# Project decision log

**Last reviewed:** 2026-11-16
**Repository:** modular-online-store
**Authority:** This log records **approved** product, business, architecture, and technical decisions. Detailed specifications remain in linked documents; this file records **what** was decided and **why**, and tracks changes over time.

## How to use this decision log

| Audience | Usage |
| --- | --- |
| **Project owner** | Approve or supersede entries; resolve **Open** and **Inferred** items. |
| **Developers & AI agents** | Read before work that affects behavior, schema, security, checkout, cart, currency, localization, or scope (see [AGENTS.md](../AGENTS.md)). |
| **Implementation reports** | Cite decision IDs (e.g. `CHECKOUT-003`, `AUTH-015`) when a task depends on or proposes changing a decision. |

**Status meanings**

| Status | Meaning |
| --- | --- |
| **Approved** | Authoritative; do not contradict without owner approval and a log update. |
| **Deferred** | Intentionally not in current scope; may ship later. |
| **Open** | Not decided; do not treat as requirements. |
| **Superseded** | Replaced by a newer entry; history preserved. |
| **Inferred** | Plausible from docs/code but not explicitly approved as a standalone decision. |

**When decisions change:** Mark the old entry **Superseded**, add the replacement with date and reason, implement in the same PR as the code change.

## Table of contents

