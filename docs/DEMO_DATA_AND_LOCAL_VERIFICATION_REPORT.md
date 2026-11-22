# Demo data and local verification report

**Date:** 2026-11-18  
**Branch:** `main` (commit `9f128c6` baseline; **no git commit** in this milestone)  
**Database:** MySQL **9.1.0** → `modular_online_store`  
**Seeder command:** `php artisan db:seed --class=DemoStoreSeeder`

---

## 1. Existing data discovered (before seed)

| Item | State |
| --- | --- |
| Migrations | **14/14** applied (including DB-013) |
| Business data | **Empty** — schema only (no users, products, or orders) |
| SQLite files | None in repository |
| `public/storage` symlink | Created via `php artisan storage:link` when missing |

---

## 2. Files changed

| File | Purpose |
| --- | --- |
| `database/seeders/DemoStoreSeeder.php` | Orchestrates store, catalog, users, orders |
| `database/seeders/DemoUsersSeeder.php` | Demo admin + 3 customers (stable emails) |
| `database/seeders/DemoOrdersSeeder.php` | Order scenarios via domain Actions |
| `database/seeders/DemoCatalogSeeder.php` | **18** products, 6 categories (2 subcategories), EGP prices, expanded catalog |
| `storage/app/public/demo/catalog/placeholder.svg` | Local product image (public disk) |
| `tests/Feature/Database/DemoCatalogSeederTest.php` | Expect ≥ 18 published products |
| `docs/DEPLOYMENT.md` | Demo seed command reference |
| `docs/DEMO_DATA_AND_LOCAL_VERIFICATION_REPORT.md` | This report |

---

## 3. Seeder design

**Entry:** `DemoStoreSeeder` calls, in order:

1. `CurrencySeeder`, `StoreLocaleSeeder`, `StoreThemeSeeder`, `StoreSettingsSeeder`
2. Updates store row `id = 1` (name, contact — does not rotate credentials)
3. `DemoCatalogSeeder` — catalog + inventory (`firstOrCreate` levels; does **not** reset reserved/on_hand on re-run)
4. `DemoUsersSeeder` — creates users only if email absent (passwords unchanged on re-run)
5. `DemoOrdersSeeder` — one order per scenario keyed by **`idempotency_key`** prefix `demo-scenario-*`

**Safety**

- No `migrate:fresh`, `db:wipe`, truncate, or deletes of unrelated rows
- Demo SKUs: `DEMO-%`; demo order keys: `demo-scenario-*-v1`
- Orders created only through `PlaceOrderAction` + verified payment / failure Actions
- **No** manual payment capture or expired reservation commits

**Repeatability:** Second `db:seed --class=DemoStoreSeeder` completed in ~837 ms with no duplicate users/orders (verified).

---

## 4. Demo credentials (local only)

| Role | Email | Password |
| --- | --- | --- |
| Admin | `demo-admin@example.local` | `DemoAdminPass12!` |
| Customer 1 | `demo.customer1@example.local` | `DemoCustPass12!` |
| Customer 2 | `demo.customer2@example.local` | `DemoCustPass12!` |
| Customer 3 | `demo.customer3@example.local` | `DemoCustPass12!` |

Created via `CreateAdminUserAction` and `RegisterCustomerAction` (ADMIN-001). **Not** for production.

---

## 5. Catalog summary (after seed)

| Metric | Count |
| --- | --- |
| Demo categories (incl. subcategories) | **6** |
| Published products | **18** |
| Variants (`DEMO-%` SKUs) | **32** |
| Locales per product | EN + AR translations |
| Base currency (catalog prices) | **EGP** (realistic amounts, e.g. oxford shirt **980.00 EGP**) |
| USD FX | **0.02** USD per 1 EGP (`StoreSettingsSeeder` / `currency_exchange_rates`) |
| Shipping | Enabled, flat **0.00 EGP** (checkout allowed) |

**Subcategories:** `demo-menswear-tops`, `demo-womenswear-dresses` (under menswear / womenswear).

---

## 6. Images

| Check | Result |
| --- | --- |
| Path | `demo/catalog/placeholder.svg` on `public` disk |
| File on disk | **Yes** (`storage/app/public/...`) |
| Resolver sample URL | `http://localhost/modular-online-store/public/storage/demo/catalog/placeholder.svg` (uses `APP_URL`) |
| External placeholder services | **Not used** |

**Note:** Ensure `php artisan storage:link` so `/storage/...` is served under `public/`.

---

## 7. Order scenarios

