# Checkout completion implementation report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows`
**Scope:** Inventory reservation expiry (verified existing), `PlaceOrderAction`, web checkout HTTP, tests, documentation

---

## 1. Files created and modified

### Created

| Path | Purpose |
| --- | --- |
| `app/Domains/Checkout/Actions/PlaceOrderAction.php` | Checkout saga: prepare, reserve, order, link, payment |
| `app/Domains/Checkout/DTOs/PlaceOrderInput.php` | Place-order input |
| `app/Domains/Checkout/DTOs/PlaceOrderResult.php` | Place-order result |
| `app/Domains/Checkout/Exceptions/PlaceOrderException.php` | Checkout placement errors |
| `app/Domains/Checkout/Exceptions/PlaceOrderIdempotencyConflictException.php` | Payload mismatch for same idempotency key |
| `app/Domains/Checkout/Support/PlaceOrderPayloadFingerprint.php` | Canonical payload hash for idempotency |
| `app/Http/Requests/Web/PlaceOrderRequest.php` | Web validation |
| `app/Http/Controllers/Web/Storefront/CheckoutController.php` | HTTP coordination |
| `resources/views/storefront/checkout/show.blade.php` | Minimal checkout form |
| `resources/views/storefront/checkout/success.blade.php` | Pending-payment confirmation |
| `tests/Support/BuildsCheckoutPlaceOrderFixtures.php` | Shared test fixtures |
| `tests/Unit/Domains/Checkout/PlaceOrderActionTest.php` | Place-order unit tests |
| `tests/Feature/Web/CheckoutPlaceOrderHttpTest.php` | Web checkout feature tests |
| `tests/Integration/MySQL/CheckoutConcurrencyTest.php` | MySQL concurrency tests (`@group mysql`) |

### Modified

| Path | Change |
| --- | --- |
| `routes/web.php` | Checkout routes |
| `lang/en/storefront.php`, `lang/ar/storefront.php` | Checkout copy and errors |
| `phpunit.xml` | `tests/Integration` suite |
| `docs/MVP_SCOPE.md` | `PlaceOrderAction` marked done |
| Pint pass on changed PHP files | Formatting only (includes prior dirty files) |

### Pre-existing (unchanged in this task, verified)

| Path | Purpose |
| --- | --- |
| `app/Domains/Inventory/Actions/ExpireReservationsAction.php` | Reservation TTL expiry |
| `app/Console/Commands/ExpireInventoryReservationsCommand.php` | `inventory:expire-reservations` |
| `bootstrap/app.php` | Scheduler: every minute, `withoutOverlapping()` |

---

## 2. Inventory expiry behavior

See [INVENTORY_RESERVATION_PHASE_2D_EXPIRY_REPORT.md](INVENTORY_RESERVATION_PHASE_2D_EXPIRY_REPORT.md).

- Batch expiry of `active` reservations with `expires_at <= now`
- Status → `expired` (never `released`); `quantity_reserved` decremented; on-hand unchanged
- Movement: `reservation_release` with expiry notes
- Command: `php artisan inventory:expire-reservations --limit=100`
- **Production:** cron must run `php artisan schedule:run` every minute (or invoke the command on a timer)

**Does not** cancel orders or change payment state.

---

## 3. PlaceOrderAction workflow

Single outer `DB::transaction()`:

1. `assertPreconditions` — idempotency key, guest rules, content locale
2. `Order` row `lockForUpdate` by `idempotency_key` — replay or continue
3. `PrepareCheckoutAction` — authoritative pricing and validation
4. `ReserveStockForCartAction` — TTL from `store_settings.reservation_ttl_minutes`
5. Resolve/create `Customer` (guest or registered)
6. Insert `orders`, `order_items`, `order_status_histories` (snapshots + FX freeze)
7. Update `inventory_reservations.order_id`
8. Insert initial `payments` row (`attempt_number = 1`, `pending`, deterministic payment idempotency key)

Cart remains **`active`** until payment success (future `MarkCartConvertedAction`).

No payment provider HTTP inside the transaction.

---

## 4. Transaction boundary and lock ordering

| Step | Locks / notes |
| --- | --- |
| Idempotency | `orders.idempotency_key` `FOR UPDATE` |
| Reserve (Inventory) | Cart → reservations → levels (ascending variant id) |
| Replay race | Unique on `idempotency_key` + duplicate-key catch + replay |

Participates in caller transaction when `DB::transactionLevel() > 0` (Inventory reserve requires active transaction).

**Final review fix (2026-11-13):** `ReserveStockForCartAction` rejects active reservations with non-null `order_id` (`ACTIVE_RESERVATIONS_LINKED_TO_ORDER`) after the cart lock, **before** cart-only reuse. Prevents a second place order (new idempotency key) from reusing holds already tied to Order A. Idempotent replay is unaffected because `PlaceOrderAction` returns the existing order before calling reserve again. See [CHECKOUT_FINAL_REVIEW_FIX_REPORT.md](CHECKOUT_FINAL_REVIEW_FIX_REPORT.md).

