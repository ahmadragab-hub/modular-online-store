# Database design approval report

**Date:** 2026-11-13  
**Scope:** MVP single-store fashion e-commerce (documentation only)  
**Sources:** [DATABASE_DESIGN.md](DATABASE_DESIGN.md), [DOMAIN_DATA_OWNERSHIP.md](DOMAIN_DATA_OWNERSHIP.md), [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md), [MVP_SCOPE.md](MVP_SCOPE.md), [LOCALIZATION_AND_THEMES.md](LOCALIZATION_AND_THEMES.md), [ARCHITECTURE.md](ARCHITECTURE.md), [DOMAIN_MAP.md](DOMAIN_MAP.md)

This report separates **confirmed decisions** (stakeholder-approved in the design pass) from **assumptions** (reasonable defaults awaiting implementation or business input).

---

## Final domain / table ownership summary

| Domain | MVP tables | Role |
| --- | --- | --- |
| **Identity** | `users` (extended), `password_reset_tokens`, `sessions` | Auth subject; `role` admin/customer |
| **Customers** | `customers`, `customer_addresses` | Shopper profile, addresses, optional locale/theme/display currency |
| **Store** | `store_settings`, `currencies`, `currency_exchange_rates`, `store_locales`, `store_themes` | USD base, FX, locale/theme config, shipping/tax flags, reservation TTL |
| **Catalog** | `categories`, `category_product`, `sizes`, `colors`, `products`, `product_translations`, `product_variants`, `product_images` | Merchandising; USD prices; **en+ar** translations |
| **Inventory** | `inventory_levels`, `inventory_movements`, `inventory_reservations` | Stock, audit, checkout reservations |
| **Cart** | `carts`, `cart_items` | USD line snapshots; display currency for UI |
| **Checkout** | *(none)* | Orchestration only (Actions) |
| **Orders** | `orders`, `order_items`, `order_status_histories` | Immutable commercial record + FX snapshot |
| **Payments** | `payments`, `payment_provider_events` | Charges, webhooks, idempotency |

**Framework (not domain-owned):** `jobs`, `cache`, `failed_jobs`, etc.

---

## Final table inventory

### Tier 1 — include in first migration batch

| # | Table | Domain |
| --- | --- | --- |
| 1 | `currencies` | Store |
| 2 | `currency_exchange_rates` | Store |
| 3 | `store_settings` | Store |
| 4 | `store_locales` | Store |
| 5 | `store_themes` | Store |
| 6 | `users` (+ `role`, `is_active`) | Identity |
| 7 | `customers` | Customers |
| 8 | `customer_addresses` | Customers |
| 9 | `categories` | Catalog |
| 10 | `category_product` | Catalog |
| 11 | `sizes` | Catalog |
| 12 | `colors` | Catalog |
| 13 | `products` | Catalog |
| 14 | `product_translations` | Catalog |
| 15 | `product_variants` | Catalog |
| 16 | `product_images` | Catalog |
| 17 | `inventory_levels` | Inventory |
| 18 | `inventory_movements` | Inventory |
| 19 | `inventory_reservations` | Inventory |
| 20 | `carts` | Cart |
| 21 | `cart_items` | Cart |
| 22 | `orders` | Orders |
| 23 | `order_items` | Orders |
| 24 | `order_status_histories` | Orders |
| 25 | `payments` | Payments |
| 26 | `payment_provider_events` | Payments |

**Laravel baseline:** `password_reset_tokens`, `sessions` (unchanged pattern).

### Tier 2 — deferred (documented, not first batch)

- `shipping_methods`
- `category_translations`
- `refunds`
- `checkout_sessions` (only if multi-step draft checkout is required)

### Documented only (no MVP DDL priority)

- `product_attributes` / `product_attribute_values`

---

## Critical relationships and constraints

### Commercial immutability

- `orders.currency_code`, `orders.exchange_rate`, `orders.currency_exchange_rate_id`, `orders.exchange_rate_effective_from`, and all `DECIMAL(19, 4)` totals are **frozen at placement**.
- `order_items` store USD base + transaction-currency unit/line amounts; **no updates** after insert.
- `payments.amount` / `payments.currency_code` must match the parent order at charge time.

### Idempotency and duplication prevention

