# Milestone 11 — Payment provider integration plan

**Date:** 2026-11-18  
**Status:** Planning only — **no application code in this milestone phase**  
**Branch context:** Builds on Milestone 10 admin + lock-order corrections  
**Companion:** [PAYMENT_PROVIDER_EVALUATION.md](PAYMENT_PROVIDER_EVALUATION.md)

---

## 1. Existing implementation findings

### Implemented today (verified in codebase)

| Area | State |
| --- | --- |
| **Place order (Strategy C)** | `PlaceOrderAction` runs order + reserve + payment row **1** inside one `DB::transaction()`, then commits (`app/Domains/Checkout/Actions/PlaceOrderAction.php`). |
| **Payment row at placement** | `Payment::create` with `status = pending`, `attempt_number = 1`, derived `idempotency_key`, amount/currency from order (~L217–225). |
| **Post-place HTTP** | `CheckoutController::store` redirects to `checkout/success/{publicUuid}` — **no** provider call, **no** redirect to gateway (`CheckoutController.php`). |
| **Success page** | Shows order number, total, `payment_pending` copy — does **not** confirm payment (`resources/views/storefront/checkout/success.blade.php`). |
| **Verified success/failure Actions** | `CompleteVerifiedPaymentSuccessAction`, `RecordPaymentFailureAction` — domain transitions only; **not** wired to HTTP webhooks. |
| **Mark paid / commit** | `MarkOrderPaidAction` + `CommitReservationAction` with full reservation validation. |
| **Lock order (order-linked)** | Order → Payment(s)↑ → Cart(s)↑ → Reservation(s)↑ → Level(s) per `OrderScopedPaymentLocks`, `OrderScopedCartLocks` (see admin implementation report). |
| **Expiry / cancel** | `ExpireReservationsAction` + `CancelUnpaidOrderWhenHoldsClearedAction`, `CancelOrderAction`, `RecordPaymentFailureAction` retain/release semantics per **ADMIN-006**, **CHECKOUT-006–009**. |
| **Schema** | `payments`, `payment_provider_events` per `2026_03_20_100009_create_payments_tables.php`. |
| **Admin payments** | Read-only list on order show (`resources/views/admin/orders/show.blade.php`). |

### Not implemented (design references exist)

| Item | Reference |
| --- | --- |
| `InitiatePaymentAction` | [CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md) §Phase C |
| `PaymentGatewayContract` | Design + [MVP_SCOPE.md](MVP_SCOPE.md); **no** PHP contract file yet |
| `HandleProviderWebhookAction` | Design §3 |
| Webhook / return URL routes | Not in `routes/web.php` or `routes/api.php` |
| Retry payment storefront flow | Design §3.C; no `RetryPaymentRequest` / routes |
| `ExpireReservationsJob` scheduler | `ExpireReservationsAction` exists; wiring to scheduler unverified in this plan |
| Reconciliation job | Deferred in design |

### Naming alignment

Design doc references `CompletePaymentSuccessAction`; implementation uses **`CompleteVerifiedPaymentSuccessAction`**. Milestone 11 should **keep the implemented name** and update diagrams to match.

---

## 2. Architecture recommendation (minimal)

### Domain boundaries

| Layer | Responsibility |
| --- | --- |
| **Payments** | Provider boundary: contract, adapter, `InitiatePaymentAction`, `ProcessProviderWebhookAction` (or split verify/apply), event persistence, mapping provider events → calls to existing Actions. |
| **Checkout** | Orchestrate post-place flow: initiate payment after commit; optional thin “checkout payment” Action that only calls Payments + returns DTO. |
| **Orders / Inventory** | **Unchanged** business rules; only called from Payments after verification. |
| **HTTP** | Webhook controller: raw body, no CSRF, delegate to Payments Action; storefront: redirect only from verified initiation DTO. |

### Provider abstraction — **proposed OPEN: PAY-005**

Introduce **one** contract in `app/Domains/Payments/Contracts/PaymentGatewayContract.php`:

```php
// Illustrative — not implemented in planning phase
interface PaymentGatewayContract
{
    public function initiateCheckout(InitiateCheckoutRequest $request): PaymentInitiationResult;

    public function verifyWebhook(VerifiedWebhookRequest $request): ProviderWebhookPayload;

    // Optional MVP: retrievePaymentStatus(ProviderReference $ref): ProviderPaymentStatus
    // Refund methods: excluded until PAY-004 approved
}
```

