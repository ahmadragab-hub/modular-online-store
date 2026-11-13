# Inventory reservation Phase 2b report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows`
**Scope:** `ReserveStockForCartAction` only (no commit/release/expire/PlaceOrder)

---

## 1. Files created

| Path |
| --- |
| `app/Domains/Inventory/Actions/ReserveStockForCartAction.php` |
| `app/Domains/Inventory/DTOs/ReserveStockForCartInput.php` |
| `app/Domains/Inventory/DTOs/ReserveStockForCartLineInput.php` |
| `app/Domains/Inventory/DTOs/ReserveStockForCartResult.php` |
| `app/Domains/Inventory/DTOs/ReserveStockForCartLineResult.php` |
| `app/Domains/Inventory/Exceptions/RequiresActiveDatabaseTransactionException.php` |
| `app/Domains/Inventory/Exceptions/InvalidStockReservationRequestException.php` |
| `app/Domains/Inventory/Exceptions/ActiveCartReservationMismatchException.php` |
| `tests/Unit/Domains/Inventory/ReserveStockForCartActionTest.php` |

## 2. Files modified

None outside Inventory (models/migrations/Checkout untouched).

---

## 3. Action API and transaction contract

**Class:** `ReserveStockForCartAction`
**Method:** `execute(ReserveStockForCartInput $input): ReserveStockForCartResult`

**Input:**

| Field | Meaning |
| --- | --- |
| `cartId` | Cart receiving holds (`order_id` left null at creation) |
| `lines` | Non-empty list of `ReserveStockForCartLineInput` (unique variant id per line, qty ≥ 1) |
| `expiresAt` | Future UTC expiry for new reservation rows |

**Output:**

| Field | Meaning |
| --- | --- |
| `cartId` | Echo of input cart |
| `lines` | Per-variant reservation id + qty |
| `reusedExistingReservations` | `true` when an identical active hold set was reused |

**Transaction contract:**

- Requires `DB::transactionLevel() >= 1` or throws `RequiresActiveDatabaseTransactionException`.
- Does **not** `beginTransaction`, `commit`, or `rollBack`.
- Any thrown exception propagates to the caller; the outer transaction rolls back all inserts/updates.
- Callers (future `PlaceOrderAction`) must lock/reserve inside their single checkout transaction.

---

## 4. Reservation creation and cart association

Per variant (after availability pass):

1. `INSERT inventory_reservations` — `status = active`, `cart_id` set, `order_id = null`, `expires_at` from input.
2. `quantity_reserved += quantity` on `inventory_levels`.
3. `INSERT inventory_movements` — `type = reservation_hold`.

`order_id` linking is deferred to Checkout when the order row exists (Phase 3).

**Lock order:** `carts` row (`lockForUpdate`) → all `active` `inventory_reservations` for cart (`lockForUpdate`) → stale/reuse/mismatch rules → `inventory_levels` by ascending variant id (`lockForUpdate`) → availability check → create rows.

See `docs/INVENTORY_RESERVATION_PHASE_2B_C1_FIX_REPORT.md` for the same-cart serialization fix.

---

## 5. Idempotency and duplicate-request behavior

**Schema fact:** There is **no** unique index on `(cart_id, product_variant_id)` or “one active hold per cart variant.” Idempotency is **application-level** only.

| Scenario | Behavior |
| --- | --- |
| Same cart, same variant/qty map, non-expired `active` rows | **Reuse** — return existing reservation ids; `reusedExistingReservations = true`; no counter or movement changes |
| Same cart, different quantities or variant set while non-expired `active` rows exist | **`ActiveCartReservationMismatchException`** — no mutation |
| Prior rows `released`, `expired`, or `committed` | Not counted as active; **new** reserve allowed |
| `active` rows with `expires_at <= now()` (TTL passed, job not run) | **`InvalidStockReservationRequestException` (`stale_active_reservations`)** — blocks double reserve until Phase 2d expiry processing |

**Concurrency (post–C1 fix):**

- Same-cart parallel `execute` calls serialize on the **cart row lock** before reservation reads; the second transaction sees the first’s committed or in-flight holds once it acquires the cart lock.
- **Remaining:** no DB unique on active holds; duplicate rows per variant if data corruption; **cross-cart** last-unit races still require MySQL Phase 2e proof.
- **PlaceOrder** should still use order `idempotency_key` and lock order row where applicable.

---

## 6. Stock accounting

- **Available** at decision time: `quantity_on_hand - quantity_reserved` on locked level rows.
- **Reserve:** `quantity_on_hand` unchanged; `quantity_reserved` increased once per successful create.
- Negative `on_hand` or `reserved` on a level → `InvalidStockReservationRequestException`.
- Multi-variant: all variants validated before any insert; failure on one variant aborts the whole Action (outer rollback).

---

## 7. Movement record semantics

| Field | Hold value |
| --- | --- |
| `type` | `reservation_hold` |
| `quantity_delta` | `0` (hold does not change physical on-hand) |
| `quantity_on_hand_after` | current `quantity_on_hand` after hold |
| `reference_type` | `reservation` |
| `reference_id` | new reservation id |
| `created_by_user_id` | `null` (system checkout path) |
| `created_at` | `now()` |

---

## 8. Expiry handling

- Input `expiresAt` must be **strictly in the future** (`InvalidStockReservationRequestException::INVALID_EXPIRY`).
- Stored on each new reservation row.
- No scheduler/job in this phase.

---

## 9. Tests and results

**File:** `tests/Unit/Domains/Inventory/ReserveStockForCartActionTest.php` (20 cases)

Covers: single/multi variant, boundary stock, insufficient stock, multi-item rollback, idempotent reuse, mismatch, cart missing/inactive/expired, stale active reservations, released/expired/committed history, invalid input, missing level, expiry validation, transaction guard.

**Inventory suite:** 34 passed (after C1 fix)
**Full suite:** `php artisan test` — **106 passed** (329 assertions)
**Pint:** pass on Inventory PHP
**`git diff --check`:** pass

Tests use SQLite; they do **not** prove InnoDB `FOR UPDATE` races (Phase 2e).

---

## 10. Known limitations and concurrency risks

| Limitation | Impact |
| --- | --- |
| No DB uniqueness for active cart holds | Theoretical duplicate rows if logic bug; same-cart serialize via cart `FOR UPDATE` (C1 fix) |
| SQLite test DB | No real MySQL concurrent cart-lock proof (Phase 2e) |
| Stale `active` + past `expires_at` | Blocked with `stale_active_reservations` until expire Action (2d) runs |
| Cart mismatch on cart change | Client must release/cancel or wait for TTL before changing cart composition mid-checkout |

---

## 11. Readiness for Phase 2c

**Ready** to implement `CommitReservationAction` and `ReleaseReservationAction`:

- Reserve path writes consistent reservation rows, counters, and audit movements.
- Exceptions from Phase 2a remain valid for commit/release.
- `PlaceOrderAction` (Phase 3) should wrap `ReserveStockForCartAction` in its transaction and later set `order_id` on reservations.

---

## Final verdict

**PHASE 2B IMPLEMENTED WITH LIMITATIONS** (same-cart C1 addressed in C1 fix report)

Functional reserve + cart-scoped idempotent reuse; same-cart duplication mitigated via cart row lock. MySQL concurrency matrix and schema-level guarantees still Phase 2e / optional migration.
