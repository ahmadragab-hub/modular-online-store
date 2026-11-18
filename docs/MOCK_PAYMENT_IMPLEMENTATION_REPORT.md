# Mock payment integration — implementation report

**Milestone 11 (MVP)** — mock gateway, EGP catalog base, EGP/USD checkout.  
**Date:** 2026-11-18  
**Decision refs:** Strategy C, **ADMIN-006**, **ADMIN-007**, **CHECKOUT-006–009**, **CHECKOUT-014**, **PAY-008**, **CUR-001–006** (EGP base update).

---

## 1. Architecture and contract

- **`PaymentGatewayContract`** (`app/Domains/Payments/Contracts/PaymentGatewayContract.php`): `providerKey()`, `assertOperational()`, `initiateCheckout(Payment, Order)`.
- **`MockPaymentGateway`**: Simulated initiation; signed internal checkout URL; no outbound HTTP. Fail-closed when `APP_ENV=production` or `payments.mock_enabled` is false.
- **`PaymentInitiationResult`**: Provider key, provider payment id, redirect URL.
- **DI:** `AppServiceProvider` binds contract to mock driver per `config/payments.php` (`driver`, `mock_enabled`).
- **Authoritative success/failure:** `ProcessMockPaymentSimulationAction` verifies mock provider and signed flow, then calls **`CompleteVerifiedPaymentSuccessAction`** / **`RecordPaymentFailureAction`** — same entry points planned for real providers.

Lock order unchanged: **Order → Payments (asc) → Carts (asc) → Reservations (asc) → Inventory levels.**

---

## 2. Customer journey

1. Checkout `POST` → `PlaceOrderAction` (transaction: order, items, reservation, pending payment).
2. Commit → `InitiatePaymentAction` → redirect to signed mock payment page.
3. Customer POST success/failure (CSRF + signature) → simulation Action → success/failure handlers.
4. Redirect to order success page; status from DB (pending / failed / processing).
5. **Retry:** `POST storefront.orders.payments.retry` → `CreatePaymentRetryAttemptAction` + initiation (new attempt, same order).

---

## 3. Currency (EGP base / EGP + USD payment)

- Seeders: **EGP** `is_base`; store `base_currency_code` / default display **EGP**; default **USD** rate row (USD per 1 EGP).
- `CheckoutAmountConverter::convertBaseAmount()` — decimal math, half-up; no floats.
- **EGP** payment: rate `1.0`, no FX row required.
- **USD** payment: requires admin rate; missing rate blocks USD only (**CUR-006**).
- Orders freeze `base_currency_code`, `currency_code`, `exchange_rate`, `currency_exchange_rate_id`, totals.
- Storefront display presenters use base currency for FX-unavailable fallback (not hard-coded USD).

---

## 4. Security and production

- Mock routes and simulation controllers call `MockPaymentGateway::assertOperational()` server-side.
- Initiation/success/failure URLs require valid signatures; payment must belong to `mock` provider.
- No client-submitted amounts treated as proof of payment.
- **Production:** mock disabled → 404 on mock UI; gateway `assertOperational()` throws.

---

## 5. Admin and UX

- Admin order payments: read-only enriched fields (provider, reference, currency, amount, status, timestamps, failure text) — **ADMIN-007**.
- Storefront success page: payment state + retry when eligible.
- EN/AR strings for payment and mock simulator.

---

## 6. Tests and verification

| Suite | Result |
| --- | --- |
| `php artisan test` | **390 passed**, **7 skipped** (SQLite default) |
| Database | **SQLite** in-memory for PHPUnit |
| Skipped | MySQL concurrency / payment-expiry integration tests (`Tests\Integration\MySQL\*`) — require MySQL connection |
| Pint | Run on dirty files |
| `git diff --check` | No whitespace errors |

**Notable coverage:** `MockPaymentFlowTest` (EGP/USD, missing rate, production guard, failure + retry, duplicate success idempotency); updated checkout/currency unit and feature tests for EGP base.

**Not claimed:** MySQL duplicate-callback concurrency (tests skipped without MySQL).

---

## 7. Key files

**Created:** contract, DTO, exceptions, `MockPaymentGateway`, `InitiatePaymentAction`, `CreatePaymentRetryAttemptAction`, `ProcessMockPaymentSimulationAction`, storefront controllers, `config/payments.php`, mock Blade, `tests/Support/InteractsWithMockPayment.php`, `tests/Feature/Web/MockPaymentFlowTest.php`.

**Modified:** `AppServiceProvider`, `CheckoutController`, checkout success view, admin order show, `CheckoutAmountConverter`, `PrepareCheckoutAction` (base conversion naming), seeders, storefront presenters, routes, lang, many tests, docs (`PROJECT_DECISIONS`, `DATABASE_DESIGN`, `PROJECT_ROADMAP`, `BUSINESS_WORKFLOWS`, `CHECKOUT_WORKFLOW_DESIGN`).

---

## 8. Known limitations and blockers

- **OPEN-001:** No real payment provider.
- **OPEN-005:** Merchant jurisdiction / settlement not finalized.
- **PAY-006–007:** Stale reconciliation and optional schema columns not implemented.
- **PAY-004:** Refunds deferred.
- Mock gateway is **not** a production payment solution.

---

## 9. Future real-provider steps

1. Implement new class behind `PaymentGatewayContract` (e.g. Paymob/Stripe).
2. Bind driver in `config/payments.php` + env secrets.
3. Add verified webhook/callback route; map to `CompleteVerifiedPaymentSuccessAction` / `RecordPaymentFailureAction` after signature and amount checks.
4. Keep initiation **after** `PlaceOrderAction` commit.
5. Disable mock in all deployed environments.