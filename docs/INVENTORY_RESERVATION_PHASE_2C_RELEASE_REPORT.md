# Inventory reservation Phase 2c-2 — ReleaseReservationAction report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows`
**Scope:** `ReleaseReservationAction` only (no expire job)

---

## 1. Files created

| Path |
| --- |
| `app/Domains/Inventory/Actions/ReleaseReservationAction.php` |
| `app/Domains/Inventory/DTOs/ReleaseReservationInput.php` |
| `app/Domains/Inventory/DTOs/ReleaseReservationResult.php` |
| `tests/Unit/Domains/Inventory/ReleaseReservationActionTest.php` |

## 2. Files modified

None (reserve/commit unchanged).

---

## 3. Action API

**Class:** `ReleaseReservationAction`
**Method:** `execute(ReleaseReservationInput $input): ReleaseReservationResult`

| Input | `reservationId` |
| --- | --- |
| Output | `reservationId`, `productVariantId`, `quantity`, `quantityOnHandAfter`, `idempotentReplay` |

---

## 4. Transaction contract

Same as commit: `RequiresActiveDatabaseTransactionException` when `DB::transactionLevel() < 1`; no nested transaction control.

---

## 5. Lock order

1. **Cart** — `lockForUpdate()` when `cart_id` is set (skipped when null).
2. **Reservation** — `lockForUpdate()` on target row.
3. **Inventory level** — `lockForUpdate()` on variant (skipped on idempotent `released` / `expired` replay).

---

## 6. State transitions and expiry decision

| State | Behavior |
| --- | --- |
| `active`, `expires_at > now()` | → `released`, `released_at` set; `quantity_reserved -= Q` |
| `released` | Idempotent no-op |
| `expired` | Idempotent no-op (status **not** changed to `released`) |
| `committed` | `InvalidInventoryReservationStateException` |
| `active`, `expires_at <= now()` | **`ExpiredInventoryReservationException`** — **reject**; Phase **2d** `ExpireReservationsAction` must transition to `expired` and decrement reserved |

**Rationale:** Cancel/release is for merchant or customer **cancel** while the hold is still valid. TTL-ended holds are terminal via **expiry**, not manual release, so stock accounting stays aligned with `INVENTORY_RESERVATION_LIFECYCLE_DESIGN.md`.

---

## 7. Counter accounting

- `quantity_reserved -= Q`
- `quantity_on_hand` unchanged
- Pre-check: `quantity_reserved >= Q`, `Q >= 1`
- No negative counters

---

## 8. Movement semantics

| Field | Value |
| --- | --- |
| `type` | `reservation_release` |
| `quantity_delta` | `0` |
| `quantity_on_hand_after` | current on-hand (unchanged) |
| `reference_type` | `reservation` |
| `reference_id` | reservation id |

One movement per successful (non-idempotent) release.

---

## 9. Idempotency

- First release: `idempotentReplay = false`
- Repeat on `released` or `expired`: `idempotentReplay = true`, no movement, no counter change
- Committed: exception (not idempotent)

---

## 10. Tests

`ReleaseReservationActionTest` — 11 cases covering success, counters, movement, idempotent replay (released + expired status), committed rejection, TTL-active rejection, not found, missing level, counter mismatch, invalid qty, transaction guard, rollback.

**Inventory suite:** 57 passed
**Full suite:** `php artisan test` — **129 passed** (395 assertions)
**Pint:** pass
**`git diff --check`:** pass

SQLite does not prove MySQL locking (Phase 2e).

---

## 11. Known limitations

| Item | Note |
| --- | --- |
| Single reservation per call | Batch by `order_id` not implemented. |
| TTL-active holds | Must use expire Action (2d), not release. |
| Reservations without `cart_id` | Supported (cart lock skipped). |

---

## 12. Readiness for Phase 2d

`ExpireReservationsAction` should share lock order, transition `active` + `expires_at < asOf` → `expired`, decrement `quantity_reserved`, and use `reservation_release` (or notes) per design. Release path must not be used for those rows.

---

## Final verdict

**RELEASE RESERVATION IMPLEMENTED AND VERIFIED**
