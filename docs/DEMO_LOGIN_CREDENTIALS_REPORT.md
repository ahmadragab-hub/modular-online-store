# Demo login credentials report

**Date:** 2026-11-19  
**Scope:** Local demo sign-in UX and account invariants (no auth rule changes).

## Documented accounts (login testing)

| Role | Email | Password |
| --- | --- | --- |
| **Customer (primary)** | `demo.customer1@example.local` | `DemoCustPass12!` |
| **Admin** | `demo-admin@example.local` | `DemoAdminPass12!` |

Additional demo customers (`demo.customer2@`, `demo.customer3@`) remain for order scenarios; same password. **Use customer 1** for the documented storefront login.

**URLs (local):**

- Customer: `/login`
- Admin: `/admin/login`

---

## Implementation

| Area | Change |
| --- | --- |
| `DemoUsersSeeder` | `PRIMARY_CUSTOMER_EMAIL` constant; `ensureDocumentedDemoAccountsRemainValid()` sets admin **active + role admin**, customer 1 **active + role customer** on each run (does not reset passwords) |
| `resources/views/shared/partials/demo-login-hints.blade.php` | Subtle hint block; gated by `app()->environment(['local', 'testing'])` |
| `storefront/auth/login.blade.php` | Hints under Sign in; email/password **prefilled** in `local` / `testing` only |
| `admin/auth/login.blade.php` | Same for admin credentials |
| `resources/css/app.css` | `.demo-login-hints` styling (works on storefront + admin login) |
| `lang/en|ar/storefront.php` | Labels “Demo Username” / “Demo Password” per requirement |
| `tests/Feature/Web/DemoLoginExperienceTest.php` | Hints visibility, production gating, login flows, admin 403 for customer |

**Production safety:** Hints render only in `local` and `testing`. No credentials in JS or config files.

---

## Demo data preserved

- All three customers still seeded; order idempotency keys unchanged (`DemoOrdersSeeder` still references customer 1/2/3).
- Re-seeding does **not** delete users or orders; passwords on existing accounts are **not** rotated.

---

## Verification

| Check | Method | Result |
| --- | --- | --- |
| Seeder idempotency | `php artisan db:seed --class=DemoStoreSeeder` × 2 on MySQL | **OK** (~3s then ~0.8s) |
| Customer login | `DemoLoginExperienceTest` | **Pass** |
| Admin login | `DemoLoginExperienceTest` | **Pass** |
| Customer → admin dashboard | HTTP 403 | **Pass** |
| Hints in testing | Response contains credentials | **Pass** |
| Hints in production env | `app['env'] = production` — no hint markup | **Pass** |
| Full PHPUnit (SQLite) | `php artisan test` | **402 passed**, 12 skipped |
| Pint | `vendor/bin/pint --dirty` | **Pass** |
| Frontend build | `npm run build` | **Pass** |

**Browser verification:** Not performed in this session (HTTP/feature tests only).

---

## Limitations

1. If a demo account password was changed manually in the DB, re-seeding will **not** restore it (by design).
2. `ensureDocumentedDemoAccountsRemainValid()` only normalizes role/active flags for the **two documented** emails.
3. Hints use English labels “Demo Username” / “Demo Password” even on Arabic storefront (credentials are ASCII).

---

## Quick manual check (owner)

1. Open `/login` — confirm hint block under Sign in.
2. Sign in as customer 1 — open `/account`.
3. Open `/admin/login` — confirm admin hint; sign in as demo admin.
4. As customer, confirm `/admin` returns forbidden.