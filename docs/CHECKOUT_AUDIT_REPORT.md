# Checkout audit report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows` (analysis only; no code changes)
**Verdict:** **CHECKOUT DESIGN READY WITH OPEN DECISIONS**

---

## 1. Files and migrations inspected

### Project rules and design

| Document | Purpose |
| --- | --- |
| `AGENTS.md` | Architecture, checkout delegation, no provider HTTP in DB transactions |
| `docs/ARCHITECTURE.md` | Strategy C summary, idempotency, domain interaction |
| `docs/DOMAIN_MAP.md` | Checkout as orchestrator; cross-domain call map |
| `docs/DATABASE_DESIGN.md` | Tables, FX rounding, checkout without `checkout_sessions` |
| `docs/DOMAIN_DATA_OWNERSHIP.md` | Owned operations per domain; `FOR UPDATE` recommendation |
| `docs/BUSINESS_WORKFLOWS.md` | Strategy C sequence, retry rules, failure matrix |
| `docs/MVP_SCOPE.md` | Checkout/payment MVP checklist |

### Migrations (authoritative schema)

| Migration | Tables |
| --- | --- |
| `2026_03_20_100000_create_store_reference_tables.php` | `currencies`, `store_locales`, `store_themes`, `store_settings` |
| `2026_03_20_100002_create_currency_exchange_rates_table.php` | `currency_exchange_rates` |
| `2026_03_20_100003_create_customers_tables.php` | `customers`, `customer_addresses` |
| `2026_03_20_100004_create_catalog_foundation_tables.php` | catalog core + variants + translations |
| `2026_03_20_100005_create_inventory_levels_and_movements_tables.php` | `inventory_levels`, `inventory_movements` |
| `2026_03_20_100006_create_carts_tables.php` | `carts`, `cart_items` |
| `2026_03_20_100007_create_orders_tables.php` | `orders`, `order_items`, `order_status_histories` |
| `2026_03_20_100008_create_inventory_reservations_table.php` | `inventory_reservations` |
| `2026_03_20_100009_create_payments_tables.php` | `payments`, `payment_provider_events` |

### Eloquent models (verified)

All domains listed in the task brief have models under `app/Domains/{Domain}/Models/`. **Checkout** has only `app/Domains/Checkout/.gitkeep` — no Actions, DTOs, or Services.

### Tests

Domain model unit tests exist (Store through Payments). **No** checkout workflow, concurrency, or integration tests.

---

## 2. Existing schema and model capabilities

### Persistence available for checkout (MVP)

| Concern | Schema support | Model support |
| --- | --- | --- |
| Guest / registered customer | `customers.user_id` nullable; `orders.guest_email` | `Customer`, `Order` |
| Addresses at purchase | JSON on `orders.shipping_address`, `billing_address`; optional `customer_addresses` | `CustomerAddress` |
| Cart identity | `carts.session_token` (guest), `customer_id` / `user_id` | `Cart` statuses: `active`, `converted`, `abandoned` |
| Line USD snapshots | `cart_items.unit_price_base` (12,2) | `CartItem` |
| Display vs transaction currency | Cart: `display_currency_code`; Order: `currency_code` + frozen FX | `Cart`, `Order`, `Currency` |
| FX snapshot on order | `exchange_rate` (18,8), `currency_exchange_rate_id`, `effective_from` | `CurrencyExchangeRate` |
| Order idempotency | `orders.idempotency_key` UNIQUE (nullable column) | fillable on `Order` |
| Public payment correlation | `orders.public_uuid` UNIQUE | fillable on `Order` |
| Immutable lines | `order_items` — no `updated_at`; transaction decimals 19,4 | `OrderItem` |
| Status audit | `order_status_histories` | `OrderStatusHistory` |
| Stock hold | `inventory_reservations` + `inventory_levels.quantity_reserved` | `InventoryReservation`, `InventoryLevel` |
| Reservation TTL | `store_settings.reservation_ttl_minutes` (default 15) | `StoreSetting` |
| Reservation linkage | `cart_id`, `order_id` nullable FKs | relations on `Cart`, `Order`, `InventoryReservation` |
| Payment attempts | `payments.attempt_number` + UNIQUE `(order_id, attempt_number)` | `Payment` |
| Payment idempotency | `payments.idempotency_key` UNIQUE | `Payment` |
| Webhook idempotency | UNIQUE `(provider, provider_event_id)` | `PaymentProviderEvent` |
| Shipping baseline | `flat_shipping_amount`, `free_shipping_threshold` USD (12,2) | `StoreSetting` |
| Tax placeholder | `tax_enabled` default false; `tax_total` on order default 0 | `StoreSetting`, `Order` |

### Inventory concurrency primitives