---

## 5. Idempotency

- **Storage:** existing `orders.idempotency_key` UNIQUE (no migration)
- **Fingerprint:** `PlaceOrderPayloadFingerprint` hashes cart id, currency, locale, guest email, addresses, note, and line variant/qty sets
- **Same key + equivalent payload:** returns existing `PlaceOrderResult` (`idempotentReplay = true`) for `pending_payment` / `payment_failed`
- **Same key + different payload:** `PlaceOrderIdempotencyConflictException` → HTTP **409**
- **First payment key:** `hash('sha256', 'payment:{orderKey}:attempt:1')` (stable replays)

**Limitations:** fingerprint is application-derived (not a DB column). Concurrent first submits rely on row lock + unique constraint; **MySQL row-lock behavior is not proven by SQLite CI** (see §9).

---

## 6. Order and payment snapshots

Frozen on `orders` / `order_items`:

- Customer linkage, `guest_email` when guest
- `base_currency_code`, `currency_code`, `exchange_rate`, `currency_exchange_rate_id`, `exchange_rate_effective_from`
- `content_locale`, addresses JSON, monetary totals (decimal strings)
- Line snapshots: SKU, names, labels, USD base unit, transaction unit, line subtotal

Initial `payments`: amount = `grand_total`, currency = order transaction currency, no provider fields.

---

## 7. HTTP integration

| Route | Name |
| --- | --- |
| `GET /checkout` | `storefront.checkout.show` |
| `POST /checkout` | `storefront.checkout.store` |
| `GET /checkout/success/{publicUuid}` | `storefront.checkout.success` |

- CSRF on POST (Laravel web stack)
- `PlaceOrderRequest` validation
- Guest: `session_token` + `guest_email`; registered: `Auth` + cart ownership
- Customer-safe error messages (no internal exception text)
- Idempotency conflict: **409**

**MVP UI note:** checkout form is intentionally minimal (cart id + addresses) for integrators; catalog/cart UX is not part of this task.

---

## 8. Test results

```text
php artisan test
Tests: 3 skipped, 164 passed (500 assertions)
Duration: ~12s
```

Skipped: `Tests\Integration\MySQL\CheckoutConcurrencyTest` (3) — requires `DB_CONNECTION=mysql`.

Also run:

- `vendor/bin/pint --dirty` — pass
- `git diff --check` — pass
- `php artisan migrate:status` — all business migrations Ran

---

## 9. MySQL concurrency verification status

| Test | Status |
| --- | --- |
| Two carts, one unit | **Skipped** in default CI (SQLite) |
| Duplicate reserve same cart | **Skipped** |
| Duplicate place-order idempotency key | **Skipped** |

Run locally:

```bash
# .env.testing or phpunit env: DB_CONNECTION=mysql, configured database
php artisan test --group=mysql
```

SQLite covers functional correctness; **does not** prove InnoDB `FOR UPDATE` serialization under parallel workers.

---

## 10. Remaining limitations and production requirements

| Item | Notes |
| --- | --- |
| Payment provider | No `InitiatePaymentAction`, webhooks, or redirect |
| Order cancel on reservation expiry | Inventory only; Orders cancel deferred |
| Reconciliation / late capture | Documented only |
| Cart UX | No storefront cart browse/add; checkout accepts `cart_id` |
| Store shipping seed | Default seeder has `shipping_enabled=true` without flat rate; disable or configure flat rate in production |
| Scheduler | Production cron for `schedule:run` |
| True parallel place-order load test | Requires MySQL verification (§9) |

Approved rules preserved: payment failure does not auto-release; cart not converted on place; late payment = reconciliation only.

---

## 11. Steps to complete and merge branch

1. Run full suite on target environment (MySQL optional but recommended for `--group=mysql`).
2. Configure store: shipping flat amount or `shipping_enabled=false`; FX rates for enabled currencies.
3. Ensure scheduler/cron for inventory expiry.
4. Code review: `PlaceOrderAction`, idempotency fingerprint, HTTP 409 behavior.
5. Merge `feature/checkout-workflows` → `main` after review.
6. **Post-merge (not blocking merge):** payment provider adapter, cart/catalog storefront, order cancel on expiry, customer checkout UX.

---

## Verdict

**CHECKOUT IMPLEMENTED WITH DOCUMENTED LIMITATIONS**

MVP place-order path (prepare → reserve → pending order → pending payment) is implemented, tested on SQLite, and exposed via web routes. Production hardening depends on payment integration, scheduler ops, shipping/FX configuration, and MySQL concurrency verification.
