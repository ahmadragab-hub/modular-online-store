# Checkout final review fix — order-linked reservation reuse

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows`

---

## Problem

After `PlaceOrderAction` linked cart reservations via `inventory_reservations.order_id`, the cart stayed **active**. A second checkout with a **different** idempotency key could call `ReserveStockForCartAction`, which reused active rows when variant/qty maps matched, then create a second order and overwrite `order_id`.

---

## Fix

**File:** `app/Domains/Inventory/Actions/ReserveStockForCartAction.php`

After cart lock and stale-TTL check, when non-stale `active` reservations exist:

- If **any** have `order_id !== null` → throw `InvalidStockReservationRequestException` with code **`active_reservations_linked_to_order`**.
- Do **not** enter the reuse path; no counter or movement changes.

**Exception constant:** `InvalidStockReservationRequestException::ACTIVE_RESERVATIONS_LINKED_TO_ORDER`

Lock order unchanged: cart → reservations → levels.

**Idempotent replay:** Unchanged. `PlaceOrderAction` short-circuits on existing `orders.idempotency_key` and never calls reserve on replay.

**Cart conversion:** Not changed.

**Migrations:** None.

---

## Tests added

### Inventory (`ReserveStockForCartActionTest`)

| Test | Proves |
| --- | --- |
| `test_order_linked_active_reservations_cannot_be_reused` | Rejection; `order_id` preserved; counters/movements unchanged |
| `test_cart_only_active_reservations_with_null_order_id_still_reused` | Cart-only reuse still works |

### Checkout (`PlaceOrderActionTest`)

| Test | Proves |
| --- | --- |
| `test_second_order_on_same_cart_with_different_idempotency_key_rejected` | One order/payment; reservations stay on Order A; key A replay succeeds |

---

## Verification

```text
php artisan test --filter=ReserveStockForCartActionTest   # 22 passed
php artisan test --filter=PlaceOrderActionTest            # 15 passed
php artisan test                                          # 164 passed, 3 skipped (MySQL group)
vendor/bin/pint --dirty                                   # pass
git diff --check                                          # pass
```

---

## Remains unverified

- **MySQL parallel checkout** on the same cart with two different idempotency keys (logical fix is correct under cart row serialization; CI still SQLite-only for concurrency).
- **Concurrent** first placement race — still relies on order idempotency unique + lock (unchanged).

---

## Verdict

**CART RESERVATION REUSE BUG FIXED AND VERIFIED**