- `inventory_levels`: one row per `product_variant_id` (UNIQUE), `quantity_on_hand`, `quantity_reserved`, `version` (optimistic).
- `inventory_movements`: append-only audit; types include `reservation_hold`, `reservation_release`, `sale_commit`.
- **Documented MVP strategy:** `SELECT ... FOR UPDATE` on `inventory_levels` inside `DB::transaction()` (`DOMAIN_DATA_OWNERSHIP.md`).

### Payments domain (models only)

- Status constants on `Payment` align with design: `pending`, `authorized`, `captured`, `failed`, `cancelled`.
- No `PaymentGatewayContract`, no Actions, no HTTP webhook controllers.

### Deliberate absences (confirmed)

- No `checkout_sessions` table.
- No `refunds` table (Tier 2).
- No payment provider columns beyond generic `provider`, `provider_payment_id`, `provider_checkout_url`, `metadata`.

---

## 3. Gaps (implementation not started)

| Gap | Impact |
| --- | --- |
| **No Checkout Actions** | Entire use case unimplemented |
| **No domain Actions** elsewhere for cart, inventory reserve/commit/release, orders create, payments initiate/webhook | Documented names exist only in docs |
| **No pricing/totals capability in Store** | FX lookup, half-up rounding, shipping conversion must be built (Query or small Service) |
| **`RefreshCartPricesAction`** | Cart can be stale vs catalog USD prices |
| **`ExpireReservationsJob`** | TTL expiry not automated |
| **Payment provider** | Undecided; blocks real redirect/webhook testing |
| **HTTP layer** | No checkout Form Requests, controllers, routes, policies for place order |
| **Order number generation** | Prefix in `store_settings`; no generator Action |
| **Guest order history** | MVP assumption: logged-in only (`BUSINESS_WORKFLOWS.md`) |
| **Reconciliation job** | Referenced for capture-without-commit failures; not specified in schema |

Models and migrations are **sufficient** to implement Strategy C; orchestration and cross-domain Actions are the missing work.

---

## 4. Documentation vs schema conflicts

**Migration vs `DATABASE_DESIGN.md`:** No checkout-related conflicts were found.

**Former cross-document conflicts (resolved 2026-11-13 on `feature/checkout-workflows`):**

| Topic | Resolution |
| --- | --- |
| Payment failure vs reservation release | `ARCHITECTURE.md` aligned with `BUSINESS_WORKFLOWS.md`: failure does **not** auto-release; retry while reservation **active**; release on **cancel** or **expiry**. |
| Place-order idempotency conflict | Reusing `orders.idempotency_key` with different payload → **HTTP 409** (`BUSINESS_WORKFLOWS.md`, `CHECKOUT_WORKFLOW_DESIGN.md`). |
| Late payment after reservation expiry | **Reconciliation only**—no auto order confirm or stock commit (`BUSINESS_WORKFLOWS.md`, `CHECKOUT_WORKFLOW_DESIGN.md`). |
| First `payments` row vs `InitiatePaymentAction` | **Place order** transaction creates attempt `1` pending row; **InitiatePaymentAction** calls provider after commit; **retries** add new `payments` rows on the same order (`CHECKOUT_WORKFLOW_DESIGN.md`). |
| `DOMAIN_DATA_OWNERSHIP.md` inventory lifecycle | Inventory ownership table and reservation lifecycle aligned with MVP: no auto-release on payment failure; release on cancel/expiry; late capture → reconciliation (`DOMAIN_DATA_OWNERSHIP.md`, 2026-11-13). |
| `ARCHITECTURE.md` / `BUSINESS_WORKFLOWS.md` reservation wording | Fashion table and stock lifecycle table aligned—no “release on payment failure” implications (2026-11-13). |

**Remaining documentation alignment (not blocking design):**

| Topic | Notes |
| --- | --- |
| `orders.idempotency_key` nullable in SQL | Application **must require** idempotency key on place-order HTTP for MVP. |

---

## 5. Recommended Checkout responsibilities

Checkout **coordinates** only. It does **not** own tables or long-lived invariants.

