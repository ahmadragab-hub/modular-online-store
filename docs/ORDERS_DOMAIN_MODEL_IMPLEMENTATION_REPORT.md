# Orders domain model implementation report

**Date:** 2026-11-13  
**Branch:** `feature/orders-models` (no commit/push)  
**Scope:** Orders Eloquent models, Inventory `order()` wiring, inverse relations, tests

---

## 1. Files inspected

- `database/migrations/2026_03_20_100007_create_orders_tables.php`
- `database/migrations/2026_03_20_100008_create_inventory_reservations_table.php`
- `docs/DATABASE_DESIGN.md`, `DOMAIN_DATA_OWNERSHIP.md`, `BUSINESS_WORKFLOWS.md`
- Existing Cart, Customer, User, ProductVariant, Currency, InventoryReservation models

No documentation vs migration conflicts.

---

## 2. Models implemented

| Model | Table |
| --- | --- |
| `Order` | `orders` |
| `OrderItem` | `order_items` |
| `OrderStatusHistory` | `order_status_histories` |

---

## 3. Relationships

### `Order`

| Method | Target |
| --- | --- |
| `customer()` | `Customer` |
| `user()` | `User` |
| `baseCurrency()` | `Currency` via `base_currency_code` |
| `transactionCurrency()` | `Currency` via `currency_code` |
| `contentLocale()` | `StoreLocale` |
| `currencyExchangeRate()` | `CurrencyExchangeRate` (nullable) |
| `items()` | `OrderItem` |
| `statusHistories()` | `OrderStatusHistory` |
| `reservations()` | `InventoryReservation` |

**Not implemented:** `cart()` — `orders` has no `cart_id`; cart linkage is via `inventory_reservations.cart_id` only.

**Status constants:** `pending_payment`, `payment_failed`, `processing`, `shipped`, `delivered`, `cancelled`, `refunded`.  
**Scopes:** `pendingPayment()`, `paymentFailed()`.

### `OrderItem`

| Method | Target |
| --- | --- |
| `order()` | `Order` |
| `productVariant()` | `ProductVariant` (nullable FK) |
| `product()` | `Product` (nullable FK) |

Timestamps: `UPDATED_AT = null` (immutable snapshot rows).

### `OrderStatusHistory`

| Method | Target |
| --- | --- |
| `order()` | `Order` |
| `actorUser()` | `User` (nullable) |

Timestamps: `UPDATED_AT = null`.

---

## 4. Existing models updated

| Model | Change |
| --- | --- |
| `InventoryReservation` | `order()` `belongsTo` |
| `Customer` | `orders()` `hasMany` |
| `User` | `orders()` `hasMany` |
| `ProductVariant` | `orderItems()` `hasMany` |
| `Currency` | `ordersAsBaseCurrency()`, `ordersAsTransactionCurrency()` |

`Cart` unchanged (no direct order FK).

---

## 5. Historical snapshots and currency

- Order monetary fields use `decimal:4`; `exchange_rate` uses `decimal:8`.
- `unit_price_base_usd` on lines uses `decimal:2`; transaction line amounts use `decimal:4`.
- `shipping_address` / `billing_address` cast to `array`.
- Models do **not** recalculate totals from catalog or current FX — values are stored as placed.

---

## 6. Tests

**File:** `tests/Unit/Domains/Orders/OrderModelsTest.php` (4 tests)

- Frozen base vs transaction currency and totals
- Order line snapshots and variant inverse
- Status history + reservation `order_id`
- Status scopes

---

## 7. Commands and results

```text
php artisan test
Tests:    42 passed (166 assertions)
Duration: ~4.1s

vendor/bin/pint (changed paths)
→ pass (ordered imports fix on Customer.php)

vendor/bin/pint --test (changed paths)
→ pass

git diff --check
→ clean

git status -sb
## feature/orders-models
 M Catalog/Customer/Identity/Inventory/Store (inverses)
 ?? app/Domains/Orders/Models/
 ?? tests/Unit/Domains/Orders/
 ?? docs/ORDERS_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md
```

Full-repo `vendor/bin/pint --test` not run (pre-existing `bootstrap/app.php` / `config/localization.php` drift).

---

## 8. Deferred

| Item | Reason |
| --- | --- |
| Payment models | Out of scope (next domain) |
| Order ↔ Cart direct Eloquent link | No `cart_id` on `orders` |
| Immutability guards on models | App-level / Actions later; history tables have no `updated_at` |
| State machine Actions | `TransitionOrderStatusAction` not in scope |

---

## 9. Issues

None.

---

## Verdict

### **ORDERS DOMAIN MODELS IMPLEMENTED AND VERIFIED**