- **DTOs** in `app/Domains/Payments/DTOs/` — no provider SDK types in Orders/Checkout.
- **Single implementation** bound in `AppServiceProvider` until a second provider is **Approved**.
- **Fake driver** for tests implements the same contract.

**Why:** Matches existing docs; isolates SDK/HTTP; smallest surface for MVP (**required for MVP** once provider chosen).

**Not needed:** Multi-provider registry, plugin system, or extra packages unless owner approves.

---

## 3. Payment initiation sequence (exact)

### Happy path

1. Customer POSTs checkout → `PlaceOrderRequest` → `PlaceOrderAction` (transaction: order, reserve, payment attempt 1, cart `converted` **CHECKOUT-014**).
2. Transaction **commits**.
3. HTTP layer receives `PlaceOrderResult` (`paymentId`, `publicUuid`, …).
4. **`InitiatePaymentAction::execute($paymentId)`** (new; Payments domain):
   - **No** outer transaction during HTTP to provider.
   - Short DB transaction: load order + payment; **order status** ∈ (`pending_payment`, `payment_failed` for retry path); **active** reservations exist and not TTL-expired; payment `pending`.
   - Lock order → payment row(s) per existing lock helpers if status update needed (avoid holding locks during HTTP).
   - Build provider request from **server-side** `payments.amount`, `payments.currency_code`, `orders.public_uuid`, `payments.idempotency_key` (metadata only for correlation).
5. Call `PaymentGatewayContract::initiateCheckout()` **outside** any lock-holding transaction.
6. On provider success:
   - New short transaction: update `payments.provider`, `provider_payment_id`, `provider_checkout_url`, optional `initiated_at` (see schema §8), optional safe `metadata` subset.
7. Return `PaymentInitiationResult` (redirect URL) to controller.
8. Controller **302** to hosted checkout (never embed secret in Blade/JS).

### Failure and edge cases

| Scenario | Behavior |
| --- | --- |
| Network / 5xx to provider | Order stays `pending_payment`; reservation **active**; payment stays `pending` unless provider returns definitive failure in response body (adapter maps). Surface retry UI. |
| Timeout after provider accepted | Persist correlation ids if partial response; else reconciliation + webhook; **do not** blindly create duplicate session with same idempotency if provider deduplicates keys. |
| Duplicate POST place order (same idempotency) | Existing order returned; initiation may re-run or skip if `provider_checkout_url` still valid (policy in Action). |
| Order cancelled / no active reservations | Initiation **rejects** — no new provider session. |
| Customer closes browser | No state change until webhook or reconciliation. |
| Return URL without webhook | Success page stays **pending**; poll/reconcile per §5. |

**Invariant:** **No** `DB::transaction()` open during provider HTTP; **no** row locks held across network I/O.

---

## 4. Webhook sequence (exact)

### Route placement (proposed)

- `POST /webhooks/payments/{provider}` in `routes/web.php` **or** dedicated `routes/webhooks.php` included from `bootstrap/app.php`.
- Middleware: **no** `VerifyCsrfToken`; throttle; optional IP allowlist **only** if provider documents static IPs (do not rely on IP alone).
- `content-type` validation per provider.

### Handler steps (`ProcessProviderWebhookAction` or split)

| Step | Detail |
| --- | --- |
| W1 | Read **raw body** (`$request->getContent()`); reject empty/oversized payload. |
| W2 | `PaymentGatewayContract::verifyWebhook()` — signature/HMAC/timestamp tolerance per provider docs. **Failure → 401/400, no state change.** |
| W3 | Parse neutral `ProviderWebhookPayload` (event id, type, provider payment id, amount, currency, status). |
| W4 | **Insert** `payment_provider_events` first with `(provider, provider_event_id)` unique — duplicate unique violation → **200 OK**, update `processing_result = ignored`, exit. |
| W5 | Resolve `Payment` by `provider_payment_id` and/or server metadata (`payment_id` in metadata set at initiation). **Never** trust client-supplied order id without DB match. |
| W6 | If no payment: set `payment_id = null`, `processing_result = ignored` or `error`, log, **200** (avoid provider infinite retry storms per provider policy). |
| W7 | Verify **amount** and **currency_code** match `payments` row (decimal string / minor units per **CUR-008**). Mismatch → `processing_result = error`, no transition, alert. |
| W8 | Verify payment belongs to order; order not wrong customer (guest vs auth policy). |
| W9 | Map event type to success / failure / unsupported. |
| W10 | **Success (verified capture):** `DB::transaction()` → `CompleteVerifiedPaymentSuccessAction::execute($paymentId, $providerPaymentId)` which already enforces order locks and `MarkOrderPaidAction`. |
| W11 | **Failure (verified):** `RecordPaymentFailureAction` when order ∈ (`pending_payment`, `payment_failed`). |
| W12 | **Late success on cancelled order:** Do **not** call success Actions; mark event `ignored`/`error`, reconciliation flag (**CHECKOUT-009**). |
| W13 | Update `payment_provider_events.processed_at`, `processing_result`. |
| W14 | HTTP **200** for processed/ignored duplicates (provider-specific requirements documented in adapter). |

