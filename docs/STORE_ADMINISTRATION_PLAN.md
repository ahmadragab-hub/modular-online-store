# Store Administration — Architecture and workflow plan (Milestone 10)

**Status:** Planning (owner approval pending)  
**Last reviewed:** 2026-11-17  
**Branch context:** `feature/store-administration`  
**Authority:** [AGENTS.md](../AGENTS.md), [PROJECT_DECISIONS.md](PROJECT_DECISIONS.md), [DOMAIN_MAP.md](DOMAIN_MAP.md)

---

## 1. Purpose

Deliver a **secure Blade admin area** so the store owner can operate the single-store fashion MVP: catalog, inventory, orders, customers, exchange rates, and store settings—without redesigning storefront checkout, Strategy C placement, or inventory reservation lifecycles already implemented.

---

## 2. Current technical baseline (repository facts)

| Layer | State |
| --- | --- |
| **Admin HTTP** | None — no `app/Http/Controllers/Web/Admin/`, no admin routes, no admin layouts/views, no Policies |
| **Identity** | `User` with `role` (`admin` \| `customer`), `is_active`; customer auth via `LoginCustomerAction` / `RegisterCustomerAction`; **admin cannot use storefront login** |
| **Global auth redirects** | `bootstrap/app.php`: `redirectGuestsTo` → customer login; `redirectUsersTo` → storefront home ([PROJECT_DECISIONS.md](PROJECT_DECISIONS.md) notes admin separation required) |
| **Storefront** | Shop, PDP, cart, checkout (`PlaceOrderAction`), customer accounts (Milestone 9) |
| **Domain models** | Tier-1 schema complete (catalog, inventory, orders, payments, customers, store) |
| **Domain Actions (implemented)** | Checkout, cart, reservation commit/release/expire, customer profile/addresses — **no** catalog CRUD, **no** `AdjustStockAction`, **no** order transition/cancel Actions |
| **Queries (read)** | Catalog listing/detail, variant for cart, inventory availability, store FX |
| **Payments provider** | Deferred — records only at checkout; no gateway HTTP |

Admin features **reuse** existing domain rules via **new owning-domain Actions and Queries**, not duplicated controller logic.

---

## 3. Admin authentication boundary (design)

### 3.1 Route surface

- **`routes/admin.php`** (or `web.php` prefixed group) registered in `bootstrap/app.php`.
- Prefix: `/admin`, route names `admin.*`.
- Middleware stack (minimum):
  - `web` (session, CSRF)
  - `auth`
  - **`admin`** — new `EnsureAdminUser` (mirror `EnsureCustomerUser`): `role === admin` && `is_active`
  - Throttle login posts
- **ADMIN-008 (Approved):** Admin strings in `lang/en/admin.php` and `lang/ar/admin.php`; support RTL/LTR. Do not attach storefront-only preference middleware unless admin explicitly reuses the same theme/locale Actions.

### 3.2 Separate login flow

- `LoginAdminController` + `LoginAdminRequest` under `Http/Controllers/Web/Admin/Auth/`.
- `LoginAdminAction` (Identity) — symmetric to customer login but **rejects `customer` role**.
- Guest routes: `GET/POST /admin/login`; logout `POST /admin/logout`.
- **Never** reuse `storefront.login.*` for staff.

### 3.3 Global redirect interaction

- Replace naive global defaults when admin ships:
  - `redirectGuestsTo`: only for routes using default `auth` middleware **without** admin prefix — use **route middleware groups** so `/admin/*` unauthenticated → `admin.login`, storefront → `storefront.login`.
  - `redirectUsersTo` (authenticated guest hitting guest-only route): **role-aware** — admin → `admin.dashboard`, customer → `storefront.home` (minimal helper or framework `Authenticate` customization).
- Customer and admin sessions share the **`web` guard** (per AUTH-001 / OPEN-002 scope); separation is **route + middleware + login endpoints**, not a second guard for MVP unless owner approves ADMIN-002.

### 3.4 Admin account provisioning

