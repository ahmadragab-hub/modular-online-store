# Cart domain model implementation report

**Date:** 2026-11-13  
**Branch:** `feature/cart-models` (unchanged; no commit/push)  
**Scope:** Cart Eloquent models, justified inverses, Inventory `cart()` wiring, unit tests

---

## 1. Files inspected

| Source | Purpose |
| --- | --- |
| `database/migrations/2026_03_20_100006_create_carts_tables.php` | Authoritative `carts` / `cart_items` schema |
| `database/migrations/2026_03_20_100008_create_inventory_reservations_table.php` | `cart_id` FK |
| `docs/DATABASE_DESIGN.md`, `DOMAIN_DATA_OWNERSHIP.md` | Ownership and cart rules |
| `app/Domains/Customers/Models/Customer.php` | Registered vs guest identity |
| `app/Domains/Catalog/Models/ProductVariant.php` | Line item FK target |
| `app/Domains/Inventory/Models/InventoryReservation.php` | Cart FK completion |
| Prior domain tests/reports | Conventions |

No documentation vs migration conflicts found.

---

## 2. Models implemented

| Model | Table |
| --- | --- |
| `Cart` | `carts` |
| `CartItem` | `cart_items` |

---

## 3. Relationships

### `Cart`

| Method | Type | Keys |
| --- | --- | --- |
| `customer()` | `belongsTo` | `customer_id` → `customers` (nullable) |
| `user()` | `belongsTo` | `user_id` → `users` (nullable, denormalized) |
| `baseCurrency()` | `belongsTo` | `base_currency_code` → `currencies` |
| `displayCurrency()` | `belongsTo` | `display_currency_code` → `currencies` |
| `items()` | `hasMany` | `CartItem` |
| `reservations()` | `hasMany` | `InventoryReservation` via `cart_id` |

**Constants / scope:** `STATUS_ACTIVE`, `STATUS_CONVERTED`, `STATUS_ABANDONED`; `scopeActive()`.

Guest carts: `session_token` + nullable `customer_id` (per schema). No invented ownership rules.

### `CartItem`

| Method | Type |
| --- | --- |
| `cart()` | `belongsTo` `Cart` |
| `productVariant()` | `belongsTo` `ProductVariant` |

**Casts:** `quantity` integer; `unit_price_base` `decimal:2` (USD snapshot only, no conversion).

---

## 4. Existing models updated

| Model | Change | Reason |
| --- | --- | --- |
| `InventoryReservation` | `cart()` `belongsTo` | Cart domain now exists; FK in schema |
| `Customer` | `carts()` `hasMany` | Registered customer ownership |
| `ProductVariant` | `cartItems()` `hasMany` | Inverse of line items |

`User::carts()` not added (optional denorm; inverse not required for Cart tests).

---

## 5. Schema verification

| Constraint | Verified in tests |
| --- | --- |
| `carts.session_token` UNIQUE (nullable) | Guest cart create |
| `UNIQUE (cart_id, product_variant_id)` | `test_unique_variant_per_cart` |
| `cart_items` CASCADE on cart delete | (not asserted; FK in migration) |
| Currency FKs on cart | `CurrencySeeder` + `displayCurrency` |
| `inventory_reservations.cart_id` | Reservation ↔ cart test |

---

## 6. Tests

**File:** `tests/Unit/Domains/Cart/CartModelsTest.php`

| Test | Coverage |
| --- | --- |
| Guest cart + display currency | Defaults, Store currency relation |
| Registered cart + items | Customer/user, variant, inverses |
| Unique variant per cart | DB unique |
| Reservation cart link | Inventory integration |

---

## 7. Commands and results

```text
php artisan test
Tests:    38 passed (146 assertions)
Duration: ~2.6s

vendor/bin/pint app/Domains/Cart app/Domains/Inventory/Models/InventoryReservation.php app/Domains/Customers/Models/Customer.php app/Domains/Catalog/Models/ProductVariant.php tests/Unit/Domains/Cart
→ pass

vendor/bin/pint --test (same paths)
→ pass

git diff --check
→ (no issues)

git status -sb
## feature/cart-models
 M app/Domains/Catalog/Models/ProductVariant.php
 M app/Domains/Customers/Models/Customer.php
 M app/Domains/Inventory/Models/InventoryReservation.php
 ?? app/Domains/Cart/Models/
 ?? tests/Unit/Domains/Cart/
 ?? docs/CART_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md
```

Full-repository `vendor/bin/pint --test` not run (pre-existing drift in `bootstrap/app.php` / `config/localization.php` unchanged).

---

## 8. Deferred

| Item | Reason |
| --- | --- |
| `InventoryReservation::order()` | Orders domain model not implemented |
| `User::carts()` | Optional; `user_id` is convenience denorm |
| Cart workflows (add line, merge, TTL) | Out of scope |
| Display-currency total calculations | Store/checkout Actions |

---

## 9. Issues / deviations

None. Schema and documentation aligned.

---

## Verdict

### **CART DOMAIN MODELS IMPLEMENTED AND VERIFIED**