| Idempotency key | Customer | Checkout currency | Order status | Payment / notes |
| --- | --- | --- | --- | --- |
| `demo-scenario-pending-payment-v1` | customer1 | EGP | `pending_payment` | Pending payment row |
| `demo-scenario-payment-failed-v1` | customer2 | EGP | `payment_failed` | `RecordPaymentFailureAction` — **reservation retained** |
| `demo-scenario-processing-v1` | customer3 | USD | `processing` | `CompleteVerifiedPaymentSuccessAction` |
| `demo-scenario-shipped-v1` | customer1 | EGP | `shipped` | Paid + `TransitionOrderStatusAction` (admin) |
| `demo-scenario-delivered-v1` | customer2 | EGP | `delivered` | Paid + shipped → delivered transitions |

**Not seeded:** Cancelled / expired-order late-payment cases (would require destructive or misleading state). Documented per CHECKOUT-009 — use existing tests and payment expiry integration tests instead.

---

## 8. Inventory

| Check | Result |
| --- | --- |
| Rows with `quantity_reserved > quantity_on_hand` | **0** |
| Demo orders | Hold stock via normal reservation workflow (`quantity_reserved` increased on place order) |

---

## 9. EGP / USD checkout (manual)

**Prerequisites:** `PAYMENT_MOCK_ENABLED=true` in local `.env` (default for dev).

1. **EGP:** Browse shop → add variant (e.g. `DEMO-OXF-M-WHT`) → checkout with display/checkout currency **EGP** → place order → mock payment success/failure URLs from checkout flow.
2. **USD:** Set display currency to **USD** on cart/checkout; place order uses FX rate above; same mock payment routes.

**Logged-in customers:** Use credentials in §4. **Guest** checkout remains enabled (`guest_checkout_enabled`).

Automated coverage (not browser): `CheckoutPlaceOrderHttpTest`, `MockPaymentFlowTest`, `StorefrontDisplayCurrencyTest`, `GuestCartCheckoutLifecycleTest`, etc.

---

## 10. Programmatic verification (not browser UI)

The agent **did not** perform visual browser QA. Verified instead:

| Area | How |
| --- | --- |
| Seeder idempotency | Ran `DemoStoreSeeder` twice on MySQL |
| Catalog eligibility | `DemoCatalogSeederTest` (published, translations, inventory) |
| Store / FX / shipping | `StoreSettingsSeeder` + checkout unit tests |
| Payment / order rules | Existing feature + MySQL integration suites |
| Image URL | `ProductImageUrlResolver` + file exists on disk |
| DB integrity | Inventory violation query (§8) |

---

## 11. Automated test results

### SQLite — `php artisan test`

| Result |
| --- |
| **397 passed**, **12 skipped** (MySQL integration group) |
| Duration ~83 s |

### MySQL — `php artisan test --configuration=phpunit.mysql.xml`

| Result |
| --- |
| **12 passed** (DB-013, checkout/cart concurrency, payment/expiry) |
| Duration ~12 s |

### Tooling

| Tool | Result |
| --- | --- |
| `vendor/bin/pint --test` | **Passed** (after seeder formatting) |
| `npm run build` | **Passed** |
| `git diff --check` | **Passed** (no conflict markers) |

---

## 12. URLs for the owner (from `.env` `APP_URL`)

Base: **`http://localhost/modular-online-store/public`**

| Page | URL |
| --- | --- |
| Homepage / shop | `/` and `/shop` |
| Product example | `/shop/classic-oxford-shirt` |
| Customer login | `/login` |
| Customer account / orders | `/account` (after login) |
| Admin login | `/admin/login` |
| Admin dashboard | `/admin` (after admin login) |

**Suggested manual browser checks**

- [ ] Admin login → dashboard metrics / orders list shows demo scenario orders
- [ ] Customer login → order history shows own orders only
- [ ] Shop lists 18 products; category filter; search `oxford`
- [ ] PDP shows price, variants, image (SVG placeholder)
- [ ] Add to cart (guest and logged-in)
- [ ] Checkout EGP and USD; mock pay success and failure
- [ ] Admin: products, inventory levels, order detail for `shipped` / `delivered` scenarios

---

## 13. Failures and limitations

1. **No browser verification** in this milestone — owner should run §12 checklist on WAMP.
2. **Single shared placeholder image** for all demo products (no per-SKU photography).
3. **Order scenarios** share one variant (`DEMO-OXF-M-WHT`) for predictable stock; not a full multi-line merchandising story.
4. **Re-seed** does not reset passwords or re-simulate orders once `demo-scenario-*` keys exist (by design).
5. **`php artisan db:show`** may list other schemas on the WAMP server; counts below apply only to `modular_online_store`.

---

## 14. Quick re-seed / inspect

```powershell
cd C:\wamp64\www\modular-online-store
php artisan config:clear
php artisan db:seed --class=DemoStoreSeeder
```

---

## 15. Next milestone

**UX-005** — storefront and admin UI redesign (out of scope here).