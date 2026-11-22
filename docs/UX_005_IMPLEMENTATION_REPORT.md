# UX-005 implementation report

**Date:** 2026-11-19  
**Decision:** UX-005 — Pre-production UI/UX polish (Approved)  
**Stack:** Blade, Tailwind CSS v4 (`resources/css/app.css`), Vite

## 1. Initial audit — main problems

| Area | Before |
| --- | --- |
| **Layout** | No site footer; minimal homepage (title + two links only); nav duplicated inside preference bar |
| **Visual system** | Sparse CSS tokens; generic zinc/Instrument Sans; little hierarchy |
| **Home** | No featured products, categories, or hero |
| **Shop / PDP** | Functional but plain cards; weak editorial feel |
| **Admin** | Horizontal link bar; no sidebar; dashboard tables unstyled |
| **Images** | Single shared SVG for all demo SKUs |
| **Mobile** | No dedicated mobile navigation |
| **Theming** | Some hardcoded colors on checkout (`bg-black`) breaking consistency |

Business logic, routes, CSRF, and domain Actions were **not** changed.

---

## 2. Design system decisions

| Token / pattern | Choice |
| --- | --- |
| **Display type** | Cormorant Garamond (editorial headlines) |
| **Body type** | DM Sans; Noto Sans Arabic for RTL |
| **Palette** | Warm ivory backgrounds (`#f8f6f3`), ink text, camel highlight (`#a67c52`) |
| **Themes** | CSS variables on `[data-theme=light/dark]`; `@custom-variant dark` for Tailwind |
| **Components** | `.sf-btn`, `.sf-input`, `.sf-card`, `.sf-product-card`, `.sf-chip`, `.sf-hero`, admin shell classes |
| **Direction** | Logical properties (`margin-inline`, `padding-inline`, `text-start`); `[dir=rtl]` font switch |
| **Admin** | Dark sidebar + light/dark-aware `--adm-*` tokens; mobile link strip |

Fonts loaded via Bunny Fonts in storefront layout.

---

## 3. Screens and components redesigned

### Storefront
- **Layout:** Sticky header, preference bar, flash messages, footer
- **Home:** Hero, category grid, featured products (via `HomeController` + catalog query)
- **Shop:** Editorial header, search, category chips, product grid
- **PDP:** Two-column layout, variant list in card
- **Cart / checkout:** Design-system forms and summaries
- **Partials:** `site-header`, `site-footer`, `product-card`, `flash-messages`

### Admin
- **Layout:** Sidebar shell + mobile nav
- **Dashboard:** Stat grid, styled tables
- **Login:** Centered admin card

### Backend (presentation only)
- `HomeController` — loads featured products and categories (read-only queries)
- `ProductImageUrlResolver` — category-aware demo placeholders
- `ListPublishedProductsQuery` / `GetProductBySlugQuery` — eager-load `categories` for images

---

## 4. Files changed (summary)

| Category | Notable paths |
| --- | --- |
| CSS/JS | `resources/css/app.css`, `resources/js/app.js` |
| Layouts | `layouts/storefront.blade.php`, `layouts/admin.blade.php` |
| Storefront views | `home`, `shop/index`, `products/show`, `cart/show`, `checkout/show`, partials |
| Admin views | `dashboard`, `auth/login`, `partials/sidebar` |
| PHP | `HomeController`, `ProductImageUrlResolver`, catalog queries, presenter |
| Assets | `storage/app/public/demo/catalog/placeholder-*.svg` (4 category variants) |
| Lang | `lang/en/storefront.php`, `lang/ar/storefront.php`, `lang/en/admin.php` |
| Tests | `StorefrontShoppingJourneyTest`, `ProductImageUrlResolverTest` |

`admin/partials/nav.blade.php` superseded by `sidebar.blade.php` (legacy file may remain unused).

---

## 5. Responsive verification

- **Implemented:** Mobile nav toggle (`data-sf-nav-toggle`), stacked grids, `sf-container` max-width, horizontal scroll on admin tables (`admin-table-wrap`)
- **Browser / screenshot:** **Not performed** in this session — verify manually at 375px / 768px / 1280px

---

## 6. English / Arabic & RTL / LTR

- Locale and theme controls retained in preference bar
- RTL: Arabic font family on `.storefront-body[dir=rtl]`
- New copy added for home hero/footer in **en** and **ar**
- No scattered `ml-*` overrides added for RTL; logical alignment preferred

---

## 7. Light / dark themes

- Storefront and admin rely on shared CSS variables and `data-theme`
- Removed checkout hardcoded `bg-black` / `dark:bg-white` button
- Admin sidebar uses fixed dark panel; main content follows theme tokens

---

## 8. Product imagery

| Item | Status |
| --- | --- |
| Generic DB path | Still `demo/catalog/placeholder.svg` |
| Display | Resolver maps to **mens / womens / outerwear / accessories** SVGs by category slug |
| Photography | **Still needed** — replace SVGs with real per-product images via admin upload |

No schema changes.

---

## 9. Automated verification

| Suite | Result |
| --- | --- |
| SQLite `php artisan test` | **398 passed**, 12 skipped (after journey test fix) |
| MySQL `phpunit.mysql.xml` | **12 passed** |
| `vendor/bin/pint` | **Passed** |
| `npm run build` | **Passed** (`app-CLVkDD26.css`, JS bundle updated) |
| `git diff --check` | **Passed** |

---

## 10. Known issues & remaining polish

1. **Admin CRUD** (products, categories, inventory forms) still use older field markup — inherit new admin tokens gradually.
2. **Customer account** pages use prior structure with global layout improvements only.
3. **Browser visual QA** not run — owner should spot-check RTL, dark mode, and checkout mock payment.
4. **Hero** does not use a photographic banner (by choice — no stock assets committed).
5. **Cart item count badge** not wired in header (link text only).

---

## 11. Manual checks for owner

Open `http://localhost/modular-online-store/public` and verify:
- Home hero, categories, featured grid
- Shop search + filters + PDP add-to-cart
- Cart → checkout (EGP/USD) → mock payment
- EN ↔ AR + light ↔ dark
- Admin sidebar + dashboard at `/admin/login`

---

## 12. Roadmap / decisions

UX-005 implementation satisfies the approved **pre-production polish** scope for storefront-first delivery; admin shell and dashboard are materially improved. Further form-level admin styling can follow without architecture changes.