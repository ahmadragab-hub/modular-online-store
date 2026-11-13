# Localization and themes

## Ownership (confirmed)

| Concern | Owner | Persistence |
| --- | --- | --- |
| Supported locales (`en`, `ar`), default locale, RTL in `store_locales` | **Store** | Database |
| Supported themes (`light`, `dark`), default theme in `store_themes` | **Store** | Database |
| Customer locale, theme, display currency | **Store** Actions validate; **Customers** optional columns for logged-in users | DB + session/cookie |
| Product/category merchandising copy | **Catalog** (`product_translations`) | Database |
| Blade, CSS, `lang/*` UI chrome | **HTTP** (presentation only) | Files |

**Publishing:** Catalog requires **both** `en` and `ar` `product_translations` before `published`. Storefront shows **no** fallback to another language.

**Presentation rule:** Views and controllers **must not** implement business rules (FX, tax, stock, checkout).

## Interim implementation → Store domain migration path

| Today (code) | Target (MVP data) |
| --- | --- |
| `config/localization.php` | Seed `store_locales`; `store_settings.default_locale` |
| `config/theme.php` | Seed `store_themes`; `store_settings.default_theme` |
| `ApplyStorefrontPreferences` middleware | Call Store Queries for allowed values; keep HTTP cookie/session |
| `PreferenceController` | Delegate to `SetLocalePreferenceAction`, `SetThemePreferenceAction`, `SetDisplayCurrencyPreferenceAction` |
| `lang/{locale}/storefront.php` | Storefront UI strings only—not product data |

**Migration steps (implementation phase):**

1. Add migrations for `store_settings`, `store_locales`, `store_themes`, `currencies`.
2. Seed from current config files.
3. Replace hardcoded validation lists in HTTP layer with Store Queries.
4. Optionally backfill `customers.preferred_*` on login.

## API

Domain Actions accept `locale` string for **Catalog** reads. Payment and order amounts never depend on locale.

## Related documents

- [DATABASE_DESIGN.md](DATABASE_DESIGN.md)
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOMAIN_DATA_OWNERSHIP.md](DOMAIN_DATA_OWNERSHIP.md)