### Lock-order note

Webhook success path must enter via `CompleteVerifiedPaymentSuccessAction` (Order → Payment → …) — **do not** add alternate paths that lock Payment before Order or Reservation before Cart.

### Return URL (optional second entry)

If provider supports signed return parameters, a **separate** storefront route may **display** status only after optional verification — **never** replace webhook for proof. MVP: webhook authoritative; return URL UX-only unless provider mandates signed return with same verification module.

---

## 5. Reconciliation and failure recovery

### Scenarios

| Scenario | Design |
| --- | --- |
| Customer returned, no webhook | Show **payment pending**; optional “check status” after delay; scheduled reconciliation if provider supports **GET payment status** (**OPEN: PAY-006**). |
| Webhook, customer never returned | Webhook alone is sufficient for `processing` transition. |
| Initiation timeout, charge may exist | Reconciliation query by idempotency key / metadata `public_uuid`; do not create duplicate attempts without lookup. |
| Uncertain status | Leave order `pending_payment`/`payment_failed`; ops visibility in admin. |
| Duplicate webhook | Event table unique + idempotent Actions. |
| Delayed success | Same as normal success if order still eligible; else reconciliation only. |
| Success after cancel/expiry | **Reconciliation only** — no `MarkOrderPaidAction`. |
| Amount/currency mismatch | Reject transition; event `error`. |
| Unknown payment in event | Persist event; no order/payment mutation. |

### Scheduled command — **proposed OPEN: PAY-006**

- `payments:reconcile-stale` — orders in `pending_payment` with `pending` payments older than N minutes and `provider_payment_id` set.
- Implementation **depends on selected provider** (polling API vs none). **Do not invent** polling until **OPEN-001** resolved.
- **MVP required if** provider lacks reliable webhooks; **defer** if webhooks are primary and documented as sufficient.

---

## 6. Business invariants checklist

| Invariant | Preserved by |
| --- | --- |
| Only verified provider success → `processing` | Webhook verify + `CompleteVerifiedPaymentSuccessAction` |
| Success + commit atomic | Single transaction in success Action + existing `MarkOrderPaidAction` |
| Full reservation validation | Unchanged `MarkOrderPaidAction` |
| Failure retains reservations | `RecordPaymentFailureAction` |
| Retry while active holds | New payment row + initiation gates |
| Cancel/expiry releases stock | Existing Actions |
| Late success no resurrect | Webhook branch + order status guards |
| Duplicate success idempotent | Payment status + event unique |
| Amount/currency from server | Initiation + webhook compare to `payments` row |
| **OPEN-001** currencies only | Store `transaction_enabled` + config allowlist |

---

## 7. Security requirements

| Topic | Requirement |
| --- | --- |
| Webhook verification | Mandatory signature/HMAC via adapter; reject before persistence or mark unprocessed. |
| Secrets | `config/payments.php` + `.env`; never in repo, Blade, or browser. |
| CSRF | Exempt webhook route only; store initiation uses normal CSRF. |
| Logging | Redact card numbers, full payloads containing PCI; log event id, payment id, order id. |
| Payload size | Limit request body size on webhook route. |
| Replay | Provider timestamp tolerance where supported; event id dedup in DB. |
| Admin | **ADMIN-007** — read-only; no manual capture. |
| Client trust | No query params marking paid; storefront shows pending until server state changes. |
| PCI | Hosted checkout only; **no** claim of PCI compliance in docs. |

---

## 8. Schema and configuration

### Existing fields (sufficient for MVP core)

`payments`: `provider`, `provider_payment_id`, `provider_checkout_url`, `metadata`, attempt/idempotency, failure fields, timestamps.

`payment_provider_events`: dedup unique, `processed_at`, `processing_result`, nullable `payment_id`.

