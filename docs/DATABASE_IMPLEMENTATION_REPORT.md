# Database implementation report

**Date:** 2026-03-20  
**Scope:** Tier‑1 migrations and reference seeders only  
**Design source:** [DATABASE_DESIGN_APPROVAL_REPORT.md](DATABASE_DESIGN_APPROVAL_REPORT.md), [DATABASE_DESIGN.md](DATABASE_DESIGN.md)

---

## 1. Migrations created (by domain)

### Identity

| Migration | Action |
| --- | --- |
| `0001_01_01_000000_create_users_table` | **Modified:** `InnoDB` engine on `users`, `password_reset_tokens`, `sessions` |
| `2026_03_20_100001_add_role_and_is_active_to_users_table` | Add `role` (default `customer`), `is_active` (default `1`), index on `role` |

### Store

| Migration | Tables |
| --- | --- |
| `2026_03_20_100000_create_store_reference_tables` | `currencies`, `store_locales`, `store_themes`, `store_settings` |
| `2026_03_20_100002_create_currency_exchange_rates_table` | `currency_exchange_rates` (immutable `created_at` only) |

### Customers

| Migration | Tables |
| --- | --- |
| `2026_03_20_100003_create_customers_tables` | `customers`, `customer_addresses` |

### Catalog

| Migration | Tables |
| --- | --- |
| `2026_03_20_100004_create_catalog_foundation_tables` | `sizes`, `colors`, `categories`, `products`, `product_translations`, `product_variants`, `product_images`, `category_product` |

### Inventory

| Migration | Tables |
| --- | --- |
| `2026_03_20_100005_create_inventory_levels_and_movements_tables` | `inventory_levels`, `inventory_movements` |
| `2026_03_20_100008_create_inventory_reservations_table` | `inventory_reservations` |

### Cart

| Migration | Tables |
| --- | --- |
| `2026_03_20_100006_create_carts_tables` | `carts`, `cart_items` |

### Orders

| Migration | Tables |
| --- | --- |
| `2026_03_20_100007_create_orders_tables` | `orders`, `order_items`, `order_status_histories` |

### Payments

| Migration | Tables |
| --- | --- |
| `2026_03_20_100009_create_payments_tables` | `payments`, `payment_provider_events` |

**Framework (unchanged):** `0001_01_01_000001_create_cache_table`, `0001_01_01_000002_create_jobs_table`

---

## 2. Table inventory and domain owners

| Table | Domain |
| --- | --- |
| `currencies` | Store |
| `currency_exchange_rates` | Store |
| `store_settings` | Store |
| `store_locales` | Store |
| `store_themes` | Store |
| `users` | Identity |
| `customers` | Customers |
| `customer_addresses` | Customers |
| `sizes`, `colors`, `categories`, `products`, `product_translations`, `product_variants`, `product_images`, `category_product` | Catalog |
| `inventory_levels`, `inventory_movements`, `inventory_reservations` | Inventory |
| `carts`, `cart_items` | Cart |
| `orders`, `order_items`, `order_status_histories` | Orders |
| `payments`, `payment_provider_events` | Payments |

**Not created:** `checkout_sessions`, `refunds`, `shipping_methods`, `category_translations` (per Tier‑2 / deferred design).

---

## 3. Seeders and seeded records

| Seeder | Records | Idempotent strategy |
| --- | --- | --- |
| `CurrencySeeder` | USD (base, minor_units 2), EGP (2), KWD (3) | `updateOrInsert` on `code` |
| `StoreLocaleSeeder` | `en` (ltr), `ar` (rtl) | `updateOrInsert` on `code` |
| `StoreThemeSeeder` | `light`, `dark` | `updateOrInsert` on `code` |
| `StoreSettingsSeeder` | Singleton row `id = 1` (USD base/display, `tax_enabled = 0`, `default_locale = en`, `default_theme = light`, `reservation_ttl_minutes = 15`) | `updateOrInsert` on `id` |

`DatabaseSeeder` calls the four seeders above only. **No** exchange rates, products, customers, orders, or payment provider data.

**Re-seed check:** Running `php artisan db:seed` twice left `currencies` count at **3** (no duplicates).

---

## 4. Foreign keys, uniques, and indexes (highlights)