| Constraint | Location |
| --- | --- |
| `UNIQUE (orders.idempotency_key)` | Duplicate place-order |
| `UNIQUE (orders.public_uuid)` | Payment return correlation |
| `UNIQUE (orders.order_number)` | Human reference |
| `UNIQUE (payments.idempotency_key)` | Per payment attempt |
| `UNIQUE (provider, provider_event_id)` | Webhook deduplication |

**Implementation note:** Nullable `idempotency_key` allows multiple NULL rows in MySQL; application must always set keys on checkout submit and payment initiation.

### Inventory integrity

- Reservations tie to `order_id` (and optionally `cart_id` for tracing); **no** `checkout_session_id`.
- Available stock = `on_hand - quantity_reserved` (application + row locks).
- Expiry job transitions reservations to `expired` / `released` and cancels stale orders.

### Foreign keys (documented behaviors)

- `currency_exchange_rates.quote_currency_code` → `currencies` **RESTRICT**
- `customers.user_id` → `users` **SET NULL**
- `customer_addresses.customer_id` → `customers` **CASCADE**
- `product_variants` → product **CASCADE**; size/color **RESTRICT**
- `inventory_levels.product_variant_id` → variant **RESTRICT**
- `order_items.product_variant_id` → variant **SET NULL** (historical row preserved)

### Publish and sellability

- `PublishProductAction` requires **`product_translations` for `en` and `ar`**, active variants, and inventory setup.
- Cart/checkout reject non-`published` products.

---

## Currency and translation decisions

### Confirmed

| Topic | Decision |
| --- | --- |
| Base currency | **USD** |
| Supported | **USD, EGP, KWD** |
| Minor units | From `currencies.minor_units`: USD **2**, EGP **2**, KWD **3** — never hardcoded in conversion code |
| Rates | Admin append-only `currency_exchange_rates` (USD → quote) |
| Display vs transaction | Independent; checkout selects **transaction** currency explicitly |
| Rounding | Decimal math; **half-up** per component; documented order in [DATABASE_DESIGN.md](DATABASE_DESIGN.md) |
| Snapshots | Order rate row + effective timestamp preserved |

### Translations and locale

| Topic | Decision |
| --- | --- |
| Store locales | `en`, `ar`; RTL/LTR via `store_locales` |
| Product copy | **Catalog** owns `product_translations`; **both locales required to publish** |
| Storefront product titles | From translation for active locale; **no fallback** to another language |
| Order snapshots | `orders.content_locale` + localized `product_name` on lines at placement |
| Categories (MVP) | Single `categories.name` (English/admin); `category_translations` Tier 2 |

### Themes

- `store_themes`: `light`, `dark`; defaults in `store_settings`.
- **Store** validates preferences; **Customers** optional `preferred_theme`; guests via session/cookie (see [LOCALIZATION_AND_THEMES.md](LOCALIZATION_AND_THEMES.md)).

---

## Checkout and payment workflow

### Confirmed: Strategy C

1. Validate cart and transaction currency (rate must exist for EGP/KWD).
2. **Single DB transaction:** customer resolution, `pending_payment` order + line snapshots, reservations, pending `payments` row — **no provider HTTP**.
3. Commit.
4. `InitiatePaymentAction` (provider HTTP).
5. Verified callback/return → capture, `processing`, commit stock, convert cart.
6. Failure → `payment_failed`, **retain reservations** for retry until TTL.
7. TTL job → release stock, `cancelled`, new checkout required.

### No `checkout_sessions` (MVP)

Correlation via `orders.public_uuid`, idempotency via `orders.idempotency_key` and `payments.idempotency_key`.

**Evolution:** Introduce `checkout_sessions` only for multi-step draft checkout before an order exists.

### Payment provider

**Unresolved (business):** Provider choice and which transaction currencies it supports. Schema does not assume a vendor; `payments.provider` is generic.

---

## MVP scope versus deferred features

| In MVP (Tier 1) | Deferred |
| --- | --- |
| Full catalog + en/ar translations | `category_translations` |
| Strategy C + reservations | `checkout_sessions` |
| Manual FX + multi-currency checkout | Automated FX feeds |
| Flat USD shipping converted at checkout | `shipping_methods` |
| Tax columns with `tax_enabled = 0` | Jurisdiction-specific tax engine |
| Hosted/redirect payments | Stored cards, complex auth/capture variants |
| Admin + storefront web | Full API parity, guest order portal |

---

## Unresolved decisions requiring business input

