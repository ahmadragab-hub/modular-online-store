# Identity domain model implementation report

**Date:** 2026-03-20  
**Scope:** `User` model audit, auth wiring verification, Store FK integration, tests

---

## 1. Existing model audit

| Item | Finding |
| --- | --- |
| Location | `App\Domains\Identity\Models\User` (correct; not `app/Models`) |
| Table | `users` (default Laravel table name) |
| Primary key | `id`, auto-increment (`bigint unsigned`) |
| Auth base | `Illuminate\Foundation\Auth\User` — session guard compatible |
| Schema columns | `name`, `email`, `email_verified_at`, `password`, `remember_token`, `role`, `is_active`, timestamps |
| Identity-owned tables without Eloquent models | `password_reset_tokens`, `sessions` — Laravel framework usage only (no domain models required for MVP) |
| Other domains’ tables | No Identity models created for Customers, Catalog, etc. |

**Gaps before this pass:** `role` and `is_active` were not fillable/cast; factory lacked role/active defaults and pointed at wrong model class (`App\User`); no Store FK relationship.

---

## 2. Changes made

### `app/Domains/Identity/Models/User.php`

- `#[Fillable]` extended with `role`, `is_active`.
- Constants `ROLE_ADMIN`, `ROLE_CUSTOMER` (schema-aligned strings only; no permission package).
- Casts: `is_active` → boolean (existing `email_verified_at`, `password` hashed).
- `isActive(): bool` helper (status only; no auth gate logic).
- `createdCurrencyExchangeRates()` → `hasMany` `CurrencyExchangeRate` via `created_by_user_id`.

### `database/factories/UserFactory.php`

- Explicit `protected $model = User::class` (fixes `App\User` resolution).
- Defaults: `role = customer`, `is_active = true`.
- States: `admin()`, `inactive()`.

### `app/Domains/Store/Models/CurrencyExchangeRate.php` (Identity integration only)

- `createdByUser()` → `belongsTo` `User::class`, nullable `created_by_user_id`, `nullOnDelete` per migration.

No migration, middleware, auth UI, or Policies changes.

---

## 3. Authentication configuration verification

| Setting | Value |
| --- | --- |
| `config/auth.php` providers.users.model | `App\Domains\Identity\Models\User` (via `User::class` / optional `AUTH_MODEL` env) |
| Default guard | `web` (session) |
| Password reset table | `password_reset_tokens` |

No `.env` override of `AUTH_MODEL` in repo; default resolves to Identity `User`.

---

## 4. Store relationship integration

| Direction | Relationship | FK |
| --- | --- | --- |
| `CurrencyExchangeRate` → `User` | `createdByUser()` | `currency_exchange_rates.created_by_user_id` → `users.id` (nullable, SET NULL on delete) |
| `User` → `CurrencyExchangeRate` | `createdCurrencyExchangeRates()` | inverse |

**Not added:** `inventory_movements.created_by_user_id` inverse on `User` — Inventory domain, deferred until that domain is implemented.

---

## 5. Tests added

**File:** `tests/Unit/Domains/Identity/UserModelTest.php`

| Test | Asserts |
| --- | --- |
| `test_user_uses_users_table_and_incrementing_primary_key` | Table, PK, incrementing |
| `test_password_is_hashed_and_hidden` | `hashed` cast, hidden attributes |
| `test_role_and_active_casts` | Customer/admin/inactive roles and `isActive()` |
| `test_email_verified_at_is_cast_to_datetime` | Datetime cast |
| `test_auth_provider_uses_identity_user_model` | `config('auth.providers.users.model')` |
| `test_currency_exchange_rate_created_by_user_relationship` | Bidirectional Store ↔ Identity link |

**Results:**

- Identity: **6 passed**
- Full suite: **18 passed** (74 assertions)
- Store model tests: **unchanged, all pass**

---

## 6. Commands executed

| Command | Result |
| --- | --- |
| `php artisan test` | **18 passed** |
| `vendor/bin/pint` on changed Identity/Store/Factory/test files | **Pass** |

---

## 7. Deferred work

- Eloquent models for `password_reset_tokens` / `sessions` (not required unless custom domain logic needed).
- `User` → `Customer` one-to-one (Customers domain).
- `User` → `inventory_movements` audit relation (Inventory domain).
- Authentication **features** (login, registration, Policies, inactive-user rejection at login) — out of scope.
- Enforcing `is_active` in guards/middleware — future Identity/HTTP work.

---

## Verdict

### `IDENTITY DOMAIN MODELS IMPLEMENTED AND VERIFIED`
