# Inventory reservation Phase 2c-1 — CommitReservationAction report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows`
**Scope:** `CommitReservationAction` only (no release / expire)

---

## 1. Files created

| Path |
| --- |
| `app/Domains/Inventory/Actions/CommitReservationAction.php` |
| `app/Domains/Inventory/DTOs/CommitReservationInput.php` |
| `app/Domains/Inventory/DTOs/CommitReservationResult.php` |
| `tests/Unit/Domains/Inventory/CommitReservationActionTest.php` |

## 2. Files modified

| Path | Change |
| --- | --- |
| `app/Domains/Inventory/Exceptions/InvalidStockReservationRequestException.php` | `INVENTORY_COUNTERS_INCONSISTENT` |

---

## 3. Action API

**Class:** `CommitReservationAction`
**Method:** `execute(CommitReservationInput $input): CommitReservationResult`

| Input | `reservationId` (positive int) |
| --- | --- |
| Output | `reservationId`, `productVariantId`, `quantity`, `quantityOnHandAfter`, `idempotentReplay` |

---

## 4. Transaction contract

- Requires `DB::transactionLevel() >= 1` → `RequiresActiveDatabaseTransactionException`.
- Does not begin, commit, or roll back.
- Failures propagate for caller rollback.

---

## 5. Lock order

Compatible with `ReserveStockForCartAction`:

1. **Cart** — `lockForUpdate()` when `reservation.cart_id` is set (serializes with reserve on same cart).
2. **Reservation** — `lockForUpdate()` on target row (fresh load after cart lock).
3. **Inventory level** — `lockForUpdate()` on `product_variant_id` (skipped on idempotent `committed` replay before level lock).

Stub read by id (no lock) is used only to discover `cart_id` for lock ordering.

---

## 6. State transition

| From | To | Side effects |
| --- | --- | --- |
| `active` (not past `expires_at`) | `committed` | `committed_at = now()`; `on_hand -= Q`; `reserved -= Q`; one `sale_commit` movement |
| `committed` | (unchanged) | **Idempotent no-op** — no movement, no counter change |
| `released` / `expired` / other | — | `InvalidInventoryReservationStateException` |
| `active` past `expires_at` | — | `ExpiredInventoryReservationException` (no commit after TTL) |

---

## 7. Inventory accounting

For quantity `Q`:

- `quantity_on_hand -= Q`
- `quantity_reserved -= Q`

Pre-checks (after locks):

- `Q >= 1`
- `quantity_reserved >= Q`
- `quantity_on_hand >= Q`

Violations → `InvalidStockReservationRequestException` (`INVENTORY_COUNTERS_INCONSISTENT`). Counters are not driven negative.

---

## 8. Movement semantics

| Field | Value |
| --- | --- |
| `type` | `sale_commit` |
| `quantity_delta` | `-Q` |
| `quantity_on_hand_after` | on-hand after decrement |
| `reference_type` / `reference_id` | `order` + `order_id` when set; else `reservation` + reservation id |
| `created_by_user_id` | `null` |

---

## 9. Idempotency

**Choice:** Idempotent **no-op** when `status === committed` (per lifecycle design §5).

- Returns `CommitReservationResult` with `idempotentReplay = true`.
- Does not insert a second movement or adjust counters again.
- Level read for `quantityOnHandAfter` is unlocked read (reporting only).

---

## 10. Tests

`CommitReservationActionTest` — 12 cases: success, counters, movement, order reference, idempotent replay, released/expired/TTL rejection, not found, missing level, counter inconsistency, invalid qty, transaction guard, rollback.

**Inventory suite:** 46 passed
**Full suite:** `php artisan test` — **118 passed** (362 assertions)
**Pint:** pass
**`git diff --check`:** pass

SQLite does **not** prove MySQL `FOR UPDATE` behavior (Phase 2e).

---

## 11. Known limitations

| Item | Note |
| --- | --- |
| Single reservation per call | Batch commit by `order_id` not implemented (future wrapper or repeated calls). |
| Idempotent replay | Does not re-lock level row (read-only reporting). |
| Cross-action deadlocks | Global rule: cart → reservations → levels; PlaceOrder must not invert. |
| MySQL concurrency | Unverified in CI (SQLite). |

---

## 12. Readiness for release (Phase 2c-2)

`ReleaseReservationAction` can mirror lock order and idempotent terminal states. Expire job remains Phase 2d.

Payment success orchestration should call `CommitReservationAction` inside the same DB transaction as order/payment updates.

---

## Final verdict

**COMMIT RESERVATION IMPLEMENTED AND VERIFIED**