| # | Topic | Blocks |
| --- | --- | --- |
| 1 | **Tax jurisdiction** and rates | Enabling `tax_enabled`, populating `default_tax_rate` / `tax_configuration` |
| 2 | **Payment provider** and supported charge currencies | Payment integration, `transaction_enabled` flags per currency at runtime |
| 3 | Fulfillment status workflow | Admin training / notifications |
| 4 | API auth model (session vs token) | API milestone, not core schema |
| 5 | Guest order history | Product decision (magic link vs login-only) |
| 6 | `users.role` vs permission package | Admin authorization depth |

**Before tax implementation, business must supply:** selling countries/regions, B2C/B2B model, VAT/sales tax registration, inclusive vs exclusive pricing, product tax classes (if any).

---

## Potential implementation risks

| Risk | Mitigation |
| --- | --- |
| Provider mandates non–Strategy C flow | Adapter in **Payments**; document variant without changing snapshots |
| KWD 3-decimal amounts vs provider integer minor units | Map using `currencies.minor_units` in gateway layer |
| Reservation TTL shorter than slow redirects | Tune `reservation_ttl_minutes`; monitor abandon rates |
| Concurrent last-unit checkout | Reservation inside locked transaction; fail fast |
| Webhook-before-browser race | Idempotent handler; UI polls order status |
| Interim config-based locale/theme vs DB | Seed Store tables from config; switch HTTP validation to Store Queries |
| Discounts/promotions absent | All `discount_total` default 0 until Tier 3 |

---

## Recommended migration and implementation order

1. **Store reference data:** `currencies` (seed), `store_locales`, `store_themes`, `store_settings`, `currency_exchange_rates`.
2. **Identity extension:** `users.role`, `is_active`.
3. **Catalog foundation:** `sizes`, `colors`, `categories`, `products`, `product_translations`, `product_variants`, `product_images`, pivot.
4. **Inventory:** `inventory_levels`, `inventory_movements`, `inventory_reservations`.
5. **Customers:** `customers`, `customer_addresses`.
6. **Cart:** `carts`, `cart_items`.
7. **Orders:** `orders`, `order_items`, `order_status_histories`.
8. **Payments:** `payments`, `payment_provider_events`.
9. **Application layer:** Store preference Actions; migrate off `config/localization.php` and `config/theme.php`.
10. **Jobs:** `ExpireReservationsJob`, optional reconciliation job.

Within each step: migrations → seeders → domain Models/Actions (implementation phase, out of scope for this report).

---

## Cross-document consistency check (completed)

| Check | Result |
| --- | --- |
| `checkout_sessions` removed from MVP schema | Aligned across DATABASE, ARCHITECTURE, OWNERSHIP, WORKFLOWS, MVP_SCOPE |
| `payment_failed` + retry with active reservation | Aligned (payment-fail workflow corrected) |
| FX rounding and `minor_units` | Aligned |
| Publish requires en + ar | Aligned in DATABASE, OWNERSHIP, DOMAIN_MAP, MVP_SCOPE |
| Tax off until jurisdiction known | Aligned |
| Checkout owns no tables | Aligned |
| Interim locale/theme migration path | [LOCALIZATION_AND_THEMES.md](LOCALIZATION_AND_THEMES.md) |

---

## Verdict

### **READY FOR MIGRATION DESIGN**

**Explanation**

All **confirmed** product and architecture decisions are reflected consistently across design documents. The MVP table set is bounded (26 business tables + Laravel auth/session). Order and payment snapshots are sufficient to preserve historical monetary and FX context. Checkout, reservation expiry, `payment_failed` retry, and idempotency paths agree after the payment-failure workflow correction.

Remaining gaps are **explicitly unresolved business choices** (tax jurisdiction, payment provider, guest order history). They do **not** introduce contradictory columns or force redesign of Tier 1 migrations: tax remains disabled with zero totals; `payments.provider` is agnostic.

**Not claimed:** Implementation-ready payment integration, tax calculation, or production FX compliance — those depend on inputs in the unresolved table above.

**Next step (when approved):** Author ordered Laravel migrations and seeders matching [DATABASE_DESIGN.md](DATABASE_DESIGN.md), without altering domain boundaries.

---

## Related documents

- [DATABASE_DESIGN.md](DATABASE_DESIGN.md)
- [DOMAIN_DATA_OWNERSHIP.md](DOMAIN_DATA_OWNERSHIP.md)
- [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md)
- [MVP_SCOPE.md](MVP_SCOPE.md)
