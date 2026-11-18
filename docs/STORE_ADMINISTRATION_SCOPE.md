# Store Administration — MVP implementation scope (Milestone 10)

**Status:** Planning  
**Date:** 2026-11-17

Companion: [STORE_ADMINISTRATION_PLAN.md](STORE_ADMINISTRATION_PLAN.md)

---

## In scope (MVP)

### A. Admin identity and access

- [ ] Dedicated admin login/logout (`/admin/login`, POST logout)
- [ ] `EnsureAdminUser` middleware on all admin routes except login
- [ ] Customers blocked from admin; admins blocked from customer account routes (existing)
- [ ] Login throttling, session regeneration on login, CSRF, inactive admin denied
- [ ] Role-aware `redirectGuestsTo` / `redirectUsersTo` behavior (no admin user sent to customer login)
- [ ] Admin user creation via approved mechanism (**ADMIN-001**)

### B. Dashboard

- [ ] Counts: orders by status (recent window or all-time)
- [ ] Recent orders list (paginated)
- [ ] Low-stock variants (threshold from store settings or fixed default)
- [ ] No external analytics, no charting library requirement beyond simple tables

### C. Catalog management

- [ ] Categories: CRUD, slug, parent, sort, active flag (English `name` on row per current schema)
- [ ] Products: CRUD draft/publish/archive, slug, soft delete awareness
- [ ] Product translations: **en** and **ar** required to publish
- [ ] Variants: SKU (unique), size/color, USD price, active/inactive
- [ ] Images: upload to disk path per `product_images` (sort order, alt text if column exists)
- [ ] Sizes/colors reference management (global tables)
- [ ] Server-side validation; duplicate SKU surfaced as field error

### D. Inventory

- [ ] Per-variant on-hand and reserved (read)
- [ ] Manual adjustment in/out with reason notes and `created_by_user_id`
- [ ] Movement history list (filter by variant)
- [ ] Initialize level when variant created (0 or configured default)
- [ ] Rules per **ADMIN-005** (no negative on-hand; respect reservations)

### E. Order management

- [ ] Paginated list, filters: status, date, order number, customer email
- [ ] Detail: snapshots, lines, totals in order currency, addresses JSON, customer link
- [ ] Payment attempts list (read-only statuses)
- [ ] Fulfillment: `processing` → `shipped` → `delivered` only (**ADMIN-006**)
- [ ] Cancel unpaid orders only (`pending_payment`, `payment_failed`); release active reservations (**ADMIN-006**)
- [ ] No cancel from `processing`+; read-only payments (**ADMIN-007**)

### F. Customer management

- [ ] Customer search/list (registered + guest)
- [ ] Detail: profile, addresses, order list (read)
- [ ] User `is_active` visibility (read-only); **no** customer deactivation in MVP (**ADMIN-009** Approved)
- [ ] No merge/delete customer, no password reset UI unless approved

### G. Currency and store configuration

- [ ] View/edit `store_settings` singleton (shipping, reservation TTL, guest checkout flag, order prefix, contact)
- [ ] Add USD→EGP and USD→KWD exchange rate rows (append-only semantics)
- [ ] **No** tax configuration UI while **OPEN-003** open

---

## Explicit exclusions

- Payment provider integration, webhooks, manual “mark paid” (**OPEN-001**)
- Refunds admin (**PAY-004** Deferred)
- API admin / Sanctum (**OPEN-002**)
- Multi-admin RBAC / Spatie (**OPEN-004** beyond simple role)
- Category translations table (Tier 2)
- Promotions, coupons, reviews (Tier 3)
- Customer-facing order history pages (separate backlog)
- Redesign of `PlaceOrderAction`, `PrepareCheckoutAction`, reservation Actions
- DB-013 schema work for one-active-cart
- Email notifications from admin events

---

## Work packages (single milestone PR)

| WP | Delivers |
| --- | --- |
| **1** | Admin auth shell, middleware, redirects, layout, tests |
| **2** | Store settings + exchange rates |
| **3** | Catalog admin (full MVP catalog surface) |
| **4** | Inventory admin |
| **5** | Orders admin |
| **6** | Customers admin (read-focused) |
| **7** | Dashboard wiring |

---

## Success criteria

- Store owner can publish bilingual products and manage stock without breaking checkout.
- Store owner can find orders, advance fulfillment, cancel per rules, see payment state read-only.
- All admin routes require active admin role.
- `php artisan test` green; new Feature coverage for admin auth and one critical Action per domain.
- Checkout and storefront regression tests unchanged in intent.