### Proposed additions — **OPEN: PAY-007** (owner approve before migration)

| Column | Table | Nullable | Why |
| --- | --- | --- | --- |
| `initiated_at` | `payments` | YES | Distinguish created-at-placement vs provider session created |
| `reconciliation_status` | `payments` | YES | `none`, `pending`, `resolved`, `escalated` — ops only |
| `provider_checkout_session_id` | `payments` | YES | Only if distinct from `provider_payment_id` for chosen provider |

**Not proposed:** duplicate of `provider_payment_id`; card fields.

### Configuration (`.env` keys — illustrative)

```env
PAYMENT_DEFAULT_PROVIDER=        # after OPEN-001
PAYMENT_WEBHOOK_TOLERANCE_SECONDS=
STRIPE_SECRET_KEY=               # example — provider-specific block
```

Document in `config/payments.php` without real credentials.

### Backward compatibility

Existing rows: null `provider` until first initiation; unique on `(provider, provider_payment_id)` applies when both set.

---

## 9. Customer-facing experience

| Screen / flow | Change |
| --- | --- |
| After place order | Redirect to **provider** (not only success page), or success page with auto-redirect if initiation inline. |
| Success / return URL | Show **pending**, **failed**, or **confirmed** from **server order/payment status** — never from provider query string alone. |
| Retry | New route: `POST /orders/{publicUuid}/payments/retry` with new idempotency key, new `payments` row, initiation. |
| Cancelled / expired | Clear messaging; link to new checkout. |
| EN/AR | `lang/en/storefront.php`, `lang/ar/storefront.php` — pending vs paid vs failed |

---

## 10. Admin experience (minimal)

Extend order show payments block (read-only):

- `provider`, `attempt_number`, `status`, `provider_payment_id`, `currency_code`, `amount`, `authorized_at`/`captured_at`/`failed_at`, safe `failure_code`/`failure_message`, `reconciliation_status` if added, link to recent `payment_provider_events` summary (type + result, not full PCI payload).

No capture/mark-paid/refund buttons (**ADMIN-007**, **PAY-004** Deferred).

---

## 11. Testing strategy (matrix summary)

See §13 in final report. Requirements:

- Unit: adapter verify, mapping, amount mismatch, unknown event, duplicate event.
- Feature: initiate redirect, webhook success/failure, bad signature, return without webhook (pending UI).
- Regression: full `php artisan test` green; existing payment/order/inventory tests unchanged.
- MySQL `@group mysql`: concurrent duplicate webhooks, success vs expiry — **opt-in**; SQLite does not prove InnoDB safety.

---

## 12. Implementation work packages (single PR)

| WP | Scope |
| --- | --- |
| **WP1** | Resolve **OPEN-001** / **OPEN-005**; `config/payments.php`; env documentation |
| **WP2** | `PaymentGatewayContract`, provider adapter, fake driver, `InitiatePaymentAction`, initiation persistence |
| **WP3** | Webhook route + `ProcessProviderWebhookAction`, event ingest, wire to `CompleteVerifiedPaymentSuccessAction` / `RecordPaymentFailureAction` |
| **WP4** | Retry payment Action + HTTP; reconciliation command if **PAY-006** approved; stale-payment admin visibility |
| **WP5** | Storefront redirect, return/pending/failed views, localization |
| **WP6** | Admin payment detail fields (read-only) |
| **WP7** | Tests (unit/feature/mysql optional), update docs, full regression, `pint` |

---

## 13. Risks and unresolved decisions

| ID | Topic | Status |
| --- | --- | --- |
| **OPEN-001** | Provider choice | Open |
| **OPEN-005** | Merchant jurisdiction / settlement | Open (new — see PROJECT_DECISIONS) |
| **PAY-005** | Gateway contract shape + binding | Open — proposed |
| **PAY-006** | Reconciliation command scope | Open — provider-dependent |
| **PAY-007** | Optional schema columns | Open |
| **PAY-004** | Refunds | Deferred |

Provider-specific webhook idempotency and HTTP status codes must be taken from official docs at WP3 time.

---

## 14. Related documents

- [PAYMENT_PROVIDER_EVALUATION.md](PAYMENT_PROVIDER_EVALUATION.md)
- [CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md)
- [PROJECT_DECISIONS.md](PROJECT_DECISIONS.md)
- [STORE_ADMINISTRATION_IMPLEMENTATION_REPORT.md](STORE_ADMINISTRATION_IMPLEMENTATION_REPORT.md) — lock order