- [N. Current project position](#n-current-project-position)
- [A. Project identity and product direction](#a-project-identity-and-product-direction)
- [B. Technology and architecture](#b-technology-and-architecture)
- [C. Database and data ownership](#c-database-and-data-ownership)
- [D. Currency and pricing](#d-currency-and-pricing)
- [E. Localization and appearance](#e-localization-and-appearance)
- [F. Catalog and inventory](#f-catalog-and-inventory)
- [G. Cart and checkout](#g-cart-and-checkout)
- [H. Orders and payments](#h-orders-and-payments)
- [I. Authentication and customer accounts (Milestone 9)](#i-authentication-and-customer-accounts-milestone-9)
- [J. UI/UX and frontend](#j-uiux-and-frontend)
- [K. Git and development workflow](#k-git-and-development-workflow)
- [L. Deferred and out-of-scope](#l-deferred-and-out-of-scope)
- [M. Decision register](#m-decision-register)

---

## N. Current project position

| Item | Verified state (2026-11-17) |
| --- | --- |
| **Stable branch** | `main` at `5b18d1f` (merge of PR #8) |
| **Current branch** | `feature/store-administration` (Milestone 10 planning) |
| **Milestone 9 status** | **Implemented** — customer auth, cart merge, profile, addresses (see branch history / merge state) |
| **Milestone 10 status** | **Planned** — store administration (see [STORE_ADMINISTRATION_PLAN.md](STORE_ADMINISTRATION_PLAN.md)) |

### Merged milestones (Git)

| Milestone | Merge commit | PR |
| --- | --- | --- |
| Customers models | `974f4a1` | #1 |
| Catalog models | `d9f1d19` | #2 |
| Inventory models | `ffcb207` | #3 |
| Cart models | `035183b` | #4 |
| Orders models | `82c2c97` | #5 |
| Payments models | `d184774` | #6 |
| Checkout workflows | `0043fcf` | #7 |
| Storefront shopping experience | `5b18d1f` | #8 |

### Milestone 9 implementation sequence (Approved)

1. **9.1–9.4** Customer accounts — **done** (consolidated)
2. **10** Store administration — **planned** (admin UI; domain Actions for catalog/inventory/order admin)

### Current implementation facts (not milestones)

- Storefront: guest cart, shop/PDP, checkout placement (`PlaceOrderAction`, inventory reservation, pending order/payment rows).
- Customer registration/login: implemented on feature work (**Milestone 9**); verify merge state on `main`.
- Admin UI: **not implemented** (Milestone 10 planning).
- Payment provider HTTP, webhooks, and post-commit `InitiatePaymentAction`: **not** wired on `main` (design approved; integration **Deferred**).
- API v1: health endpoint only.

---

## A. Project identity and product direction

| ID | Summary | Status |
| --- | --- | --- |
| PROD-001 | Single-store fashion e-commerce application | Approved |
| PROD-002 | Not multi-tenant SaaS; not a marketplace | Approved |
| PROD-003 | Commercial product: deployable per customer deployment (not shared-tenant SaaS) | Approved |
| PROD-004 | Domain-Oriented Modular Monolith on Laravel | Approved |
| PROD-005 | MVP prioritization in [MVP_SCOPE.md](MVP_SCOPE.md) | Approved |

**PROD-001 — Single-store fashion e-commerce**
- **Statement:** One logical store per deployment; fashion/clothing catalog semantics.
- **Rationale:** Documented across [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE_DESIGN.md](DATABASE_DESIGN.md), [MVP_SCOPE.md](MVP_SCOPE.md).
- **Date:** 2026-03-20 (foundation); ongoing.

**PROD-002 — No multi-tenant SaaS or marketplace**
- **Statement:** Do not build multi-tenant SaaS, marketplace vendors, or SaaS billing unless explicitly requested.
- **Rationale:** [AGENTS.md](../AGENTS.md) §13; [PROJECT_ROADMAP.md](PROJECT_ROADMAP.md) non-goals.
- **Date:** Date not established.

**PROD-003 — Per-customer commercial deployment**
- **Statement:** The application is a **commercial** product intended to be **managed and hosted per customer** (one store instance per deployment), distinct from a shared multi-tenant platform.
- **Rationale:** Product direction established with decision log; **not** independently documented in earlier architecture files (those emphasize single-store per **instance**, not hosting model).
- **Date:** 2026-11-16.

**PROD-004 — Modular monolith**
- **Statement:** Business logic in `app/Domains/`; HTTP in `app/Http/`; shared logic in `app/Shared/` only when genuinely cross-domain.
- **Rationale:** [ARCHITECTURE.md](ARCHITECTURE.md), [DOMAIN_MAP.md](DOMAIN_MAP.md).
- **Date:** Date not established.

---

## B. Technology and architecture

| Topic | Approved position | Primary references |
| --- | --- | --- |
| Runtime | Laravel **13**, PHP **8.3+** | [AGENTS.md](../AGENTS.md) |
| Database | **MySQL**, InnoDB tables in migrations | [DATABASE_DESIGN.md](DATABASE_DESIGN.md) |
| HTTP pattern | Controllers → Form Requests → Domain Actions/Queries | [AGENTS.md](../AGENTS.md), [ARCHITECTURE.md](ARCHITECTURE.md) |
| Storefront / admin | **Blade** in same Laravel app | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Frontend assets | **Vite** (+ Tailwind per project setup) | `package.json`, storefront layout |
| API | Optional later; **no REST requirement** for initial MVP breadth | [MVP_SCOPE.md](MVP_SCOPE.md), `routes/api.php` |
| Abstraction | No Repository-by-default; no unnecessary layers | [AGENTS.md](../AGENTS.md) |
| Shared business logic | API and web must reuse same Actions | [ARCHITECTURE.md](ARCHITECTURE.md) |

**ARCH-001 — Delivery channels share domain logic**
- **Status:** Approved
- **Statement:** Storefront, admin, and API controllers call the same domain Actions; differences stay in validation, authorization, and response mapping.
- **Date:** Date not established.

**ARCH-002 — Migrations location**
- **Status:** Approved ([PROJECT_ROADMAP.md](PROJECT_ROADMAP.md))
- **Statement:** Migrations remain in `database/migrations/`; migration path does not change domain ownership.
- **Date:** Date not established.

**ARCH-003 — Checkout payment sequence provider-dependent**
- **Status:** Approved
- **Statement:** Do **not** assume payment always before or after order creation globally; Strategy C is the MVP default; adjust per payment provider contract.
- **Rationale:** [AGENTS.md](../AGENTS.md), [ARCHITECTURE.md](ARCHITECTURE.md), [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md).
- **Date:** 2026-11-13 ([CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md)).

---

## C. Database and data ownership

**Approved model:** Domain ownership matrix in [DOMAIN_DATA_OWNERSHIP.md](DOMAIN_DATA_OWNERSHIP.md) and schema in [DATABASE_DESIGN.md](DATABASE_DESIGN.md). Tier-1 migrations: `database/migrations/2026_03_20_*`.

| ID | Topic | Status |
| --- | --- | --- |
| DB-001 | Domain owns its tables and mutation rules | Approved |
| DB-002 | `users` (Identity) vs `customers` (profile/addresses) | Approved |
| DB-003 | Guest customer: `customers.user_id` NULL | Approved |
| DB-004 | Registered: `customers.user_id` UNIQUE → `users.id` | Approved |
| DB-005 | `customers.email` UNIQUE | Approved |
| DB-006 | Catalog USD prices on variants; translations separate | Approved |
| DB-007 | Inventory levels, movements, reservations | Approved |
| DB-008 | Carts: `session_token`, optional `user_id` / `customer_id` | Approved |
| DB-009 | Orders: immutable snapshots, idempotency, `public_uuid` | Approved |
| DB-010 | Payments + `payment_provider_events`; no card storage | Approved |
| DB-011 | No `checkout_sessions` table in MVP | Approved |
| DB-012 | Cross-domain writes via owning domain Actions | Approved |
| DB-013 | How to enforce one-active-cart at the **database** layer | Open |

**DB-002 — Users vs Customers**
- **Statement:** `users` holds credentials and `role`; `customers` holds shopper profile, preferences, addresses, and links to orders. One registered customer row per user (`user_id` unique).
- **Evidence:** Models + `2026_03_20_100003_create_customers_tables.php`.
- **Date:** 2026-03-20.

**DB-008 — Cart identity keys**
- **Statement:** Guest carts use server-side `session_token`; registered carts may also set `user_id` and `customer_id`. Client must not be trusted for ownership.
- **Evidence:** [CART_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md](CART_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md), `GuestCartResolver`.
- **Date:** Date not established.

**One active cart — business rule vs database enforcement**
- **AUTH-023 (Approved):** At most one **active** cart per **customer** is an approved **business invariant** ([DOMAIN_DATA_OWNERSHIP.md](DOMAIN_DATA_OWNERSHIP.md), [DATABASE_DESIGN.md](DATABASE_DESIGN.md)). Guest carts follow one active cart per guest session identity (**CART-001**). Milestone 9 **AUTH-024–025** define operational handling when duplicates are detected.
- **DB-013 (Open):** The precise **database** enforcement mechanism (for example application-only logic, partial unique index, or other constraint) is **not** approved. Do not assume application-only enforcement or any specific schema constraint is the decided approach until the owner approves **DB-013**.

---

## D. Currency and pricing

Consolidated from [ARCHITECTURE.md](ARCHITECTURE.md), [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md), [MVP_SCOPE.md](MVP_SCOPE.md) “Confirmed decisions”.

| ID | Decision | Status |
| --- | --- | --- |
| CUR-001 | Base currency **USD** | Approved |
| CUR-002 | Supported: **USD, EGP, KWD** | Approved |
| CUR-003 | Variant/catalog prices stored in **USD** | Approved |
| CUR-004 | Manual admin exchange rates (USD → quote per 1 USD) | Approved |
| CUR-005 | Display currency ≠ transaction currency | Approved |
| CUR-006 | Missing FX rate blocks non-USD checkout | Approved |
| CUR-007 | Orders/payments freeze currency, amounts, `exchange_rate` | Approved |
| CUR-008 | `minor_units`: USD 2, EGP 2, KWD 3 | Approved |
| CUR-009 | Decimal arithmetic; avoid float money (BCMath / `MoneyMath`) | Approved |
| CUR-010 | Half-up rounding per component in transaction currency | Approved |

**CUR-009 — Decimal money**
- **Statement:** Use decimal types and shared decimal math (`app/Shared/Support/MoneyMath.php`), not floating-point, for money.
- **Date:** 2026-03-20 (checkout preparation implementation reports).

---

## E. Localization and appearance

| ID | Decision | Status |
| --- | --- | --- |
| LOC-001 | Locales **en** and **ar**; RTL for Arabic | Approved |
| LOC-002 | Themes **light** and **dark** | Approved |
| LOC-003 | Store owns locale/theme/currency **config**; Catalog owns product translations | Approved |
| LOC-004 | Publish requires **both** en and ar `product_translations` | Approved |
| LOC-005 | No silent fallback for missing required product translations on storefront | Approved |
| LOC-006 | UI chrome in `lang/*`; product copy in Catalog tables | Approved |

**References:** [LOCALIZATION_AND_THEMES.md](LOCALIZATION_AND_THEMES.md), [MVP_SCOPE.md](MVP_SCOPE.md).

**Current implementation fact:** `ApplyStorefrontPreferences` middleware + session/cookie persistence; Store reference tables seeded.

---

## F. Catalog and inventory

| ID | Decision | Status |
| --- | --- | --- |
| CAT-001 | Products, variants, sizes, colors, SKU uniqueness | Approved |
| CAT-002 | Only **published** products with active variants are sellable | Approved |
| CAT-003 | Publish requires en + ar translations | Approved |
| INV-001 | Stock per variant; movements audited | Approved |
| INV-002 | Reservations with TTL; expire via scheduled command | Approved |
| INV-003 | Stock checked on cart mutation and checkout preparation | Approved |
| INV-004 | No negative on-hand; reserved ≤ on-hand | Approved |

**Evidence:** [DATABASE_DESIGN.md](DATABASE_DESIGN.md), domain Actions (`AddCartItemAction`, `PrepareCheckoutAction`, `ReserveStockForCartAction`), inventory reports under `docs/INVENTORY_*`.

---

## G. Cart and checkout

### Approved checkout strategy (Strategy C)

| ID | Decision | Status |
| --- | --- | --- |
| CART-001 | Guest cart via server `session_token` in session | Approved |
| CART-002 | Cart lines store USD `unit_price_base` snapshots | Approved |
| CHECKOUT-001 | **Strategy C:** reserve stock + pending order + pending payment **inside DB transaction** | Approved |
| CHECKOUT-002 | **No** payment provider HTTP inside DB transaction | Approved |
| CHECKOUT-003 | Provider call **after** transaction commits | Approved |
| CHECKOUT-004 | Verified callbacks determine payment outcome | Approved |
| CHECKOUT-005 | Success: confirm order (`processing`), commit reservation | Approved |
| CHECKOUT-014 | Cart `converted` at order placement (not on payment success) | Approved |
| CHECKOUT-006 | Payment failure: order `payment_failed`; **do not** release reservation immediately | Approved |
| CHECKOUT-007 | Retry payment on same order while reservation active | Approved |
| CHECKOUT-008 | Release reservation on cancellation or TTL expiry | Approved |
| CHECKOUT-009 | Late success after expiry → reconciliation, not blind confirm | Approved |
| CHECKOUT-010 | `orders.idempotency_key` + payload fingerprint; conflict → 409 | Approved |
| CHECKOUT-011 | Guest checkout supported when `guest_checkout_enabled` | Approved |
| CHECKOUT-012 | Guest email cannot checkout as guest if email belongs to registered customer | Approved |

**References:** [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md), [CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md).

**Current implementation facts (`main`):**

- `PlaceOrderAction`, `PrepareCheckoutAction`, guest storefront checkout implemented.
- Post-commit payment initiation and webhook handling: **Deferred** (pending payment row created; no live gateway).
- `ResolveOrCreateCustomerAction` as separate class: **not** extracted; logic inline in `PlaceOrderAction::resolveCustomer()` (**Inferred** gap vs design doc naming).

**CHECKOUT-013 — Preserve checkout in Milestone 9**
- **Status:** Approved (Milestone 9)
- **Statement:** Do not redesign `PlaceOrderAction`, checkout idempotency, or reservation behavior for customer accounts.
- **Date:** 2026-11-16.

**CHECKOUT-014 — Cart conversion at order placement**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** The shopping cart transitions to **`converted`** inside the same database transaction as successful **`PlaceOrderAction`** (order placement), not on payment success. Retain current `PlaceOrderAction` behavior. Payment success must **not** require `MarkCartConvertedAction` for checkout to complete (handler may no-op if cart already `converted`).
- **References:** **ADMIN-006** (approved lifecycle); `PlaceOrderAction` implementation.
- **Consequences:** Customer starts a **new** cart after placing an order, even while payment is pending. Idempotent place-order replay must tolerate an already-`converted` cart linked via reservations.

---

## H. Orders and payments

| ID | Decision | Status |
| --- | --- | --- |
| ORD-001 | Orders store address JSON snapshots and frozen commercial fields | Approved |
| ORD-002 | Order items snapshot product name, SKU, variant label, prices | Approved |
| PAY-001 | Separate `payments` rows; `attempt_number`; payment idempotency keys | Approved |
| PAY-002 | `payment_provider_events` deduplicated | Approved |
| PAY-003 | No storage of sensitive card data in MVP | Approved |
| PAY-004 | Refunds table/workflows | Deferred (Tier 2) |

---

## I. Authentication and customer accounts (Milestone 9)

**Status:** All items below **Approved** on **2026-11-16** (project owner). **Not yet implemented** on `main` unless noted.

### Authentication

| ID | Decision |
| --- | --- |
| AUTH-001 | Use Laravel `web` session guard |
| AUTH-002 | Do not introduce Sanctum or a separate customer guard for this MVP |
| AUTH-003 | Customer registration, login, logout in scope |
| AUTH-004 | `role = customer` assigned **server-side** only |
| AUTH-005 | Inactive users cannot log in |
| AUTH-006 | Admin users cannot access customer account routes → **HTTP 403** |
| AUTH-007 | Regenerate session after login and registration |
| AUTH-008 | Logout invalidates session and regenerates CSRF token |

**Global authentication redirects (Milestone 9.1 implementation note)**
- `bootstrap/app.php` configures Laravel `redirectGuestsTo` and `redirectUsersTo` for the **storefront customer** login and home routes.
- When **admin authentication** is implemented, this configuration must be revisited so unauthenticated admin requests redirect to the **admin login**, not the customer login. Do not rely on the current global redirects for admin areas without that adjustment.

### Customer records

| ID | Decision |
| --- | --- |
| AUTH-009 | On register, if guest `Customer` exists with same email → **link** to new `User` |
| AUTH-010 | Preserve existing `Customer` row and order history |
| AUTH-011 | If customer already linked to another user → reject registration (email conflict) |
| AUTH-012 | No general merge of multiple `Customer` rows in Milestone 9 |
| AUTH-013 | Registration collects first and last name on `Customer` |
| AUTH-014 | Sync `User.name` from customer names |

### Guest and authenticated carts

| ID | Decision |
| --- | --- |
| AUTH-015 | After login/register, merge guest cart into customer's cart |
| AUTH-016 | Merge **into** existing customer cart (do not replace) |
| AUTH-017 | Same variant → combine quantities |
| AUTH-018 | Reuse existing cart mutation / stock validation |
| AUTH-019 | If merge would exceed stock → **cap** quantity and show warning |
| AUTH-020 | Merge must be **transactional**; no partial abandon of guest cart on failure |
| AUTH-021 | Preserve guest session token long enough for merge after auth session regenerate |
| AUTH-022 | After successful merge: guest cart **abandoned**, guest session token cleared |
| AUTH-023 | One active cart per customer (**approved business invariant**) |
| AUTH-024 | If multiple active carts for one customer → **STOP, REPORT**; do not silently fix |
| AUTH-025 | Interim one-active-cart enforcement (application-level) |

**AUTH-023 — One active cart per customer**
- **Status:** Approved.
- **Statement:** Each registered customer has at most one active cart under normal operations. This is a **business invariant**, distinct from how it is enforced in the database (**DB-013** remains **Open**).
- **Date:** 2026-11-16.

**AUTH-025 — Interim one-active-cart enforcement**
- **Status:** Approved.
- **Date:** 2026-11-17.
- **Statement:** **AUTH-023** remains the business invariant. Under normal operations, authenticated customer cart creation and resolution **must** go through `GetOrCreateCustomerCartAction`, which locks the owning `Customer` row inside a transaction before checking for or creating the customer's active cart. If multiple active carts already exist for a customer, the application **stops and reports** (**AUTH-024**); it must **not** automatically delete, abandon, or merge duplicate active carts as repair.
- **Limits:** This is an interim **application-level** guarantee for cooperating application paths only. It is **not** a database-level guarantee against writers that bypass the Action. **DB-013** remains **Open** — database-level enforcement is **not** approved or implemented. **DB-013** must be resolved before production hardening.
- **Verification:** Customer-cart concurrent creation has **not** been verified by a dedicated MySQL integration test that exercises true parallel connections.

### Profile and addresses

| ID | Decision |
| --- | --- |
| AUTH-026 | Customers can update profile |
| AUTH-027 | Addresses belong to authenticated user's `Customer` only |
| AUTH-028 | One default shipping and one default billing address per customer |
| AUTH-029 | First saved address becomes both defaults |
| AUTH-030 | Checkout without saved address allowed |
| AUTH-031 | Order address snapshots remain authoritative at checkout time |

### Checkout protection

| ID | Decision |
| --- | --- |
| AUTH-032 | No redesign of `PlaceOrderAction`, idempotency, reservations in M9 |
| AUTH-033 | Preserve guest checkout |
| AUTH-034 | Avoid schema changes if existing schema supports approved behavior |

**Current implementation fact:** `main` has **no** customer auth routes; guest-only cart resolver.

**Superseded / narrowed — API authentication (OPEN-002)**
- Milestone 9 **AUTH-002** approves session guard for **customer storefront MVP**.
- [MVP_SCOPE.md](MVP_SCOPE.md) still lists API auth (Sanctum vs session) as **Open** for future API breadth — not superseded for API v1 login.

---

## J. UI/UX and frontend

| ID | Decision | Status |
| --- | --- | --- |
| UX-001 | Blade storefront; Vite-built assets | Approved |
| UX-002 | Preserve en/ar, RTL/LTR, light/dark behavior | Approved |
| UX-003 | No full UI redesign during backend/domain milestones | Approved |
| UX-004 | Fix broken/unusable UI when touched in a feature | Approved |
| UX-005 | Comprehensive UI/UX polish milestone before production readiness | Approved |

**UX-005 — Pre-production UI/UX polish**
- **Status:** Approved (project owner).
- **Statement:** A dedicated comprehensive UI/UX polish milestone is planned before production readiness; backend milestones must not substitute for it (**UX-003**).
- **Date:** 2026-11-16.

---

## K. Git and development workflow

| ID | Decision | Status |
| --- | --- | --- |
| DEV-001 | Feature branches per milestone; `main` stable | Approved |
| DEV-002 | Verify with tests before merge | Approved |
| DEV-003 | Meaningful commits; **one coherent PR per milestone** (not per small subtask) | Approved |
| DEV-004 | Do not push/merge without owner instruction (agents) | Approved |
| DEV-005 | Sync local `main` with `origin/main` before next milestone | Approved |
| DEV-006 | Prefer Windows PowerShell for owner shell commands | Approved (owner preference) |

**DEV-003 — Milestone-level pull requests**
- **Status:** Approved (project owner).
- **Statement:** Make meaningful local commits as logical work completes. Push and open **one coherent pull request when a milestone is ready**. Do **not** create a separate PR for every small subtask within the same milestone.
- **Date:** 2026-11-16.

**References:** [GIT_WORKFLOW.md](GIT_WORKFLOW.md), [AGENTS.md](../AGENTS.md) user rules.

---

## L2. Store administration (Milestone 10)

**References:** [STORE_ADMINISTRATION_PLAN.md](STORE_ADMINISTRATION_PLAN.md), [STORE_ADMINISTRATION_SCOPE.md](STORE_ADMINISTRATION_SCOPE.md).

### Summary

| ID | Decision | Status | Blocks order admin? |
| --- | --- | --- | --- |
| ADMIN-001 | Admin provisioning via Artisan; no public registration | **Approved** | No (bootstrap only) |
| ADMIN-002 | Reuse `web` guard; separate admin routes, login/logout, middleware | **Approved** | No |
| ADMIN-003 | MVP: `role = admin` and `is_active`; no Spatie/complex RBAC (**OPEN-004** stays Open) | **Approved** | No |
| ADMIN-004 | Prefer archive/deactivate over hard delete when orders reference catalog | **Approved** | No |
| ADMIN-005 | Transactional stock adjust; never `on_hand` &lt; reserved; adjustments do not touch reservations | **Approved** | No |
| ADMIN-006 | Order status transition and cancellation matrix | **Approved** | No |
| ADMIN-007 | Payment information read-only in admin; no manual capture/success/refund | **Approved** | No |
| ADMIN-008 | `lang/en/admin.php` and `lang/ar/admin.php`; preserve EN/AR and RTL/LTR | **Approved** | No |
| ADMIN-009 | Customer administration read-only for MVP; defer user deactivation | **Approved** | No |
| ADMIN-010 | No separate audit table; use movements, status histories, rate creator metadata | **Approved** | No |

**OPEN (unchanged):** OPEN-001 (payment provider), OPEN-003 (tax), OPEN-004 (permission package — **not** resolved by ADMIN-003; future package choice remains Open).

---

**ADMIN-001 — Admin provisioning**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** Create the **first** admin user through an Artisan command (e.g. `admin:create-user`). **No** public admin registration. Non-production seeders may create test admins.
- **Consequences:** Production bootstrap depends on CLI access; document command in deployment notes.

**ADMIN-002 — Admin authentication boundary**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** Reuse the Laravel **`web`** session guard (**AUTH-001**). Separate **admin routes**, **admin login/logout**, and **admin middleware** from the storefront customer area.
- **Consequences:** Role-aware guest redirects required when admin ships (see AUTH note in §I).

**ADMIN-003 — MVP admin authorization**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** MVP administration requires `users.role = admin` and `users.is_active = true`. Do **not** introduce Spatie or a complex RBAC package for Milestone 10.
- **OPEN-004:** Remains **Open** for a future permission-package decision. ADMIN-003 does **not** approve a package-based model.

**ADMIN-004 — Catalog deletion vs archive**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** Prefer **archiving/deactivation** over hard deletion for catalog records referenced by orders. Hard-delete only records that are safe and unused.
- **Consequences:** Admin catalog Actions must guard destructive deletes; align with product/variant `status` and soft deletes where present.

**ADMIN-005 — Manual stock adjustments**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** Stock adjustments must run in a **database transaction** with appropriate row locking. Never reduce `quantity_on_hand` below **reserved** quantity. Manual adjustments **must not** modify reservation rows.
- **Consequences:** `AdjustStockAction` enforces invariants per **INV-004**; admin cannot “fix” reservations via adjust.

**ADMIN-006 — Order status transitions and cancellation**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** MVP order lifecycle, cancellation, payment interaction, and admin fulfillment transitions are fixed per the **approved matrix** below. Align `TransitionOrderStatusAction`, `CancelOrderAction`, payment success/failure Actions, and expiry orchestration with this matrix. **`refunded`** is outside the MVP transition workflow (**PAY-004** Deferred).
- **Rules (owner):**
  1. Allowed lifecycle: new order → `pending_payment`; verified failure → `payment_failed` (hold reservation); verified success → `processing` (commit reservation); retry success from `payment_failed` when reservation still `active`; cancel from `pending_payment` or `payment_failed` (customer, admin, or system expiry); admin `processing` → `shipped` → `delivered`.
  2. **No** cancellation from `processing`, `shipped`, or `delivered` in MVP.
  3. **No** manual admin payment success, capture, or refund (**ADMIN-007**).
  4. Only **verified provider** success may transition an order to `processing`.
  5. Late payment success after cancellation must **not** resurrect the order or commit stock; **reconciliation only** (**CHECKOUT-009**).
  6. Ordinary payment failure **retains** reservations for retry; only **explicit cancellation** or **expiry** releases them.
  7. Cart conversion at order placement (**CHECKOUT-014**).
- **Consequences:** Order admin UI and Actions implement this matrix; expiry implementation must close the gap documented below.

**ADMIN-007 — Read-only payments in admin**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** Payment information in the admin area is **read-only**. No manual payment success, capture, or refund actions until **OPEN-001** and payment integration are resolved (**PAY-004** Deferred).
- **Consequences:** Admin shows `payments` and attempts; status changes only via future verified provider flows.

**ADMIN-008 — Admin localization**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** Use separate `lang/en/admin.php` and `lang/ar/admin.php` translation files. Preserve English/Arabic content support and RTL/LTR theming for the admin UI consistent with project localization rules.
- **Consequences:** New translation namespace; admin layout must support direction/theme switching where applicable.

**ADMIN-009 — Customer administration (MVP)**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** Customer administration is **read-only** for MVP (list, search, profile, addresses, order history visibility). **Defer** customer **deactivation** (`users.is_active`) until login and order consequences are defined.
- **Consequences:** No deactivate toggle in Milestone 10 unless a follow-up decision approves it.

**ADMIN-010 — Audit trail (MVP)**
- **Status:** Approved (project owner).
- **Date:** 2026-11-17.
- **Statement:** No separate admin audit table in MVP. Rely on existing `inventory_movements`, `order_status_histories`, and `currency_exchange_rates.created_by_user_id`.
- **Consequences:** Important admin events must populate those fields where applicable (`actor_user_id`, `created_by_user_id`).

### ADMIN-006 — Approved transition matrix

Order status constants (MVP): `pending_payment`, `payment_failed`, `processing`, `shipped`, `delivered`, `cancelled`, `refunded` (`App\Domains\Orders\Models\Order`). **`refunded`** is **not** part of the MVP workflow below (**PAY-004** Deferred).

#### A. Allowed transitions

| From → To | Who | Reservation / stock | Notes |
| --- | --- | --- | --- |
| — → `pending_payment` | Customer — `PlaceOrderAction` | Reserve → `active` | Pending `payments` row; cart → `converted` (**CHECKOUT-014**) |
| `pending_payment` → `payment_failed` | Verified provider failure — `RecordPaymentFailureAction` | **Hold** — stay `active` (**CHECKOUT-006**) | Not ordinary HTTP abandon unless mapped to verified failure |
| `payment_failed` → `processing` | Verified provider success (retry) | **Commit** if reservations still `active` | Blocked if expired/cancelled |
| `pending_payment` → `processing` | Verified provider success only | **Commit**; `paid_at` | **ADMIN-007** — no admin override |
| `processing` → `shipped` | Admin — `TransitionOrderStatusAction` | None (stock committed) | |
| `shipped` → `delivered` | Admin | None | Terminal fulfillment |
| `pending_payment` → `cancelled` | Customer or admin — `CancelOrderAction` | **Release** active reservations | Cancel pending payments |
| `payment_failed` → `cancelled` | Customer or admin | **Release** active reservations | **Not** caused by ordinary payment failure alone |
| `pending_payment` / `payment_failed` → `cancelled` | System — expiry orchestration | Expire/release reservations | See **Expiry implementation gap** |
| `cancelled` → * | — | ✗ | Late success → reconciliation only (**CHECKOUT-009**) |

#### B. Forbidden in MVP

| Transition / action | Reason |
| --- | --- |
| `processing` / `shipped` / `delivered` → `cancelled` | **ADMIN-006** rule 2 |
| Fulfillment rollback (`delivered` → `shipped`, etc.) | No documented MVP path |
| `processing` ← admin or unverified source | Rule 4 — provider-verified success only |
| `*` → `refunded` | Outside MVP workflow |
| Admin manual capture / mark paid | **ADMIN-007** |

#### C. Cancellation by status (MVP)

| Status | Customer cancel | Admin cancel | System (expiry) |
| --- | --- | --- | --- |
| `pending_payment` | ✓ | ✓ | ✓ (when orchestrated) |
| `payment_failed` | ✓ | ✓ | ✓ (when orchestrated) |
| `processing`, `shipped`, `delivered` | ✗ | ✗ | ✗ |
| `cancelled`, `refunded` | ✗ | ✗ | ✗ |

#### D. Ordinary payment failure vs cancellation vs expiry

| Event | Order status | Reservations | Payments |
| --- | --- | --- | --- |
| **Ordinary payment failure** (verified) | → `payment_failed` | **Remain `active`** — retry allowed (**CHECKOUT-006**, **CHECKOUT-007**) | Attempt → `failed`; new attempt on retry |
| **Explicit cancel** (customer/admin) | → `cancelled` | **Release** active (`ReleaseReservationAction`) | Pending attempts → `cancelled` |
| **TTL expiry** (system) | → `cancelled` (required) | Expire/release per reservation | No new retries on same order |

Ordinary failure is **not** cancellation. Abandoning a redirect without a verified failure event does **not** by itself release stock unless product maps that case to verified failure or the customer/admin cancels.

#### E. Payment success vs expiry race (MVP requirements)

Verified payment success and reservation expiry **must not** both win. Handlers and expiry orchestration **must**:

1. Use **transactions** and **row locks** on the order and reservation rows (same discipline as reserve/commit).
2. **Success path:** Before `MarkOrderPaidAction` / `CommitReservationAction`, assert order ∈ (`pending_payment`, `payment_failed`), order **not** `cancelled`, and all order reservations required for commit are still **`active`** and unexpired.
3. **Expiry path:** When the last active reservation for an unpaid order is expired/released, transition order → `cancelled` with `cancelled_at` and status history in the **same transactional boundary** as inventory updates (or a single orchestrating Application/Checkout entry that calls Inventory then Orders in one transaction).
4. If success arrives after order is **`cancelled`** (expiry or manual): **do not** move order to `processing`; **do not** commit stock; record event; **reconciliation only** (**CHECKOUT-009**, rule 5).

#### F. Expiry implementation gap (Milestone 10 / payment follow-on)

**Approved behavior (E3 in [CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md)):** after TTL, expire reservations and cancel unpaid orders when no active reservations remain.

**Current code:** `ExpireReservationsAction` expires inventory reservations only; it does **not** invoke `CancelOrderAction` or update order status.

**Required in implementation milestone:** Close this gap **safely and transactionally** (or via a documented orchestrator used by `ExpireReservationsJob`) so orders do not remain `pending_payment` / `payment_failed` with zero active holds. Do not change **ADMIN-006** rules; implement coordination per §E.

**References:** [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md), [STORE_ADMINISTRATION_PLAN.md](STORE_ADMINISTRATION_PLAN.md) §5.3.

---

## L. Deferred and out-of-scope

**Deferred** (documented intent to ship later — **not** rejected)

| Area | Examples | Sources |
| --- | --- | --- |
| Payments | Provider integration, webhooks, `InitiatePaymentAction`, reconciliation jobs | [MVP_SCOPE.md](MVP_SCOPE.md), [CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md) |
| Admin | **Milestone 10** — admin login, catalog/inventory/order admin UI (was deferred; now planned) | [STORE_ADMINISTRATION_SCOPE.md](STORE_ADMINISTRATION_SCOPE.md) |
| Customer (post-M9) | Order history pages, password reset UI, email verification | [MVP_SCOPE.md](MVP_SCOPE.md), M9 scope boundary |
| Auth (API) | API authentication / Sanctum | [MVP_SCOPE.md](MVP_SCOPE.md) OPEN-002 |
| Tier 2 | Refunds, `shipping_methods`, guest order lookup, `LinkCustomerToUserAction` (full duplicate merge) | [MVP_SCOPE.md](MVP_SCOPE.md) |
| Tier 3 | Promotions, wishlist, reviews, recommendations, automated FX | [MVP_SCOPE.md](MVP_SCOPE.md) |
| Tax | Rules until jurisdiction confirmed | [MVP_SCOPE.md](MVP_SCOPE.md), `tax_enabled` default off |
| UI | Full redesign / polish milestone | UX-005 |

**Permanently rejected / non-goals (unless product changes)**

| Topic | Status |
| --- | --- |
| Multi-tenant SaaS, marketplace | [PROJECT_ROADMAP.md](PROJECT_ROADMAP.md), PROD-002 |
| `checkout_sessions` in MVP | DB-011 |
| Repository layer by default | [ARCHITECTURE.md](ARCHITECTURE.md) |

---

## M. Decision register

Compact index. See sections A–L for narrative. **Change history:** none superseded yet except note under OPEN-002 vs AUTH-002 (scoped).

| ID | Title | Status | Date |
| --- | --- | --- | --- |
| PROD-001 | Single-store fashion e-commerce | Approved | 2026-03-20 |
| PROD-002 | No SaaS / marketplace | Approved | not established |
| PROD-003 | Per-customer commercial deployment | Approved | 2026-11-16 |
| PROD-004 | Domain-Oriented Modular Monolith | Approved | not established |
| PROD-005 | MVP tiering | Approved | 2026-03-20 |
| ARCH-001 | Shared domain Actions across channels | Approved | not established |
| ARCH-002 | Global migrations directory | Approved | not established |
| ARCH-003 | Provider-dependent payment ordering | Approved | 2026-11-13 |
| DB-001 | Domain data ownership | Approved | 2026-03-20 |
| DB-002 | Users vs Customers split | Approved | 2026-03-20 |
| DB-003 | Guest customers | Approved | 2026-03-20 |
| DB-004 | Registered customer user_id unique | Approved | 2026-03-20 |
| DB-005 | Customer email unique | Approved | 2026-03-20 |
| DB-006 | Catalog pricing and translations | Approved | 2026-03-20 |
| DB-007 | Inventory model | Approved | 2026-03-20 |
| DB-008 | Cart identity keys | Approved | not established |
| DB-009 | Order snapshots and idempotency | Approved | 2026-03-20 |
| DB-010 | Payments and events | Approved | 2026-03-20 |
| DB-011 | No checkout_sessions MVP | Approved | 2026-11-13 |
| DB-012 | Cross-domain via Actions | Approved | not established |
| DB-013 | One-active-cart database enforcement mechanism | Open | not established |
| AUTH-023 | One active cart per customer (business invariant) | Approved | 2026-11-16 |
| AUTH-025 | Interim one-active-cart enforcement (application-level) | Approved | 2026-11-17 |
| CUR-001–010 | Currency and money rules | Approved | 2026-03-20 |
| LOC-001–006 | Locale, theme, translations | Approved | 2026-03-20 |
| CAT-001–003 | Catalog rules | Approved | 2026-03-20 |
| INV-001–004 | Inventory rules | Approved | 2026-03-20 |
| CART-001–002 | Cart rules | Approved | not established |
| CHECKOUT-001–012 | Strategy C and guest checkout | Approved | 2026-11-13 |
| CHECKOUT-013 | Preserve checkout in M9 | Approved | 2026-11-16 |
| CHECKOUT-014 | Cart `converted` at order placement | Approved | 2026-11-17 |
| ORD-001–002 | Order immutability | Approved | 2026-03-20 |
| PAY-001–003 | Payment records | Approved | 2026-03-20 |
| PAY-004 | Refunds | Deferred | not established |
| AUTH-001–034 | Milestone 9 customer auth & accounts | Approved | 2026-11-16 |
| UX-001–004 | Blade / localization / no redesign | Approved | not established |
| UX-005 | Pre-production UI polish milestone | Approved | 2026-11-16 |
| DEV-003 | Coherent milestone PRs; no PR per subtask | Approved | 2026-11-16 |
| DEV-001–002, DEV-004–005 | Other git workflow rules | Approved | not established |
| DEV-006 | PowerShell preference | Approved | 2026-11-16 |
| OPEN-001 | Payment provider choice | Open | [MVP_SCOPE.md](MVP_SCOPE.md) |
| OPEN-002 | API auth mechanism | Open | [MVP_SCOPE.md](MVP_SCOPE.md) |
| OPEN-003 | Tax jurisdiction | Open | [MVP_SCOPE.md](MVP_SCOPE.md) |
| OPEN-004 | `users.role` vs permission package | Open | [MVP_SCOPE.md](MVP_SCOPE.md) |
| ADMIN-001 | Admin provisioning (Artisan) | Approved | 2026-11-17 |
| ADMIN-002 | Admin `web` guard + separate routes | Approved | 2026-11-17 |
| ADMIN-003 | MVP simple `admin` role; OPEN-004 remains Open | Approved | 2026-11-17 |
| ADMIN-004 | Archive over hard delete (catalog) | Approved | 2026-11-17 |
| ADMIN-005 | Stock adjustment invariants | Approved | 2026-11-17 |
| ADMIN-006 | Order transition / cancel matrix | Approved | 2026-11-17 |
| ADMIN-007 | Read-only payments in admin | Approved | 2026-11-17 |
| ADMIN-008 | Admin `lang/*/admin.php` + RTL/LTR | Approved | 2026-11-17 |
| ADMIN-009 | Read-only customer admin; defer deactivate | Approved | 2026-11-17 |
| ADMIN-010 | No separate audit table (MVP) | Approved | 2026-11-17 |

### OPEN items (require owner approval)

| ID | Topic | Notes |
| --- | --- | --- |
| OPEN-001 | Payment provider | Must support transaction currencies at checkout |
| OPEN-002 | API authentication | Session vs Sanctum for API v1 — **customer web** uses session per AUTH-001 |
| OPEN-003 | Tax jurisdiction | Blocks enabling `tax_enabled` |
| OPEN-004 | Admin permission model | Simple `role` vs package — **MVP uses simple role per ADMIN-003**; package choice **still Open** |

---

## Related documents

- [AGENTS.md](../AGENTS.md) — agent and developer rules
- [ARCHITECTURE.md](ARCHITECTURE.md) — structure and patterns
- [DOMAIN_MAP.md](DOMAIN_MAP.md) — domain boundaries
- [DOMAIN_DATA_OWNERSHIP.md](DOMAIN_DATA_OWNERSHIP.md) — table ownership
- [DATABASE_DESIGN.md](DATABASE_DESIGN.md) — schema specification
- [BUSINESS_WORKFLOWS.md](BUSINESS_WORKFLOWS.md) — checkout and currency flows
- [CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md) — Strategy C design
- [MVP_SCOPE.md](MVP_SCOPE.md) — tiering and open decisions
- [LOCALIZATION_AND_THEMES.md](LOCALIZATION_AND_THEMES.md) — locale and theme ownership
- [GIT_WORKFLOW.md](GIT_WORKFLOW.md) — version control
- [PROJECT_ROADMAP.md](PROJECT_ROADMAP.md) — phases and non-goals