- **No self-registration.**
- Proposed: Artisan command `admin:create-user` and/or documented seeder for first admin (see **ADMIN-001** Open).
- Password policy aligned with customer auth (hashed, server-side).

### 3.5 Authorization model (MVP)

- **ADMIN-003 (Approved):** MVP requires `users.role === admin` and `is_active`. **OPEN-004** remains Open for a future permission package.
- Optional thin `AdminPolicy` or gate `accessAdminPanel` for future expansion.

---

## 4. HTTP layout and domain ownership

```
app/Http/
  Controllers/Web/Admin/
    Auth/
    DashboardController.php
    Catalog/   (categories, products, variants, images)
    Inventory/
    Orders/
    Customers/
    Store/     (settings, exchange rates)
  Middleware/EnsureAdminUser.php
  Requests/Web/Admin/...
resources/views/
  layouts/admin.blade.php
  admin/...
lang/{en,ar}/admin.php
```

| Capability | Owning domain | New work (indicative) |
| --- | --- | --- |
| Admin login | Identity | `LoginAdminAction`, admin auth controllers |
| Dashboard metrics | Store + Orders + Inventory | Read Queries (no new tables) |
| Category CRUD | Catalog | Actions + validation (publish rules N/A for categories) |
| Product / variant / image CRUD | Catalog | Actions; publish requires en+ar translations (CAT rules) |
| Stock view / adjust | Inventory | `AdjustStockAction`, `InitializeVariantStockAction`, movement audit |
| Order list / detail / status | Orders | `ListOrdersForAdminQuery`, `TransitionOrderStatusAction`, `CancelOrderAction` |
| Customer list / detail | Customers | Read Queries; optional `DeactivateCustomerUserAction` (Identity) |
| FX rates | Store | `CreateExchangeRateAction` (append-only rows) |
| Store settings | Store | `UpdateStoreSettingsAction` (singleton id 1) |

Controllers: validate → authorize → call Action/Query → Blade/redirect.

---

## 5. Critical workflows (preserve existing behavior)

### 5.1 Catalog publish

- **Approved:** both `en` and `ar` `product_translations` required to publish ([MVP_SCOPE.md](MVP_SCOPE.md)).
- Admin publish Action validates translations, variant SKU uniqueness, USD prices on variants.
- Product **soft delete** / archive — avoid hard delete when orders reference variants (proposed **ADMIN-004**).

### 5.2 Inventory adjustment vs reservations

- Available qty = `on_hand - reserved` (existing `GetAvailableQuantityForVariantsQuery`).
- Adjustments write `inventory_movements` with `created_by_user_id`, types `adjustment_in` / `adjustment_out`.
- **Must not** break active reservations: proposed rule — reject adjustment that would make `on_hand < quantity_reserved` or make **available** negative (**ADMIN-005**).
- Adjustments **do not** mutate reservation rows.

### 5.3 Order management (**ADMIN-006** Approved)

- **Read-only payments** in admin (**ADMIN-007**); only verified provider success moves orders to `processing`.
- **Fulfillment:** admin-only `processing` → `shipped` → `delivered` via `TransitionOrderStatusAction` + `order_status_histories` (`actor_user_id`).
- **Cancel:** admin (or customer) may cancel **only** from `pending_payment` or `payment_failed` — `CancelOrderAction`, release **active** reservations, `cancelled_at`. **No** MVP cancel from `processing`, `shipped`, or `delivered`.
- **System expiry:** must coordinate reservation expiry with order `cancelled` (see **ADMIN-006** §F — not fully implemented in `ExpireReservationsAction` alone).
- **Late payment after cancel:** reconciliation only; no resurrect, no commit.
- **`refunded`:** not in MVP admin transition workflow.
- Order line amounts and addresses are **snapshots** — admin UI read-only.

### 5.4 Exchange rates

- **CUR-004:** Manual rates; append new row with `effective_from`; `ResolveCurrentExchangeRateQuery` already used at checkout.
- Historical orders keep frozen `exchange_rate` — never update order rows when rates change.

### 5.5 Customers

