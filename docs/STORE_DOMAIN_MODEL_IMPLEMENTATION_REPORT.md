# Store domain model implementation report

**Date:** 2026-03-20  
**Scope:** Eloquent models and unit tests for the Store domain only

---

## 1. Models created

| Model | Path |
| --- | --- |
| `Currency` | `app/Domains/Store/Models/Currency.php` |
| `StoreLocale` | `app/Domains/Store/Models/StoreLocale.php` |
| `StoreTheme` | `app/Domains/Store/Models/StoreTheme.php` |
| `StoreSetting` | `app/Domains/Store/Models/StoreSetting.php` |
| `CurrencyExchangeRate` | `app/Domains/Store/Models/CurrencyExchangeRate.php` |

---

## 2. Table mapping

| Model | Table | Primary key |
| --- | --- | --- |
| `Currency` | `currencies` | `code` (char, non-incrementing) |
| `StoreLocale` | `store_locales` | `code` (char, non-incrementing) |
| `StoreTheme` | `store_themes` | `code` (string, non-incrementing) |
| `StoreSetting` | `store_settings` | `id` (fixed singleton `1`, non-incrementing) |
| `CurrencyExchangeRate` | `currency_exchange_rates` | `id` (auto-increment) |

---

## 3. Relationships and foreign keys

| Model | Relationship | Type | Keys |
| --- | --- | --- | --- |
| `Currency` | `exchangeRates()` | `hasMany` | `currency_exchange_rates.quote_currency_code` → `currencies.code` |
| `CurrencyExchangeRate` | `quoteCurrency()` | `belongsTo` | `quote_currency_code` → `currencies.code` |
| `StoreSetting` | `baseCurrency()` | `belongsTo` | `base_currency_code` → `currencies.code` |
| `StoreSetting` | `defaultDisplayCurrency()` | `belongsTo` | `default_display_currency_code` → `currencies.code` |
| `StoreSetting` | `defaultLocale()` | `belongsTo` | `default_locale` → `store_locales.code` |
| `StoreSetting` | `defaultTheme()` | `belongsTo` | `default_theme` → `store_themes.code` |

**Store ↔ Identity (updated):** `CurrencyExchangeRate::createdByUser()` and `User::createdCurrencyExchangeRates()` wired in the Identity domain pass (see [IDENTITY_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md](IDENTITY_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md)).

No relationships were added from Store models to Customers, Catalog, Orders, or Payments.

---

## 4. Casts and mass assignment

### Mass assignment

Laravel 13 `#[Fillable]` attributes list explicit columns per model. `StoreSetting` fillable excludes `id` so the singleton row id is not mass-assigned on create.

### Casts

| Model | Notable casts |
| --- | --- |
| `Currency` | booleans for flags; `minor_units`, `sort_order` as integer |
| `StoreLocale` / `StoreTheme` | `is_active` boolean; `sort_order` integer |
| `StoreSetting` | tax/shipping booleans; `default_tax_rate` `decimal:4`; `flat_shipping_amount` / `free_shipping_threshold` `decimal:2`; `tax_configuration` as `array` (JSON) |
| `CurrencyExchangeRate` | `rate` as `decimal:8` (string, not float); `effective_from` / `created_at` as datetime |

### Timestamps

| Model | Behavior |
| --- | --- |
| `CurrencyExchangeRate` | `UPDATED_AT = null` (migration has `created_at` only) |
| All others | Standard `created_at` / `updated_at` |

### Singleton

`StoreSetting::SINGLETON_ID = 1` and `StoreSetting::singleton()` load the single store row expected by the schema.

---

## 5. Tests added

**File:** `tests/Unit/Domains/Store/StoreModelsTest.php`

| Test | Coverage |
| --- | --- |
| `test_currency_uses_code_primary_key_and_table` | Table, PK, USD seed |
| `test_store_locale_and_theme_use_string_primary_keys` | Locale/theme PKs and attributes |
| `test_store_settings_singleton_and_relationships` | Singleton id and four belongsTo relations |
| `test_store_settings_json_cast` | `tax_configuration` array cast |
| `test_currency_exchange_rate_decimal_precision_and_relationships` | `decimal:8` string, `quoteCurrency`, `exchangeRates` on quote currency |
| `test_kwd_minor_units_from_seed` | KWD `minor_units = 3` |

Uses `RefreshDatabase` and existing reference seeders only.

**Results:** 6 Store tests passed; **12 passed** full suite (58 assertions).

---

## 6. Commands executed

| Command | Result |
| --- | --- |
| `php artisan test --filter=StoreModelsTest` | **Pass** (after correcting EGP parent on `exchangeRates`) |
| `php artisan test` | **12 passed** |
| `vendor/bin/pint app/Domains/Store tests/Unit/Domains/Store` | **Pass** |

No migrations or schema changes were made.

---

## 7. Schema / documentation notes

- **Exchange rate ownership:** Rates are USD → quote; `Currency::exchangeRates()` correctly keys off `quote_currency_code` (e.g. EGP rows belong to the EGP `Currency`, not USD).
- **`store_settings.id`:** Migration defines `id` without `autoIncrement()`; model sets `$incrementing = false`, matching the singleton seed pattern.
- **Documentation vs migrations:** Aligned with `2026_03_20_100000` and `2026_03_20_100002` migrations; no discrepancies found for Store tables.

---

## 8. Deferred work

- Store Queries / Actions (FX lookup, preference validation) — not in scope.
- Wire HTTP preference layer to Store models instead of config — still documented in [LOCALIZATION_AND_THEMES.md](LOCALIZATION_AND_THEMES.md); interim middleware unchanged.

---

## Verdict

### `STORE DOMAIN MODELS IMPLEMENTED AND VERIFIED`