| Responsibility | Owning domain | Checkout role |
| --- | --- | --- |
| Cart ownership, active status, non-empty | Cart | Load cart via Action; pass cart id/context into `PlaceOrderAction` |
| Cart expiry | Cart | Reject if `expires_at` in past (if set) |
| Variant exists, product published, variant active | Catalog | Delegate validation Query/Action; enforce en+ar translations for publish rules at catalog layer |
| Authoritative USD variant price | Catalog | Refresh or compare at place order (via Cart refresh) |
| Available quantity math | Inventory | Delegate `GetAvailableQuantityQuery`; reserve via `ReserveStockAction` |
| Transaction currency validity | Store | `transaction_enabled` on `currencies`; current rate for non-USD |
| FX + rounding + shipping/tax totals | Store (data) + Checkout (orchestration) | Build `ComputeCheckoutTotals` using store settings; **never trust client totals** |
| Customer resolve / guest create | Customers | `ResolveOrCreateCustomerAction` |
| Address validation (HTTP shape) | Http Form Request | Checkout Action receives validated DTO |
| Pending order + line snapshots | Orders | `CreatePendingOrderAction` |
| Status history on place | Orders | Same Action or `RecordOrderStatusHistoryAction` |
| Reservation create + level update | Inventory | `ReserveStockAction` per line (locked) |
| Link reservation to order/cart | Inventory | Set `order_id`, optional `cart_id` |
| Initial pending payment row | Payments | `CreatePendingPaymentAction` (amount = `grand_total`, currency = order transaction currency) — callable from Checkout inside transaction |
| Idempotency (place order) | Checkout + Orders | Lookup by `orders.idempotency_key` before work |
| Provider HTTP | Payments | `InitiatePaymentAction` **after commit** |
| Webhook / return handling | Payments | `HandleProviderWebhookAction` → Orders + Inventory + Cart Actions in one DB transaction |
| Reservation expiry | Inventory (Job) | `ExpireReservationsJob` — Checkout not involved |
| Cart converted | Cart | `MarkCartConvertedAction` on successful payment only |

Checkout must **not** directly write `inventory_levels`, `orders` line fields, or `payments` provider fields without calling the owning Action.

---

## 6. Business decisions

### 6.1 Resolved in checkout documentation (MVP implementation may proceed)

| Topic | Approved rule |
| --- | --- |
| Payment failure and reservations | **No** automatic `ReleaseReservationAction` on decline/failure; retry on **same order** while reservation **active**. |
| Release reservations | **Customer/admin cancellation** or **reservation TTL expiry** (`ExpireReservationsJob`). |
| Place-order idempotency | Same `orders.idempotency_key` + equivalent payload → replay existing order; **different payload → HTTP 409 Conflict**. |
| Payment retries | **Same order**; each retry = new `payments` row (`attempt_number++`, new `payments.idempotency_key`); prior attempts retained. |
| Late payment success after expiry | Order already **cancelled**, stock released → **reconciliation only**; **no** auto-confirm order or commit stock. |
| Tax at checkout | Disabled until jurisdiction; `tax_total = 0` at MVP (`tax_enabled` default off). |
| Strategy C boundaries | No provider HTTP inside place-order DB transaction; no `checkout_sessions` in MVP. |

### 6.2 Still requiring production approval or runbook detail

| # | Decision | Notes |
| --- | --- | --- |
| 1 | **Payment provider** | Must support transaction currencies (USD, EGP, KWD). May affect adapter shape or Strategy variant. |
| 2 | **Reconciliation runbook** | *What to do* when provider captured after expiry is defined (reconciliation, no auto-fulfill); **how** (void/refund steps, SLAs) is ops/provider-specific—not checkout schema. |
| 3 | **Reservation expires while customer on provider page** | Order cancelled and stock released; provider should fail or void capture where possible—**provider-specific**. |
| 4 | **Customer-initiated cancel while `payment_failed`** | Cancellation releases reservations (documented); UI/HTTP surface not yet implemented. |
| 5 | **Max payment retry attempts** | Application policy only (not in schema). |
| 6 | **Free shipping threshold** | Compare USD subtotal to USD threshold, then convert shipping—confirm in Store Action tests during implementation. |
| 7 | **`placed_at`** | Recommend set at `pending_payment` creation; not yet stated in all workflow docs. |
| 8 | **Guest checkout disabled** | Enforce `guest_checkout_enabled` in HTTP when flag off. |

---

## 7. Implementation readiness verdict

| Area | Status |
| --- | --- |
| Database schema | **Ready** for Strategy C |
| Domain models | **Ready** (relationships support workflow) |
| Business workflow documentation | **Ready** (checkout policy aligned across `ARCHITECTURE.md`, `BUSINESS_WORKFLOWS.md`, `DOMAIN_DATA_OWNERSHIP.md`, `CHECKOUT_WORKFLOW_DESIGN.md`) |
| Checkout code | **Not started** |
| Payment integration | **Blocked on provider choice** for end-to-end production |
| Automated checkout tests | **Missing** |

**Overall:** **CHECKOUT DESIGN READY WITH OPEN DECISIONS** — schema and models support implementation; **checkout policy is documented**. Production hardening still depends on **payment provider choice** and **reconciliation runbooks** (§6.2).

---

## Related deliverable

Detailed workflow, transaction boundaries, and implementation plan: [CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md).
