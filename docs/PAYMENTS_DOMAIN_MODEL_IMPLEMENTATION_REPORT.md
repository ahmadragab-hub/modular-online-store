# Payments domain model implementation report

**Date:** 2026-11-13  
**Branch:** `feature/payments-models` (no commit/push)  
**Scope:** Payments Eloquent models, inverse relationships on Order and Currency, unit tests

---

## 1. Files inspected

- `AGENTS.md`
- `docs/ARCHITECTURE.md`, `docs/DOMAIN_MAP.md`, `docs/DATABASE_DESIGN.md`, `docs/DOMAIN_DATA_OWNERSHIP.md`, `docs/BUSINESS_WORKFLOWS.md`, `docs/MVP_SCOPE.md`
- `database/migrations/2026_03_20_100009_create_payments_tables.php`
- `database/migrations/2026_03_20_100007_create_orders_tables.php` (order FK context)
- `app/Domains/Orders/Models/Order.php`
- `app/Domains/Store/Models/Currency.php`
- `tests/Unit/Domains/Orders/OrderModelsTest.php` (conventions)

**Schema vs documentation:** No blocking conflicts. `refunds` is documented as Tier 2 and **does not exist** in migrations; not implemented.

---

## 2. Payments tables discovered (MVP migrations)

| Table | Purpose |
| --- | --- |
| `payments` | One row per payment attempt on an order (`attempt_number`) |
| `payment_provider_events` | Idempotent webhook/callback audit log |

**Not in schema:** `refunds`, separate `payment_attempts` table (attempts are columns on `payments`).

---

## 3. Models implemented

| Model | Table |
| --- | --- |
| `App\Domains\Payments\Models\Payment` | `payments` |
| `App\Domains\Payments\Models\PaymentProviderEvent` | `payment_provider_events` |

---

## 4. Relationships

### `Payment`

| Method | Type | FK / keys |
| --- | --- | --- |
| `order()` | `belongsTo` | `order_id` → `orders.id` |
| `currency()` | `belongsTo` | `currency_code` → `currencies.code` |
| `providerEvents()` | `hasMany` | `payment_provider_events.payment_id` |

**Status constants:** `pending`, `authorized`, `captured`, `failed`, `cancelled` (per `DATABASE_DESIGN.md`).

**Scopes:** `pending()`, `failed()`, `captured()`.

**No User relationship:** schema has no actor/creator FK on `payments`.

### `PaymentProviderEvent`

| Method | Type | FK / keys |
| --- | --- | --- |
| `payment()` | `belongsTo` | `payment_id` (nullable, `nullOnDelete`) |

**Processing result constants:** `success`, `ignored`, `error`.

**Timestamps:** `UPDATED_AT = null` (migration has `created_at` only).

---

## 5. Inverse relationships on existing models

| Model | Change |
| --- | --- |
| `Order` | `payments()` `hasMany` |
| `Currency` | `payments()` `hasMany` via `currency_code` |

No changes to Identity, Customers, or other domains (no schema support).

---

## 6. Casts and financial precision

| Model | Field | Cast |
| --- | --- | --- |
| `Payment` | `amount` | `decimal:4` (matches `DECIMAL(19, 4)`) |
| `Payment` | `attempt_number` | `integer` |
| `Payment` | `metadata` | `array` |
| `Payment` | `authorized_at`, `captured_at`, `failed_at` | `datetime` |
| `PaymentProviderEvent` | `payload` | `array` |
| `PaymentProviderEvent` | `processed_at` | `datetime` |

Amounts are stored as historical snapshots; models do not recalculate from orders or live FX.

---

## 7. Nullable fields and constraints (verified in tests)

- `payments`: `provider`, `provider_payment_id`, `provider_checkout_url`, failure fields, metadata, lifecycle timestamps nullable until set.
- `payment_provider_events`: `payment_id` nullable for unmatched webhooks.
- Unique: `idempotency_key`; `(order_id, attempt_number)`; `(provider, provider_payment_id)`; `(provider, provider_event_id)`.

---

## 8. Tests

**File:** `tests/Unit/Domains/Payments/PaymentModelsTest.php`

| Test | Covers |
| --- | --- |
| `test_payment_persists_amount_currency_and_metadata` | Persistence, order/currency relations, inverses |
| `test_payment_retry_uses_incremented_attempt_number` | Multiple attempts per order |
| `test_payment_status_scopes` | `pending()`, `failed()`, `captured()` |
| `test_payment_enforces_idempotency_key_unique` | DB unique constraint |
| `test_payment_provider_event_links_to_payment_and_allows_null_payment` | Event relations, null payment, no `updated_at` |
| `test_payment_provider_event_enforces_provider_event_unique` | DB unique constraint |
| `test_payment_nullable_provider_fields_before_selection` | Nullable provider columns |
| `test_payment_enforces_order_attempt_unique` | `(order_id, attempt_number)` unique |

No new factories added; orders/customers created inline like `OrderModelsTest`.

---

## 9. Commands and results

```text
vendor/bin/pint app/Domains/Payments app/Domains/Orders/Models/Order.php app/Domains/Store/Models/Currency.php tests/Unit/Domains/Payments
→ PASS (fixed ordered imports / FQCN in test file)

vendor/bin/pint --test app/Domains/Payments tests/Unit/Domains/Payments
→ PASS

php artisan test
→ Tests: 50 passed (190 assertions), Duration: 4.29s

git diff --check
→ (no output — clean)

git status -sb
→ ## feature/payments-models
```

---

## 10. Deferred work

| Item | Reason |
| --- | --- |
| `Refund` model | No `refunds` migration in MVP batch |
| Payment provider integration | Out of scope (models only) |
| Checkout / payment Actions | Out of scope |
| State transition logic on models | Belongs in Actions/Services |
| `User` on payments | No FK in schema |

---

## 11. Issues / deviations

None. Payment attempts are modeled as separate `payments` rows with `attempt_number`, not a child table — documentation and migration agree.

---

## 12. Verdict

**PAYMENTS DOMAIN MODELS IMPLEMENTED AND VERIFIED**
