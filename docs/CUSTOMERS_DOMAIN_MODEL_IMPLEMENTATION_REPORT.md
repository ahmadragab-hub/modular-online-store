# Customers domain model implementation report

**Date:** 2026-03-20  
**Branch (local):** `feature/customers-models` (not committed per task)  
**Scope:** Eloquent models + unit tests only

---

## Models implemented

| Model | Path |
| --- | --- |
| `Customer` | `app/Domains/Customers/Models/Customer.php` |
| `CustomerAddress` | `app/Domains/Customers/Models/CustomerAddress.php` |

---

## Tables covered

| Table | Owner | Notes |
| --- | --- | --- |
| `customers` | Customers | Guest (`user_id` NULL) or registered (unique `user_id`) |
| `customer_addresses` | Customers | CASCADE delete with customer |

Source migration: `2026_03_20_100003_create_customers_tables.php`.

---

## Relationships implemented

### `Customer`

| Method | Type | Keys |
| --- | --- | --- |
| `user()` | `belongsTo` | `customers.user_id` → `users.id` (nullable) |
| `preferredDisplayCurrency()` | `belongsTo` | `preferred_display_currency_code` → `currencies.code` |
| `preferredLocale()` | `belongsTo` | `preferred_locale` → `store_locales.code` |
| `preferredTheme()` | `belongsTo` | `preferred_theme` → `store_themes.code` |
| `addresses()` | `hasMany` | `customer_addresses.customer_id` |

### `CustomerAddress`

| Method | Type | Keys |
| --- | --- | --- |
| `customer()` | `belongsTo` | `customer_id` → `customers.id` |

### Identity inverse (minimal)

| Model | Method | Reason |
| --- | --- | --- |
| `User` | `customer()` `hasOne` | Schema unique `user_id`; inverse of registered shopper link |

No Cart, Order, or Payment relationships (those domains not implemented).

---

## Scopes and casts

| Item | Detail |
| --- | --- |
| `Customer::scopeRegistered()` | `whereNotNull('user_id')` |
| `Customer::scopeGuestIdentity()` | `whereNull('user_id')` |
| `Customer` casts | `marketing_opt_in` → boolean |
| `CustomerAddress` casts | `is_default_shipping`, `is_default_billing` → boolean |

---

## Design decisions

- **No factories** for Customer — tests create rows explicitly (per task: avoid unnecessary test data factories).
- **Preference FKs** — optional nullable columns; relationships mirror Store reference tables (same pattern as `StoreSetting`).
- **Default shipping/billing uniqueness** — enforced in application layer per design; not in DB partial unique index.
- **User email vs customer email** — independent columns; syncing is future Customers/Identity workflow, not model responsibility.

---

## Tests

**File:** `tests/Unit/Domains/Customers/CustomerModelsTest.php`

| Test | Coverage |
| --- | --- |
| `test_customer_persists_guest_and_registered` | Persistence, casts, `User` ↔ `Customer` |
| `test_customer_preference_relationships` | Currency / locale / theme FKs |
| `test_customer_address_relationship_and_cascade` | `hasMany`, `belongsTo`, CASCADE delete |
| `test_unique_email_and_user_id_constraints` | Unique email |
| `test_unique_user_id_constraint` | One customer per user |
| `test_guest_and_registered_scopes` | Query scopes |

**PHPUnit:**

```text
php artisan test
Tests:    24 passed (95 assertions)
Duration: ~2.8s
```

---

## Pint

```text
vendor/bin/pint app/Domains/Customers tests/Unit/Domains/Customers app/Domains/Identity/Models/User.php
→ pass

vendor/bin/pint --test (changed Customer-related paths)
→ pass
```

**Note:** `vendor/bin/pint --test` on the **entire repository** still reports style drift in `bootstrap/app.php` and `config/localization.php` (pre-existing, unrelated to this task). Customer/Identity touch files satisfy Pint.

---

## Files changed

| File | Change |
| --- | --- |
| `app/Domains/Customers/Models/Customer.php` | **Added** |
| `app/Domains/Customers/Models/CustomerAddress.php` | **Added** |
| `app/Domains/Identity/Models/User.php` | `customer()` `hasOne` |
| `tests/Unit/Domains/Customers/CustomerModelsTest.php` | **Added** |
| `docs/CUSTOMERS_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md` | **Added** |

No migrations, routes, controllers, or Store model changes.

---

## Unresolved / recommendations

- **CustomerFactory** — add when feature tests need many customers.
- **`User::customer()` test** — covered indirectly via Customers tests; optional duplicate in `UserModelTest`.
- **Link guest to user** — `LinkCustomerToUserAction` (Tier 2 in MVP_SCOPE) not implemented.
- **Commit** — user requested no git commit on this task.

---

## Verdict

### **CUSTOMERS DOMAIN MODELS IMPLEMENTED AND VERIFIED**

Models match the approved schema; relationships and constraints are covered by unit tests; full suite passes.

**Verification commands:**

1. `php artisan test` → **24 passed**
2. `vendor/bin/pint --test` on Customer paths → **pass**
