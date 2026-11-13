# Catalog domain model implementation report

**Date:** 2026-03-20  
**Branch (local):** `feature/catalog-models` (no commit per task)  
**Scope:** Eloquent models and unit tests only

---

## 1. Schema note (documentation vs migrations)

The task brief mentions category translations and product attributes. **Approved Tier‑1 migrations do not include** `category_translations`, `product_attributes`, or `product_attribute_values` (deferred in `DATABASE_DESIGN.md`). Models were implemented **only** for tables in `2026_03_20_100004_create_catalog_foundation_tables.php`.

---

## 2. Tables and models

| Table | Model |
| --- | --- |
| `sizes` | `Size` |
| `colors` | `Color` |
| `categories` | `Category` |
| `products` | `Product` |
| `product_translations` | `ProductTranslation` |
| `product_variants` | `ProductVariant` |
| `product_images` | `ProductImage` |
| `category_product` | *(pivot via `belongsToMany` on `Category` / `Product`; no dedicated pivot model)* |

---

## 3. Model responsibilities

| Model | Responsibility |
| --- | --- |
| `Size` / `Color` | Reusable variant attribute lookups |
| `Category` | Tree (`parent` / `children`), product assignment, active flag |
| `Product` | Merchandising shell, status, soft delete; links to translations, variants, images, categories |
| `ProductTranslation` | Localized copy per `store_locales` code (`en`, `ar`) |
| `ProductVariant` | Sellable SKU, **USD** `price` (`decimal:2`) |
| `ProductImage` | Gallery paths; optional variant-specific image |

---

## 4. Relationships

| Model | Relationships |
| --- | --- |
| `Size` | `productVariants()` |
| `Color` | `productVariants()` |
| `Category` | `parent()`, `children()`, `products()` (pivot `is_primary`) |
| `Product` | `translations()`, `translationEn()`, `translationAr()`, `variants()`, `images()`, `categories()` |
| `ProductTranslation` | `product()`, `storeLocale()` |
| `ProductVariant` | `product()`, `size()`, `color()`, `images()` |
| `ProductImage` | `product()`, `variant()` |

No cross-domain relations to Cart, Inventory, or Orders (tables exist; domain models not implemented).

---

## 5. Casts, constants, scopes

| Item | Detail |
| --- | --- |
| `Product` | Soft deletes; `published_at` datetime |
| `Product` constants | `STATUS_DRAFT`, `STATUS_PUBLISHED`, `STATUS_ARCHIVED`; `LOCALE_EN`, `LOCALE_AR` |
| `Product` scopes | `draft()`, `published()` |
| `ProductVariant` | `price`, `compare_at_price` → `decimal:2`; `STATUS_ACTIVE` / `STATUS_INACTIVE`; `active()` scope |
| `Category` | `active()` scope |
| `Category` / pivot | `is_active` boolean; pivot `is_primary` on `category_product` |

No currency conversion. No publish Action (both locales required at publish time is documented for future `PublishProductAction`).

---

## 6. Translation handling

- `Product::translations()` returns all `product_translations` rows.
- `translationEn()` / `translationAr()` are `hasOne` filtered by `locale` (`en` / `ar`).
- `ProductTranslation::storeLocale()` FK `locale` → `store_locales.code`.
- **No** fallback to another locale in models (per requirements).
- Category MVP copy remains on `categories.name` until Tier‑2 `category_translations`.

---

## 7. Tests

**File:** `tests/Unit/Domains/Catalog/CatalogModelsTest.php`

| Test | Coverage |
| --- | --- |
| Category hierarchy + `active` scope | Parent/child |
| EN + AR translations | `translationEn` / `translationAr`, `storeLocale` |
| Unique `(product_id, locale)` | DB constraint |
| Variant USD decimal + unique SKU | `decimal:2`, FKs |
| Category–product pivot + soft delete | `is_primary`, `SoftDeletes` |
| Product images | Product and optional variant |
| Product status scopes | `draft` / `published` |

Seeds: `StoreLocaleSeeder` only (translation FK).

---

## 8. Verification results

```text
php artisan test
Tests:    31 passed (120 assertions)
Duration: ~2.7s
```

Store, Identity, Customers, and Feature tests: **unchanged, all passing**.

```text
vendor/bin/pint app/Domains/Catalog tests/Unit/Domains/Catalog
→ pass

vendor/bin/pint --test app/Domains/Catalog tests/Unit/Domains/Catalog
→ pass
```

**Whole-repo** `vendor/bin/pint --test`: still fails on pre-existing `bootstrap/app.php` and `config/localization.php` (unchanged per scope protection).

**Git:** no commit or push; branch expected `feature/catalog-models`.

---

## 9. Files changed

| Path | Action |
| --- | --- |
| `app/Domains/Catalog/Models/Size.php` | Added |
| `app/Domains/Catalog/Models/Color.php` | Added |
| `app/Domains/Catalog/Models/Category.php` | Added |
| `app/Domains/Catalog/Models/Product.php` | Added |
| `app/Domains/Catalog/Models/ProductTranslation.php` | Added |
| `app/Domains/Catalog/Models/ProductVariant.php` | Added |
| `app/Domains/Catalog/Models/ProductImage.php` | Added |
| `tests/Unit/Domains/Catalog/CatalogModelsTest.php` | Added |
| `docs/CATALOG_DOMAIN_MODEL_IMPLEMENTATION_REPORT.md` | Added |

No migrations. No Store/Identity/Customers model changes.

---

## 10. Recommendations / deferred

- **`CategoryTranslation` model** when Tier‑2 migration lands.
- **`ProductAttribute` models** when normalized attributes are migrated.
- **`PublishProductAction`** to enforce both `en` and `ar` translations + active variants.
- **Factories** for catalog entities when admin/catalog feature tests need them.

---

## Verdict

### **CATALOG DOMAIN MODELS IMPLEMENTED AND VERIFIED**

All migrated Catalog tables have models; relationships match the schema; English and Arabic translation access is covered by tests.
