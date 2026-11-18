# Store Administration — Implementation report (Milestone 10)

**Date:** 2026-11-17  
**Branch:** `feature/store-administration`  
**Status:** Implemented (not merged; no commit in this work)

## Scope delivered

All seven work packages (WP1–WP7) are implemented per **ADMIN-001–010** and **CHECKOUT-014**. **OPEN-001**, **OPEN-003**, and **OPEN-004** remain Open.

| WP | Delivered |
| --- | --- |
| WP1 | `routes/admin.php`, admin auth, middleware, redirects, layout, EN/AR `admin.php`, `admin:create-user` |
| WP2 | Store settings edit, exchange rate create (append-only), USD base preserved |
| WP3 | Category/product/variant/image/size/color admin Actions + views; publish/archive rules |
| WP4 | Inventory levels, adjust, movements; `AdjustStockAction` invariants |
| WP5 | Order list/show, fulfillment transitions, unpaid cancel; payment Actions for verified flows only; expiry → order cancel coordination |
| WP6 | Read-only customer list/detail |
| WP7 | Dashboard metrics query |

## Expiry ↔ order cancel

`ExpireReservationsAction` now invokes `CancelUnpaidOrderWhenHoldsClearedAction` after each successful expiry when `order_id` is set. Covered by `ExpireReservationsOrderCancelTest`.

For order-linked expiries, `ExpireReservationsAction::expireOne` acquires the **order** row lock (and **payment** rows for that order) **before** cart/reservation/level locks when the order is still `pending_payment` or `payment_failed`. Paid/processing orders skip expiry of linked holds.

`CancelUnpaidOrderWhenHoldsClearedAction` runs at the end of a successful order-linked expiry inside the same transaction: it re-locks the order and payment rows, checks whether any **active** reservations remain (see limitations below), cancels the order when appropriate, and cancels eligible **pending** payment attempts via locked payment models (not bulk `UPDATE`).

## Order-scoped lock order (2026-11-18)

Milestone 10 corrected Payment ↔ Order and Reservation ↔ Cart lock inversions. Order-linked writes that touch payments and inventory follow this **first row-lock acquisition** sequence:

1. **Order** — `lockForUpdate` on the order row.
2. **Payment rows** (ascending `id`) — all `payments` for the order, via `OrderScopedPaymentLocks::lockForOrder`.
3. **Cart rows** (ascending `id`) — scope depends on the Action (see below).
4. **Reservation rows** (ascending `id`) — `lockForUpdate` on the relevant reservation set.
5. **Inventory levels** — `lockForUpdate` on the variant level per commit, release, or expiry.

**Cart ID discovery:** `OrderScopedCartLocks::lockCartsForOrder` uses a **non-locking** `SELECT DISTINCT cart_id … ORDER BY cart_id` on `inventory_reservations` for the order, then locks each `carts` row in ascending id order. Discovery does not authorize state or substitute for locked reads before mutations.

**Cart lock scope by path:**

| Path | Cart locking |
| --- | --- |
| Verified payment success (`CompleteVerifiedPaymentSuccessAction` → `MarkOrderPaidAction`) | All distinct `cart_id` values linked to reservations on the order, ascending, **before** any reservation row lock. |
| Admin/system cancel (`CancelOrderAction`) | All distinct `cart_id` values for **active** reservations on the order, ascending, **before** batch reservation locks and per-line `ReleaseReservationAction`. |
| Order-linked expiry (`ExpireReservationsAction::expireOne`) | **Only** the current reservation’s `cart_id` (if set), **before** that reservation’s `lockForUpdate`. Does **not** pre-lock every cart on the order. |
| Cart-only expiry | Cart (if present) → reservation → level; no order or payment locks. |

Per-reservation `CommitReservationAction` and `ReleaseReservationAction` still lock **Cart → Reservation → Level** on each call. Order-scoped callers must batch-lock carts and reservations first so the **first** transaction-level reservation lock never precedes the relevant cart locks.

**Helpers:** `app/Shared/Support/OrderScopedPaymentLocks.php`, `app/Shared/Support/OrderScopedCartLocks.php`.

This report does **not** assert production readiness or verified InnoDB concurrency safety; see limitations.

## Payment success invariant (2026-11-18 correction)

`MarkOrderPaidAction` validates that **order line quantities by variant** match **active** reservation quantities (one reservation row per variant at checkout; see `ReserveStockForCartAction`). It rejects expired/released/partial/missing reservations, commits every active reservation, then re-verifies all reservations are `committed` before setting `processing` and `paid_at`. Idempotent when the order is already `processing` and fully committed.

No payment provider HTTP or admin manual capture UI. `CompleteVerifiedPaymentSuccessAction` and `RecordPaymentFailureAction` exist for future verified callbacks and unit tests. Admin order UI is read-only for payments (**ADMIN-007**).

## Migrations

**None added.** Tier-1 schema sufficient.

## Verification (implementation run)

| Check | Result |
| --- | --- |
| `php artisan test` | **378 passed**, **5 skipped**, 0 failed (post-correction; **1092** assertions) |
| Skipped | 4× `CheckoutConcurrencyTest`, 1× `CustomerCartConcurrencyTest` (MySQL not configured in default `phpunit.xml`) |
| `vendor/bin/pint --test` | Passed |
| `git diff --check` | No whitespace errors |
| `npm run build` | Not run — no changes under `resources/css`, `resources/js`, or Vite config |

## Known limitations

- **SQLite tests do not prove MySQL deadlock freedom.** Query-order tests in PHPUnit observe SQL table touch order on SQLite; they do **not** prove InnoDB row-lock behavior or freedom from deadlock under parallel sessions.
- **MySQL concurrency not verified in the default test run:** opt-in integration tests (including payment/expiry and checkout `@group mysql`) are skipped unless `database.default` is `mysql`. An actual parallel InnoDB contention run was **not** demonstrated for this milestone report.
- **Multi-cart expiry parity:** Payment success and unpaid cancellation pre-lock **all** relevant order carts before reservations. Order-linked expiry locks **only** the expiring reservation’s cart. Parity across writers is **not** guaranteed if an order ever had multiple distinct carts; normal checkout (**CHECKOUT-014** / `PlaceOrderAction`) currently associates line holds with **one** cart per order.
- **Inventory level lock ordering:** Multi-reservation payment success commits via repeated `CommitReservationAction` calls; each path locks one level per variant in reservation iteration order, **not** a single global ascending `inventory_levels.id` sort across the whole order.
- **`CancelUnpaidOrderWhenHoldsClearedAction`:** Uses a non-locking `exists()` check for remaining **active** reservations after inventory work (ADMIN-006 cancel-when-cleared semantics). This is a business gate, not a lock-order substitute.
- MySQL parallel concurrency for admin inventory adjust not separately integration-tested (same baseline as checkout).
- Revenue/sales metrics omitted from dashboard (no captured-payment volume metric without provider).
- Catalog admin UI is functional per **UX-003**; not production polish (**UX-005**).

## Related documents

- [STORE_ADMINISTRATION_PLAN.md](STORE_ADMINISTRATION_PLAN.md)
- [STORE_ADMINISTRATION_SCOPE.md](STORE_ADMINISTRATION_SCOPE.md)
- [PROJECT_DECISIONS.md](PROJECT_DECISIONS.md) §L2 **ADMIN-006**