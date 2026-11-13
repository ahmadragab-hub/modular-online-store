# Inventory domain model implementation report

**Date:** 2026-03-20
**Branch:** `feature/inventory-models` (no commit per task)**Scope:** Eloquent models and unit tests only

---

## 1. Inventory tables inspected

| Table | Migration |
| --- | --- |
| `inventory_levels` | `2026_03_20_100005_create_inventory_levels_and_movements_tables.php` |
| `inventory_movements` | same |
| `inventory_reservations` | `2026_03_20_100008_create_inventory_reservations_table.php` |

---

## 2. Models and responsibilities

| Model | Table | Responsibility |
| --- | --- | --- |
| `InventoryLevel` | `inventory_levels` | One row per `product_variant_id` (unique); on-hand and reserved quantities |
| `InventoryMovement` | `inventory_movements` | Immutable audit log of quantity changes |
| `InventoryReservation` | `inventory_reservations` | Checkout holds with status and expiry |

---

## 3. Relationships

| Model | Implemented | Inverse (deferred) |
| --- | --- | --- |
| `InventoryLevel` | `productVariant()` → `ProductVariant` | `ProductVariant::inventoryLevel()` — not added (Catalog unchanged) |
| `InventoryMovement` | `productVariant()`, `createdByUser()` → `User` | — |
| `InventoryReservation` | `productVariant()` | `cart()`, `order()` — **deferred** (no Cart/Order models) |

**Cart / Orders:** `inventory_reservations.cart_id` and `order_id` are fillable and nullable; FKs exist in MySQL. `belongsTo` for `Cart` and `Order` will be added when `app/Domains/Cart/Models` and `app/Domains/Orders/Models` exist.

---

## 4. Casts, constants, scopes

### `InventoryLevel`
- Timestamps: `CREATED_AT = null` (only `updated_at` in schema)
- Integer casts: `quantity_on_hand`, `quantity_reserved`, `low_stock_threshold`, `version`

### `InventoryMovement`
- `UPDATED_AT = null`; immutable `created_at`
- Constants: `TYPE_ADJUSTMENT_IN`, `TYPE_ADJUSTMENT_OUT`, `TYPE_RESERVATION_HOLD`, `TYPE_RESERVATION_RELEASE`, `TYPE_SALE_COMMIT`, `TYPE_RETURN_RESTOCK`
- Integer casts: `quantity_delta`, `quantity_on_hand_after`, `reference_id`

### `InventoryReservation`
- Status constants: `STATUS_ACTIVE`, `STATUS_COMMITTED`, `STATUS_RELEASED`, `STATUS_EXPIRED`
- Scopes: `active()`, `notExpired()`
- Integer cast: `quantity`; datetime: `expires_at`, `committed_at`, `released_at`

No float types; no workflow logic in models.

---

## 5. Catalog dependency

All inventory rows require a valid `product_variant_id` (RESTRICT on delete). Tests build variants via Catalog models (`Product`, `Size`, `Color`, `ProductVariant`).

---

## 6. Tests

**File:** `tests/Unit/Domains/Inventory/InventoryModelsTest.php`

| Test | Coverage |
| --- | --- |
| `test_inventory_level_one_per_variant_and_relationship` | Unique variant FK, quantities, `updated_at` only |
| `test_inventory_movement_immutable_audit_row` | Types, admin user FK, no `updated_at` |
| `test_inventory_reservation_persistence_and_scopes` | Variant link, nullable cart/order, scopes |

---

## 7. Verification

```text
php artisan test
Tests:    34 passed (133 assertions)
```

```text
vendor/bin/pint app/Domains/Inventory tests/Unit/Domains/Inventory
vendor/bin/pint --test (same paths)
→ pass
```

Full-repo `vendor/bin/pint --test`: still fails on pre-existing `bootstrap/app.php` and `config/localization.php` (unchanged).

**Git:** branch `feature/inventory-models`; no commit or push.

---

## 8. Files changed

| Path | Action |
| --- | --- |
| `app/Domains/Inventory/Models/InventoryLevel.php` | Added |
| `app/Domains/Inventory/Models/InventoryMovement.php` | Added |
| `app/Domains/Inventory/Models/InventoryReservation.php` | Added |
| `tests/Unit/Domains/Inventory/InventoryModelsTest.php` | Added |
| `docs/INVENTORY_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md` | Added |

No migrations. No changes to Store, Identity, Customers, or Catalog.

---

## 9. Recommendations

- Add `InventoryReservation::cart()` / `order()` when Cart and Orders domain models land.
- Optional `ProductVariant::inventoryLevel()` and `::reservations()` for ergonomic Catalog reads (Catalog-owned FK targets).
- Reserve/release/commit **Actions** in a later Inventory milestone (`BUSINESS_WORKFLOWS.md` Strategy C).

---

## Verdict

### **INVENTORY DOMAIN MODELS IMPLEMENTED AND VERIFIED**