- **Store:** `store_settings` → `currencies`, `store_locales`, `store_themes` (RESTRICT).
- **Customers:** `user_id` → `users` (SET NULL, UNIQUE); preference FKs to currencies/locales/themes; addresses → customers (CASCADE).
- **Catalog:** `product_translations (product_id, locale)` UNIQUE; locale → `store_locales`; variants → product (CASCADE), size/color (RESTRICT); SKU UNIQUE.
- **Orders:** `public_uuid`, `order_number`, `idempotency_key` UNIQUE; monetary totals `decimal(19,4)`; FKs to currencies, `store_locales`, `currency_exchange_rates`.
- **Payments:** `idempotency_key` UNIQUE; `(order_id, attempt_number)` UNIQUE; `(provider, provider_payment_id)` UNIQUE; `currency_code` → currencies (RESTRICT).
- **Webhooks:** `(provider, provider_event_id)` UNIQUE on `payment_provider_events`.
- **Inventory reservations:** placed **after** `carts` and `orders` (FKs to `cart_id`, `order_id`).
- **Carts:** `session_token` UNIQUE; `display_currency_code` / `base_currency_code` → currencies.

All Tier‑1 creates use **`InnoDB`** so MySQL enforces foreign keys on this environment.

---

## 5. Monetary precision and currency verification

| Column pattern | Implementation |
| --- | --- |
| Catalog USD prices | `decimal(12, 2)` on variants, cart line base, order USD snapshot |
| Transaction amounts | `decimal(19, 4)` on orders, order lines, payments |
| Exchange rates | `decimal(18, 8)` on rates and `orders.exchange_rate` |
| Tax rate (future) | `decimal(5, 4)` nullable on `store_settings` |

**Seeded minor units:** USD **2**, EGP **2**, KWD **3**; USD `is_base = 1`.

---

## 6. Commands executed and results

| Command | Result |
| --- | --- |
| `php artisan migrate:fresh --seed` | **Success** — all 13 migration batches applied, seeders completed |
| `php artisan migrate:status` | All migrations **Ran** (batch 1) |
| `php artisan test` | **6 passed** (21 assertions) |
| `php artisan db:show` | MySQL 8.3.0, database `modular_online_store` |
| `php artisan db:table currencies` / `orders` | Confirmed `decimal` scales; orders unique keys present |
| `php artisan db:seed` (second run) | **Success** — no duplicate currencies |
| `Schema::hasTable('checkout_sessions')` | **false** |

---

## 7. Test results

```
Tests:    6 passed (21 assertions)
Duration: ~0.8–1.0s
```

PHPUnit uses SQLite in-memory (`phpunit.xml`); migrations apply cleanly in tests (no seed required for storefront preference tests).

---

## 8. Deviations from approved design

| Deviation | Reason |
| --- | --- |
| **`inventory_reservations` migration after `orders` (and `carts`)** | Approval list ordered reservations before carts; schema requires `cart_id` and `order_id` FKs. Physical order adjusted for dependency correctness. |
| **`InnoDB` on all Tier‑1 tables + `users`** | Local WAMP default was `MyISAM`, which prevented FK creation (error 1824). Explicit `engine('InnoDB')` restores designed constraints. |
| **`Schema::defaultStringLength(191)` in `AppServiceProvider`** | MySQL utf8mb4 index limit on `users.email` UNIQUE; required for `migrate:fresh` on MySQL 8. |
| **`DatabaseSeeder` no longer creates `Test User`** | Seed scope limited to reference data per task; tests do not depend on seeded users. |

No columns or tables were added beyond approved Tier‑1 design.

---

## 9. Unresolved / follow-up items

- **Exchange rates:** Table exists; no seed rows (per instruction not to invent rates). EGP/KWD checkout remains blocked until admin inserts rates.
- **`transaction_enabled`:** All three currencies seeded as transaction-enabled in reference data; runtime should still gate on payment provider when chosen.
- **Eloquent models / Actions:** Not in scope; tables have no domain models yet.
- **Optional:** Set `default_storage_engine=InnoDB` in MySQL server config so future tables default correctly without explicit engine.

---

## Verdict

### `DATABASE FOUNDATION IMPLEMENTED AND VERIFIED`

Tier‑1 schema migrations and reference seeders match the approved design (with documented migration-order and MySQL engine adjustments). `migrate:fresh --seed`, schema inspection, idempotent re-seed, and the full test suite completed successfully on local MySQL.
