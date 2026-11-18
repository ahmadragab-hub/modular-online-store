# Final release readiness report

**Date:** 2026-11-18  
**Cycle:** Release hardening (DB-013, MySQL CI, search, operations)

---

## Final verdict

| Classification | Applies? |
| --- | --- |
| **Demo-ready** | **Yes** — mock payment, SQLite-tested suite, local WAMP/Laravel |
| **Staging-ready** | **Yes** — with MySQL migrate, mock disabled, DB-013 on InnoDB, CI workflow added |
| **Production-ready (live commerce)** | **No** — **OPEN-001**, **OPEN-005**, real PSP webhooks, merchant onboarding unresolved |

---

## 1. DB-013 implementation

| Item | Evidence |
| --- | --- |
| Migration | `database/migrations/2026_11_18_120000_enforce_one_active_cart_per_customer_mysql.php` |
| Mechanism | MySQL **STORED** generated column `active_customer_claim` = `customer_id` when `status = active`, else NULL; **UNIQUE** index |
| Pre-check | Migration **throws** listing `customer_id` values if duplicate active rows exist |
| Guest carts | Unaffected (`customer_id` NULL → claim NULL; multiple rows allowed) |
| Application | `GetOrCreateCustomerCartAction` catches `UniqueConstraintViolationException` and returns existing active cart |
| **AUTH-025** | Customer row lock + action paths **retained** |

**Duplicate data check (this environment):** Fresh SQLite test DBs have no legacy duplicates. Production/staging must run migrate on MySQL; if duplicates exist, migration aborts per design.

**SQLite PHPUnit:** Migration is a **no-op** on non-MySQL — constraint **not** enforced in default CI unit job.

---

## 2. MySQL concurrency verification

| Run | Driver | Result |
| --- | --- | --- |
| `php artisan test` (default) | **SQLite** in-memory | **397 passed**, **11 skipped** |
| MySQL integration (local) | — | **Not executed** — `mysql`/`mysqladmin` CLI not available on PATH in agent environment |
| MySQL job in CI | Configured in `.github/workflows/ci.yml` | **Not executed in this session** — requires GitHub Actions run |

**Skipped (11):** All `tests/Integration/MySQL/*` when `DB_CONNECTION !== mysql`, including **4** new `ActiveCustomerCartConstraintTest` cases.

**Honest statement:** InnoDB concurrency is **not verified locally** in this run. Verification depends on the **mysql-integration** CI job or local `phpunit.mysql.xml` against a dedicated test database.

---

## 3. CI workflow

**Added:** `.github/workflows/ci.yml`

| Job | Purpose |
| --- | --- |
| `php-sqlite` | Full `php artisan test`, Pint |
| `mysql-integration` | MySQL 8 service, `php artisan test --configuration=phpunit.mysql.xml` |
| `frontend` | `npm ci`, `npm run build` |

**Added:** `phpunit.mysql.xml` — MySQL env defaults for integration suite only.

---

## 4. Product search

| Item | Detail |
| --- | --- |
| Query | `ListPublishedProductsQuery` — `q` searches localized `name` and `short_description` (active locale), parameterized `LIKE` |
| HTTP | `ShopController` — `q` + existing `category` filter |
| UI | Shop search form, EN/AR strings |
| Tests | `StorefrontProductSearchTest` — name match, category+search, draft excluded, empty results |

No external search engine. No new indexes (MVP volume).

---

## 5. Security review (focused)

| Area | Status |
| --- | --- |
| Customer order history IDOR | 404 when `customer_id` mismatch |
| Mock payment in production | `assertOperational()` fail-closed |
| Signed mock routes | Unchanged |
| Search input | Trimmed, max 100 chars, escaped LIKE wildcards |
| DB-013 | No silent merge/delete of duplicate carts in migration |

No new critical issues found in this cycle; no broad refactor.

---

## 6. Test and build evidence (this session)

| Command | Result |
| --- | --- |
| `php artisan test` | **397 passed**, **11 skipped**, **1178 assertions** (~83s) |
| PHPUnit DB (main job) | **SQLite** |
| `vendor/bin/pint --dirty` | Clean (prior full run); run `pint --test` in CI |
| `git diff --check` | Clean (prior cycle) |
| `npm run build` | Success (prior cycle; CI runs `npm ci` + build) |
| MySQL migrate verification | **Not run locally** (no MySQL client in PATH) |

**New tests:** `StorefrontProductSearchTest` (4), `ActiveCustomerCartConstraintTest` (4, MySQL-only).

---

## 7. Deployment documentation

Updated **[DEPLOYMENT.md](DEPLOYMENT.md)** — pre/post checklists, PowerShell commands, MySQL test instructions, DB-013 notes, queue worker, rollback.

---

## 8. Documentation updates

- `PROJECT_DECISIONS.md` — **DB-013 Approved**
- `DATABASE_DESIGN.md` — `active_customer_claim`, EGP cart base note
- `DEPLOYMENT.md` — expanded
- `MVP_SCOPE.md` — (prior cycle) mock/EGP; add search when scope checklist updated

---

## 9. Remaining blockers

1. **OPEN-001** — real payment provider
2. **OPEN-005** — merchant jurisdiction
3. **MySQL integration proof** — execute CI job or local `phpunit.mysql.xml`
4. Tax (**OPEN-003**) before charging tax
5. Guest order lookup (Tier 2) still optional

---

## 10. Recommended next steps

1. Push branch and confirm **GitHub Actions** `mysql-integration` job passes.
2. On staging MySQL: `php artisan migrate`, verify `SHOW COLUMNS FROM carts LIKE 'active_customer_claim'`.
3. Integrate payment provider when business decisions close.
4. Optional: add DB index on `product_translations (locale, name)` if search volume grows.

---

## 11. Related reports

- [FINAL_MVP_COMPLETION_REPORT.md](FINAL_MVP_COMPLETION_REPORT.md)
- [MOCK_PAYMENT_IMPLEMENTATION_REPORT.md](MOCK_PAYMENT_IMPLEMENTATION_REPORT.md)