# Inventory reservation Phase 2b — C1 fix report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows`
**Issue:** Critical same-cart concurrent duplicate reserves (`INVENTORY_RESERVATION_PHASE_2B_CODE_REVIEW.md` C1)

---

## 1. Change summary

`ReserveStockForCartAction::execute()` now **locks the cart row first** and performs all reservation reads **after** that lock. Stale-active detection uses the same locked reservation result set (no pre-cart `exists()` query).

**Files modified:**

| File | Change |
| --- | --- |
| `app/Domains/Inventory/Actions/ReserveStockForCartAction.php` | Cart `lockForUpdate`, eligibility checks, unified locked reservation query, lock-order docblock |
| `app/Domains/Inventory/Exceptions/InvalidStockReservationRequestException.php` | `CART_NOT_ACTIVE`, `CART_EXPIRED` |
| `tests/Unit/Domains/Inventory/ReserveStockForCartActionTest.php` | +4 tests (missing/inactive/expired cart, stale holds) |
| `docs/INVENTORY_RESERVATION_PHASE_2B_REPORT.md` | Lock order and concurrency notes |

---

## 2. Exact lock sequence

After `DB::transactionLevel() >= 1` and input validation (lines, expiry only — **no reservation DB reads**):

1. **`carts`** — `Cart::query()->whereKey($cartId)->lockForUpdate()->first()`
2. **Eligibility** — `status = active`; `expires_at` not in the past (`CART_NOT_ACTIVE`, `CART_EXPIRED`)
3. **`inventory_reservations`** — all `status = active` for `cart_id` with `lockForUpdate()->get()`
4. **Stale rule** — any locked row with `expires_at <= now()` → `STALE_ACTIVE_RESERVATIONS`
5. **Reuse / mismatch** — non-expired subset vs requested variant map
6. **`inventory_levels`** — `FindInventoryLevelsForVariantIdsQuery::find($variantIds, lockForUpdate: true)` (ascending `product_variant_id`)
7. **Availability** → **create** reservations, counters, movements using **`$cart->getKey()`** from the locked model

**Documented lock order:** Cart → cart reservations → inventory levels (ascending variant id).

**No nested transactions or commits.**

---

## 3. Reservation re-query behavior

| Review question | After fix |
| --- | --- |
| Query reservations before cart lock? | **No** |
| Reuse collection from before cart lock? | **No** |
| Re-query after level lock wait? | **Not required for C1** — same-cart serialization is on the cart row; second txn blocks on cart lock until first finishes, then reads reservations under cart lock |

Cross-cart contention remains on **inventory level** rows only (unchanged design).

---

## 4. Cart eligibility

Aligned with `PrepareCheckoutAction` cart rules:

- Cart must exist (`CART_NOT_FOUND`)
- `status === active` (`CART_NOT_ACTIVE`)
- `expires_at` not past (`CART_EXPIRED`)

---

## 5. Tests

| Test | Purpose |
| --- | --- |
| Existing 16 cases | Reuse, mismatch, rollback, sequential idempotency — **still pass** |
| `test_rejects_missing_cart` | No row after lock lookup |
| `test_rejects_inactive_cart` | Converted cart |
| `test_rejects_expired_cart` | Past `expires_at` |
| `test_rejects_stale_active_reservations_on_cart` | TTL passed, status still active |

**Results:**

- `php artisan test tests/Unit/Domains/Inventory/` — **34 passed**
- `php artisan test` — **106 passed** (329 assertions)
- Pint — pass
- `git diff --check` — pass

**SQLite limitation:** Tests do **not** prove two MySQL sessions contending on the same cart; that remains **Phase 2e**.

---

## 6. Remaining concurrency risks

| Risk | Status |
| --- | --- |
| Same-cart duplicate reserves (C1) | **Mitigated** via cart `FOR UPDATE` |
| Cross-cart last unit on one variant | Level locks — needs MySQL 2e |
| Multiple `active` rows per variant per cart (H4) | Not addressed; no schema unique |
| PlaceOrder without cart lock elsewhere | Callers must use this Action (not bypass) |
| Deadlock with future Actions | Preserve lock order: cart → reservations → levels |

---

## 7. PlaceOrder integration note

`PlaceOrderAction` should call `ReserveStockForCartAction` **inside its transaction** without locking the same cart through a separate code path first (avoid inconsistent lock order). Optional: lock order idempotency row **before** cart if design requires — document global order when Phase 3 is implemented.

---

## Final verdict

**C1 FIXED WITH LIMITATIONS**

Same-cart serialization is implemented as specified. **MySQL concurrent verification** is still outstanding (Phase 2e).
