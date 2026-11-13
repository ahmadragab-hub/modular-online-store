# Inventory reservation Phase 2a report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows`
**Scope:** Query + exception foundation only (no reservation Actions)

---

## 1. Files created

| Path | Purpose |
| --- | --- |
| `app/Domains/Inventory/Queries/FindInventoryLevelsForVariantIdsQuery.php` | Load levels by variant ids with optional `lockForUpdate()` |
| `app/Domains/Inventory/Exceptions/MissingInventoryLevelException.php` | Missing `inventory_levels` row for requested variant |
| `app/Domains/Inventory/Exceptions/InsufficientStockException.php` | Available qty below requested (Phase 2b reserve) |
| `app/Domains/Inventory/Exceptions/InventoryReservationNotFoundException.php` | Reservation lookup miss (by id or order) |
| `app/Domains/Inventory/Exceptions/InvalidInventoryReservationStateException.php` | Wrong status for commit/release/idempotent ops |
| `app/Domains/Inventory/Exceptions/ExpiredInventoryReservationException.php` | Expired / TTL-ended reservation on paid path |
| `tests/Unit/Domains/Inventory/FindInventoryLevelsForVariantIdsQueryTest.php` | Query behavior tests |
| `tests/Unit/Domains/Inventory/InventoryReservationExceptionsTest.php` | Exception message/context smoke tests |

## 2. Files modified

None (models, migrations, Checkout, Cart, Orders unchanged).

---

## 3. Schema re-verification

Re-read `database/migrations/2026_03_20_100005_create_inventory_levels_and_movements_tables.php` and `InventoryLevel` model:

- Unique `product_variant_id` on `inventory_levels`.
- Columns: `quantity_on_hand`, `quantity_reserved`, `low_stock_threshold`, `version`, `updated_at` only.
- No auto-provisioning of levels in application code today; missing row is a hard failure for reservation (same as design).

---

## 4. Query: `FindInventoryLevelsForVariantIdsQuery`

**Method:** `find(array $productVariantIds, bool $lockForUpdate = false): Collection<int, InventoryLevel>`

| Behavior | Detail |
| --- | --- |
| ID normalization | Cast to int, drop non-positive, unique, **sort ascending** |
| Ordering | SQL `orderBy('product_variant_id')` (matches lock ordering for Phase 2b) |
| Empty input | Returns empty `Collection` (no query) |
| Completeness | If any requested id has no level → `MissingInventoryLevelException` with `missingProductVariantIds` |
| Locking | When `$lockForUpdate === true`, applies Eloquent `lockForUpdate()` on the builder |
| Transactions | Does **not** start, commit, or roll back transactions |
| Mutations | Read-only; does not create levels, reservations, or movements |

**Transaction expectations:** Callers (`ReserveStockForOrderAction`, commit/release Actions) must open an outer `DB::transaction()` first, then call `find(..., lockForUpdate: true)` so InnoDB row locks are held until commit/rollback.

**Why not inline Eloquent in Actions?** Single place enforces ascending variant order, one-query batch load, and consistent missing-level handling—required by the lifecycle design without duplicating logic in each Action.

**Deferred from design doc:** `GetAvailableQuantityQuery` was listed for 2a in the lifecycle design; Phase 2b reserve Actions can compute `on_hand - reserved` on locked `InventoryLevel` rows. A shared availability helper can be added later if `PrepareCheckoutAction` refactoring is prioritized.

---

## 5. Exceptions

| Class | Intended use (Phase 2b+) |
| --- | --- |
| `MissingInventoryLevelException` | Query completeness; variant not stocked in inventory |
| `InsufficientStockException` | After lock, `available < requested` on reserve |
| `InventoryReservationNotFoundException` | Commit/release/expire target missing |
| `InvalidInventoryReservationStateException` | Non-idempotent illegal transition (e.g. commit when `released`) |
| `ExpiredInventoryReservationException` | Paid path when reservation past TTL / not `active` (reconciliation signal) |

All are `final`, extend `Exception`, expose readonly context for logging and HTTP mapping later. No shared base class (avoids unnecessary hierarchy).

---

## 6. Test results

### Focused Inventory suite

```
tests/Unit/Domains/Inventory/
  14 passed (36 assertions)
```

Covers: ordering, deduplication, missing level, single-query multi-load, lock flag inside transaction, exception payloads.

### Full suite

```
php artisan test
  86 passed (284 assertions)
```

### Pint

```
vendor/bin/pint app/Domains/Inventory/Queries app/Domains/Inventory/Exceptions
  (pass)
```

### `git diff --check`

No conflict markers or whitespace errors on tracked diffs.

---

## 7. MySQL locking limitations (test environment)

- PHPUnit uses **SQLite in-memory** (`phpunit.xml`: `DB_CONNECTION=sqlite`).
- Tests verify `lockForUpdate: true` runs successfully **inside** an active transaction; they do **not** prove exclusive row locking or race prevention.
- SQLite does not provide InnoDB-style pessimistic concurrency; real lock contention tests remain **Phase 2e** on MySQL.

---

## 8. Blockers for Phase 2b

**None.** Schema and models support reserve workflow as designed:

- `InventoryReservation` statuses and FKs exist.
- `InventoryMovement` types include `reservation_hold`, `reservation_release`, `sale_commit`.
- Query + exceptions are in place for batch lock → availability check → reservation insert.

Phase 2b should implement `ReserveStockForOrderAction` (and line helper), movement writes, and `InsufficientStockException` on availability failure, using this query with `lockForUpdate: true` inside the caller’s transaction.

---

## 9. Final verdict

**PHASE 2A IMPLEMENTED AND VERIFIED**
