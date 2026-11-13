# MVP scope

Prioritization for the **first usable** single-store fashion release. Schema in [DATABASE_DESIGN.md](DATABASE_DESIGN.md).

---

## Tier 1 — Required for first usable MVP

### Store & identity

- [ ] `store_settings` seeded (`base_currency_code` = USD, `tax_enabled` = 0 until jurisdiction known)
- [ ] `currencies` seeded (USD, EGP, KWD with correct `minor_units`)
- [ ] `currency_exchange_rates` admin insert-only (USD→EGP, USD→KWD)
- [ ] `store_locales` (`en`, `ar`) and `store_themes` (`light`, `dark`)
- [ ] Store Actions for locale, theme, display currency preferences (migrate from interim config/middleware)
- [ ] Storefront switchers: language, theme, display currency
- [ ] Checkout **transaction currency** selector (provider-supported only)
- [ ] `users.role` (`admin`, `customer`)
- [ ] Admin login (Blade); customer registration + login
- [ ] Basic authorization Policies (admin routes, own cart/orders)

### Catalog

- [ ] Categories (flat or tree via `parent_id`)
- [ ] Products CRUD (draft/publish)
- [ ] **`product_translations`** — **both `en` and `ar` required to publish**
- [ ] Global `sizes`, `colors`
- [ ] Product variants with unique SKU and **USD** price
- [ ] Product images (disk path storage)
- [ ] Storefront browse: category, product detail (locale from Store preference)

### Inventory

- [ ] `inventory_levels` per variant
- [ ] Admin stock adjustment + movements audit
- [ ] `inventory_reservations` with TTL from store settings
- [ ] Reserve / release / commit integrated in checkout (Strategy C)
- [ ] Expire reservations scheduled job

### Customers

- [ ] `customers`, `customer_addresses`
- [ ] Optional `preferred_locale`, `preferred_theme`, `preferred_display_currency_code`
- [ ] Registered customer address book
- [ ] Guest checkout with email + address snapshots on order

### Cart

- [ ] Guest cart (`session_token`) and authenticated cart
- [ ] Add/update/remove lines with USD snapshots (`unit_price_base`)
- [ ] `display_currency_code` on cart; converted totals for UI only
- [ ] Merge cart on login (basic)

### Checkout & orders

- [ ] `PlaceOrderAction` (Strategy C — see [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md))
- [ ] `orders.idempotency_key`, `orders.public_uuid` (no `checkout_sessions` table)
- [ ] Orders with immutable snapshots + frozen FX and transaction currency
- [ ] `order_status_histories` including `payment_failed`
- [ ] Customer order list (authenticated); order confirmation page
- [ ] Admin order list + status update (`processing` → `shipped` → `delivered`)

### Payments

- [ ] Payment provider (team choice) via `PaymentGatewayContract` — must support selected transaction currencies
- [ ] `payments`, `payment_provider_events`
- [ ] Redirect/hosted checkout; verified webhooks; idempotency
- [ ] **`payment_failed`** + retry on same order while reservation active
- [ ] **No** stored card data

### Delivery

- [ ] Blade storefront: home, PLP, PDP, cart, checkout, confirmation
- [ ] Blade admin: products, variants, translations, stock, rates, orders, store settings
- [ ] API v1 mirrors key Actions where agreed (see open decision)

### Operations

- [ ] Flat shipping from `store_settings` (USD baseline, converted at checkout)
- [ ] Tax calculation **disabled** until jurisdiction confirmed (schema ready)
- [ ] Order number generation with prefix

---

## Tier 2 — Important shortly after MVP

- [ ] `shipping_methods` table
- [ ] `refunds` table and admin flow
- [ ] `category_translations`
- [ ] Guest order lookup (magic link or email OTP)
- [ ] `LinkCustomerToUserAction`
- [ ] Low stock admin indicator
- [ ] Product soft delete handling on storefront
- [ ] API Resources parity
- [ ] Email notifications via Events
- [ ] `checkout_sessions` **only if** multi-step draft checkout is required

---

## Tier 3 — Future enhancements

- [ ] Coupons / promotions
- [ ] Multi-zone tax, VAT IDs, invoices (after jurisdiction confirmed)
- [ ] Advanced shipping
- [ ] `product_attributes` normalized schema
- [ ] Wishlists, reviews, recommendations
- [ ] Partial shipments, returns/RMA
- [ ] Spatie roles/permissions
- [ ] Automated FX feeds
- [ ] ERP/export, Redis guest cart

---

## Schema elements by tier

| Table / feature | Tier |
| --- | --- |
| Core catalog + `product_translations` | 1 |
| `store_locales`, `store_themes` | 1 |
| `inventory_*` | 1 |
| `carts`, `cart_items` | 1 |
| `orders`, `order_items`, `order_status_histories` | 1 |
| `payments`, `payment_provider_events` | 1 |
| `customers`, `customer_addresses` | 1 |
| `store_settings`, `currencies`, `currency_exchange_rates` | 1 |
| Multi-currency display + checkout | 1 |
| `checkout_sessions` | 2 (conditional) |
| `refunds`, `shipping_methods`, `category_translations` | 2 |

---

## Confirmed decisions (closed)

| Decision | Resolution |
| --- | --- |
| Multi-currency | USD base; USD/EGP/KWD; manual rates; immutable snapshots |
| `minor_units` | USD 2, EGP 2, KWD 3 from `currencies` |
| FX rounding | Decimal math; half-up per component |
| Checkout | Strategy C; no provider HTTP inside DB transactions |
| `checkout_sessions` | **Not in MVP** |
| Failed payment | `payment_failed`; retry while reservation active |
| Publish rule | Both `en` and `ar` product translations required |
| Languages / themes | Store owns config; Catalog owns product translations |

---

## Open decisions (require approval before implementation)

| # | Topic | Notes |
| --- | --- | --- |
| 1 | Payment provider | Must support charge currencies offered at checkout |
| 2 | API auth | Session vs Sanctum |
| 3 | Tier 1 API breadth | Web-first vs full API checkout |
| 4 | Tax jurisdiction | Business input — blocks enabling `tax_enabled` |
| 5 | Fulfillment statuses | Confirm operational workflow |
| 6 | `users.role` vs permission package | Admin granularity |

---

## Related documents

- [DATABASE_DESIGN.md](DATABASE_DESIGN.md)
- [DATABASE_DESIGN_APPROVAL_REPORT.md](DATABASE_DESIGN_APPROVAL_REPORT.md)
- [DOMAIN_DATA_OWNERSHIP.md](DOMAIN_DATA_OWNERSHIP.md)
- [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md)
