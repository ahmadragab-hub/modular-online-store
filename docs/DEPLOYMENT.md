# Deployment readiness

Production uses **MySQL 8 (InnoDB)**. Mock payment must be **disabled** in production. **OPEN-001** is required before live payment acceptance.

## Requirements

| Component | Version |
| --- | --- |
| PHP | 8.3+ |
| Extensions | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml` |
| Composer | 2.x |
| Node.js | 18+ (build only) |
| MySQL | 8.x InnoDB |

## Pre-deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`
- [ ] `APP_URL` = HTTPS canonical URL
- [ ] MySQL database created with **least-privilege** user (DDL only during deploy role if you split roles)
- [ ] `PAYMENT_MOCK_ENABLED=false`
- [ ] `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` set for production (not `array`)
- [ ] `storage/` and `bootstrap/cache/` writable by PHP user
- [ ] TLS terminated; cookies secure
- [ ] Backups configured
- [ ] **No** `db:seed` on production unless intentional
- [ ] DB-013 migration applied on MySQL (`active_customer_claim` present)
- [ ] Admin user created per **ADMIN-001** (not committed in repo)

## Install and build (PowerShell)

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Environment reference (no secrets in repo)

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

PAYMENT_GATEWAY=mock
PAYMENT_MOCK_ENABLED=false
```

## Queue worker (if `QUEUE_CONNECTION=database`)

```powershell
php artisan queue:work --tries=3
```

Run under systemd, supervisord, or Windows Service on staging/production.

## Migration notes (DB-013)

Migration `2026_11_18_120000_enforce_one_active_cart_per_customer_mysql.php`:

- **MySQL only** — adds `active_customer_claim` + UNIQUE index
- **Fails** if duplicate active carts exist for one customer (reports `customer_id` values only)
- **Manual remediation** required before retry — do not auto-delete or merge in migration

## Rollback

1. Redeploy previous release artifact.
2. Run `php artisan migrate:rollback --step=1` only when the rollback step is understood (DB-013 down drops generated column on MySQL).

## Backup and restore

- Backup: `mysqldump` (schema + data) on a schedule.
- Restore: test on staging first; never restore production dumps into the PHPUnit test database.

## Post-deployment smoke tests

- [ ] `GET /api/v1/health` returns OK
- [ ] Storefront home and shop load; Vite assets present (`/build/manifest.json`)
- [ ] Admin login at `/admin/login`
- [ ] Guest cart add; registered customer login and account pages
- [ ] Checkout EGP path (mock **only** on non-production)
- [ ] Confirm mock payment URL returns 404/fail-closed when `APP_ENV=production` and mock disabled

## Local development (WAMP)

- Copy `.env.example` to `.env`; set `DB_CONNECTION=mysql` and `DB_DATABASE=modular_online_store` (create the empty database in MySQL first).
- **Application** must never use SQLite locally; `phpunit.xml` forces SQLite **only** for the default PHPUnit run.
- Keep **`modular_store_test`** separate for `phpunit.mysql.xml` (RefreshDatabase wipes it).

## Local / CI verification (PowerShell)

**Default suite (SQLite):**

```powershell
php artisan test
vendor\bin\pint --test
npm ci
npm run build
git diff --check
```

**MySQL integration** (dedicated database `modular_store_test` only):

```powershell
$env:DB_CONNECTION='mysql'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_DATABASE='modular_store_test'
$env:DB_USERNAME='root'
$env:DB_PASSWORD='your_password'
php artisan test --configuration=phpunit.mysql.xml
```

GitHub Actions runs the same MySQL job (see `.github/workflows/ci.yml`). **Passing locally on SQLite does not prove InnoDB concurrency** — only the MySQL job does.

## Health

- `GET /api/v1/health`