- List registered + guest customers; show addresses and order history (read).
- **No** arbitrary merge/delete of customer rows; **no** editing customer passwords from admin in MVP unless **ADMIN-009** approved.

---

## 6. Security controls (minimum)

| Risk | Mitigation |
| --- | --- |
| Customer accessing admin | `EnsureAdminUser`; admin login rejects customers; feature tests |
| Admin accessing customer account routes | Already 403; admins use admin URLs only |
| Shared session guard confusion | Role-aware redirects; explicit logout per area |
| Mass assignment | Form Requests + explicit `fill()` in Actions; never bind `request()->all()` on models |
| Stock integrity | Transactions + `lockForUpdate` on `inventory_levels` in `AdjustStockAction` |
| Invalid order transitions | Single `TransitionOrderStatusAction` with allowed matrix |
| Destructive actions | POST + CSRF; confirm in UI for cancel/delete image |
| PII exposure | Mask guest emails in logs; no provider secrets in views |
| Audit | `inventory_movements`, `order_status_histories`, `currency_exchange_rates.created_by_user_id` |

---

## 7. Localization and UI

- **ADMIN-008 (Approved):** `lang/en/admin.php` and `lang/ar/admin.php` with RTL/LTR support in admin layout.
- Reuse CSS variables pattern from storefront where practical; separate `layouts/admin.blade.php`.
- **UX-003:** functional admin UI, not full redesign (UX-005 remains pre-production).

---

## 8. Testing strategy

| Area | Tests |
| --- | --- |
| Admin auth | Feature: login/logout/throttle; customer denied; inactive admin denied |
| Authorization | Feature: guest cannot access `/admin`; customer 403 on admin routes |
| Catalog Actions | Unit: publish rules, SKU uniqueness |
| Adjust stock | Unit + Feature; MySQL integration for `lockForUpdate` on adjust (optional, same pattern as `CheckoutConcurrencyTest`) |
| Order transition | Unit: allowed/denied transitions; cancel releases reservations (mock or integration) |
| Regression | Existing storefront/checkout tests remain green |

---

## 9. Database changes (Milestone 10)

**Default: none required** — tier-1 schema supports MVP admin.

Possible **optional** (owner decision only):

- Admin audit log table (if **ADMIN-010** requires more than existing movement/history tables) — **not assumed**.

**DB-013** (one active cart) unchanged and unrelated to admin milestone.

---

## 10. Dependencies and blockers

| Blocker | Impact |
| --- | --- |
| Expiry ↔ order cancel coordination (**ADMIN-006** §F) | Implementation alongside payment/expiry jobs — transactional |
| **ADMIN-001** Admin provisioning | **Approved** — Artisan bootstrap |
| **ADMIN-005** Adjustment vs reserved stock | **Approved** — inventory admin |
| **OPEN-004** Permission package | Still **Open**; MVP uses **ADMIN-003** simple role |
| **OPEN-001** Payment provider | Admin remains **read-only** for capture/refund; no fake “mark paid” |
| **OPEN-003** Tax | No tax admin UI; `tax_enabled` stays off |

**Non-blocking:** Customer storefront order history UI (deferred); payment HTTP integration (deferred).

---

## 11. Consolidated implementation sequence (internal)

1. **WP1 — Admin shell:** routes, middleware, layout, nav, login/logout, role-aware redirects, tests, seed/command for admin user (after **ADMIN-001**).
2. **WP2 — Store config:** settings edit, exchange rate create, tests.
3. **WP3 — Catalog:** categories, products, translations, variants, images, sizes/colors management, publish rules, tests.
4. **WP4 — Inventory:** list levels, `AdjustStockAction` + `InitializeVariantStockAction`, movement history in UI, tests.
5. **WP5 — Orders:** list/filter/show, status transitions, cancel + reservation release, payment read-only panel, tests.
6. **WP6 — Customers:** list/show, read-only addresses/orders, optional deactivate, tests.
7. **WP7 — Dashboard:** aggregate queries wired to WP5–4 data, tests.
8. **WP8 — Documentation + decision log** updates on completion.

One PR after owner approves plan and Open decisions.