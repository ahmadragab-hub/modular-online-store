# Local MySQL configuration and migration report

**Date:** 2026-11-18  
**Repository:** `modular-online-store`  
**Scope:** Local WAMP application database (not demo seeding).

## Executive summary

The **application** now runs against **MySQL** (`modular_online_store` on `127.0.0.1:3306`). All **14** migrations applied, including **DB-013** (`active_customer_claim` + unique index). **No SQLite database files** were present in the project; no data migration was required.

PHPUnit’s **default** suite still uses **SQLite in memory** by design (`phpunit.xml`). MySQL-specific integration tests run via `phpunit.mysql.xml` against **`modular_store_test`**.

---

## 1. Root cause of SQLite selection

| Layer | Driver | Cause |
| --- | --- | --- |
| **`.env` (local app)** | MySQL | Already configured before this task (`DB_CONNECTION=mysql`, `DB_DATABASE=modular_online_store`). The dev database **did not exist** on the server until created in this session. |
| **`.env.example`** | Was **sqlite** | Laravel skeleton default; misled new clones into SQLite for the **app**, not only tests. **Updated** to MySQL. |
| **`config/database.php`** | Default fallback was `sqlite` | When `DB_CONNECTION` missing, Laravel chose SQLite. **Updated** default fallback to `mysql`. |
| **`phpunit.xml`** | **sqlite** `:memory:` | Intentional fast automated tests; does **not** define the local application DB. |
| **`phpunit.mysql.xml`** | **mysql** `modular_store_test` | MySQL integration / concurrency tests only. |

There is **no** `.env.testing` file. No `database/database.sqlite` (or other `*.sqlite`) files were found in the repo.

---

## 2. Files changed

| File | Change |
| --- | --- |
| `.env.example` | MySQL as normal local app DB; notes on PHPUnit vs `modular_store_test` |
| `config/database.php` | `env('DB_CONNECTION', 'mysql')` |
| `database/migrations/2026_11_18_120000_enforce_one_active_cart_per_customer_mysql.php` | DB-013: **VIRTUAL** generated column (MySQL 9.1 compatibility) |
| `database/seeders/StoreSettingsSeeder.php` | `flat_shipping_amount` → `0.00` so checkout works on fresh MySQL after seed |
| `docs/DEPLOYMENT.md` | WAMP local dev section (app vs test DB) |
| `docs/DATABASE_DESIGN.md` | DB-013 column documented as VIRTUAL |
| `docs/PROJECT_DECISIONS.md` | DB-013 note: VIRTUAL on MySQL 9.x |

**Not changed:** Local `.env` (already MySQL). No commits, push, or branch switches (per request).

---

## 3. Final local MySQL configuration (no secrets)

| Setting | Value |
| --- | --- |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `modular_online_store` |
| `DB_USERNAME` | `root` |
| `DB_PASSWORD` | *(empty — typical WAMP default)* |

**Resolved application connection (after `php artisan config:clear`):**

- Driver: **mysql**
- Database: **modular_online_store**
- Server: **MySQL 9.1.0** (WAMP)

**Integration test database (PHPUnit only):**

- `modular_store_test` on the same host (created alongside dev DB).

### Database creation (performed this session)

The configured database `modular_online_store` was missing (`SQLSTATE[1049]`). Databases were created with:

```sql
CREATE DATABASE IF NOT EXISTS modular_online_store
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS modular_store_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

*(Use phpMyAdmin, MySQL Workbench, or any client if reproducing on another machine.)*

---

## 4. Migration results

Commands:

```text
php artisan config:clear
php artisan migrate:status   → 14 migrations, all Ran
php artisan migrate          → initial batch + DB-013
```

| Metric | Value |
| --- | --- |
| Tables in `modular_online_store` | **34** |
| Rows in `migrations` | **14** |
| Prior data | **None** (empty new database) |
| Destructive commands used | **None** (`migrate:fresh` / `db:wipe` not run) |

### DB-013 verification (MySQL)

Migration: `2026_11_18_120000_enforce_one_active_cart_per_customer_mysql.php`

| Check | Result |
| --- | --- |
| Column `active_customer_claim` | Present; `Extra`: **VIRTUAL GENERATED** |
| Unique index | `carts_active_customer_claim_unique` on `active_customer_claim` |
| STORED on MySQL 9.1 | **Fails** with errno **1215** when expression references `customer_id` (FK). **VIRTUAL** succeeds with identical uniqueness semantics. |

**MySQL integration tests (DB-013):** `Tests\Integration\MySQL\ActiveCustomerCartConstraintTest` — **4 passed**.

---

## 5. Test and build results (by database engine)

### SQLite — default `php artisan test` (`phpunit.xml`)

| Result | Detail |
| --- | --- |
| **397 passed** | |
| **11 skipped** | All `tests/Integration/MySQL/*` (expected when not on MySQL config) |
| Duration | ~123 s |

### MySQL — `php artisan test --configuration=phpunit.mysql.xml`

| Suite | Result |
| --- | --- |
| `ActiveCustomerCartConstraintTest` | **4 passed** |
| `CheckoutConcurrencyTest` | **4 passed** |
| `CustomerCartConcurrencyTest` | **1 passed** |
| `PaymentExpiryConcurrencyTest` | **1 failed**, **1 skipped** |

**Failure (remaining issue):** `test_payment_success_then_expiry_on_same_order_does_not_leave_inconsistent_state` throws `ExpiredInventoryReservationException` when committing an **already expired** reservation during payment success. The test assumes success can commit after manual `expires_at` backdating; current domain code rejects expired commits. This is a **test/product alignment** issue, not a MySQL connection problem.

### Tooling

| Tool | Result |
| --- | --- |
| `vendor/bin/pint --test` | **Passed** |
| `npm run build` | **Passed** |

---

## 6. SQLite vs MySQL — explicit distinction

| Concern | SQLite (PHPUnit default) | MySQL (local app + `phpunit.mysql.xml`) |
| --- | --- | --- |
| One active cart (DB-013) | Application logic only (**AUTH-025**) | DB unique claim + application logic |
| Row locking / concurrency | Not representative of production | Validated by MySQL integration tests |
| Schema parity | Migrations run; DB-013 migration **no-ops** on non-MySQL | Full InnoDB constraints including DB-013 |

**Do not** treat a green default PHPUnit run as proof of InnoDB concurrency or DB-013; run the MySQL configuration when verifying those.

---

## 7. Data migration

No SQLite application data existed. **No transfer** was performed or required.

If a developer had used SQLite locally in the past with data in `database/database.sqlite`, that file would need a **manual export/import** strategy; migrations alone do not move rows.

---

## 8. Remaining issues

1. **`PaymentExpiryConcurrencyTest`** — one failing test on MySQL (see §5). Review desired behavior when payment succeeds after reservation TTL (race with expiry job).
2. **`php artisan db:show`** on WAMP lists **all databases** on the server (hundreds of tables); use `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'modular_online_store'` for app scope.
3. **MySQL client** not on PATH locally; PDO/Artisan confirmed connectivity.
4. **Production target** remains MySQL **8.x** in docs; local WAMP runs **9.1.0** — DB-013 VIRTUAL column works on both; STORED variant may still work on 8.x where 9.x rejects it.

---

## 9. Next steps (out of scope for this task)

- **Not started:** Demo seeding (`DemoStoreSeeder`), expanded catalog, UI redesign (**UX-005**).
- **Recommended after this report:** `php artisan db:seed` with intentional seeders when demo data work resumes; optional `php artisan storage:link` for public assets.
- Fix or adjust `PaymentExpiryConcurrencyTest` before treating MySQL CI as fully green on payment/expiry races.

---

## 10. Quick verification commands (PowerShell)

```powershell
cd C:\wamp64\www\modular-online-store
php artisan config:clear
php artisan db:show
php artisan migrate:status
php artisan test
php artisan test --configuration=phpunit.mysql.xml
vendor\bin\pint --test
npm run build
```