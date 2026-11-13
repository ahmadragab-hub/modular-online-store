# Checkout preparation implementation report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows` (no commit/push)
**Phase:** Read-only checkout preparation (no orders, payments, or reservations)

---

## 1. Files created and changed

| Path | Role |
| --- | --- |
| `app/Domains/Checkout/Actions/PrepareCheckoutAction.php` | Use case: validate cart + compute authoritative summary |
| `app/Domains/Checkout/DTOs/PrepareCheckoutInput.php` | Cart id, transaction currency, ownership (`userId` or `sessionToken`) |
| `app/Domains/Checkout/DTOs/PrepareCheckoutLineResult.php` | Per-line priced snapshot for display/place-order input |
| `app/Domains/Checkout/DTOs/PrepareCheckoutResult.php` | Totals, FX metadata, lines |
| `app/Domains/Checkout/Exceptions/CheckoutPreparationException.php` | Typed failure codes |
| `app/Domains/Store/Queries/ResolveCurrentExchangeRateQuery.php` | Latest rate for quote currency (USD = 1.0) |
| `app/Domains/Store/Services/CheckoutAmountConverter.php` | USD → transaction currency with half-up |
| `app/Shared/Support/MoneyMath.php` | BCMath add/multiply/compare/roundHalfUp |
| `tests/Unit/Domains/Checkout/PrepareCheckoutActionTest.php` | 15 tests |
| `docs/CHECKOUT_PREPARATION_IMPLEMENTATION_REPORT.md` | This report |

**Removed:** `app/Domains/Checkout/.gitkeep`

**Not changed:** migrations, Eloquent models, routes, controllers.

---

## 2. Responsibilities

| Component | Responsibility |
| --- | --- |
| `PrepareCheckoutAction` | Orchestrate validation, FX, line pricing, shipping, return DTO |
| `ResolveCurrentExchangeRateQuery` | Store-owned read: effective rate per quote currency |
| `CheckoutAmountConverter` | Store-owned conversion using `currencies.minor_units` |
| `MoneyMath` | Shared decimal-safe arithmetic |

---

## 3. Calculation rules

1. **Unit price (USD):** `product_variants.price` (not `cart_items.unit_price_base`).
2. **FX:** `rate` = quote per 1 USD; USD uses `1.00000000` without a rate row.
3. **Unit in transaction currency:** `roundHalfUp(usd × rate, minor_units)`.
4. **Line subtotal:** `unit_price × quantity` at transaction `minor_units`.
5. **Cart subtotal (transaction):** sum of line subtotals.
6. **Cart subtotal (USD):** sum of `usd × qty` at 2 decimals (for shipping threshold).
7. **Shipping (USD):**
   - `shipping_enabled = false` → **0.00 USD**.
   - `shipping_enabled = true` and `flat_shipping_amount` is **null** → `CheckoutPreparationException` (`shipping_not_configured`).
   - `shipping_enabled = true` and flat amount set (including **0.00**) → apply flat unless `subtotal_base_usd >= free_shipping_threshold` (threshold in **USD**).
8. **Shipping (transaction):** convert shipping USD component like line prices.
9. **Tax:** always **0** when `tax_enabled` is false (MVP seed default); `tax_total` returned as zero in transaction scale.
10. **Discount:** **0** (MVP; no promotion engine).
11. **Grand total:** `subtotal + shipping_total + tax_total` (no discounts in this phase).

---

## 4. Validation rules

- Cart exists, `status = active`, not expired, not empty.
- Ownership: registered `userId` matches `carts.user_id` / `customers.user_id`; guest `sessionToken` matches `carts.session_token`.
- Variant **active**, product **published**, **en** + **ar** translations present.
- Stock: `quantity_on_hand - quantity_reserved >= line quantity` (read-only check).
- Currency exists and `transaction_enabled`.
- Non-USD without rate → `exchange_rate_missing`.

---

## 5. Store configuration note

Default `StoreSettingsSeeder` has `shipping_enabled = true` and `flat_shipping_amount = null`. **Prepare checkout fails** with `shipping_not_configured` until admin sets a flat rate or disables shipping. Explicit `0.00` flat rate is valid (configured free shipping before threshold logic).

---

## 6. Writes confirmation

`PrepareCheckoutAction` performs **no** `INSERT`/`UPDATE`/`DELETE` on orders, payments, inventory reservations, cart status, cart items, or inventory levels. Validation failures do not mutate persisted rows (covered by `test_failed_preparation_does_not_mutate_persisted_state`).

---

## 7. Tests

**File:** `tests/Unit/Domains/Checkout/PrepareCheckoutActionTest.php`

| Test | Coverage |
| --- | --- |
| USD / EGP / KWD happy paths | FX, rounding (KWD 3 dp) |
| Missing FX | EGP without row |
| Unsupported / disabled currency | EUR missing; EGP `transaction_enabled = 0` |
| Cart empty / expired / inactive | |
| Unpublished product | |
| Authoritative price | Cart snapshot `1.00`, catalog `75.00` |
| Shipping flat + free threshold | USD threshold; EGP converted shipping |
| Shipping enabled, null flat | `shipping_not_configured` |
| Explicit zero flat rate | Allowed |
| Insufficient stock / inactive variant | |
| Missing en or ar translation | |
| Guest wrong session token | |
| Free shipping threshold exact match | Subtotal USD = threshold |
| Quantity > 1 line subtotal | |
| Ownership | Wrong user |
| Decimal strings | BCMath outputs |
| Read-only on failure | Cart, items, inventory, orders, payments, reservations unchanged |

**Commands:**

```text
php artisan test --filter=PrepareCheckoutActionTest
→ 25 passed (71 assertions)

php artisan test (full suite)
→ 75 passed (261 assertions)

vendor/bin/pint --test (checkout + store query/service + MoneyMath + tests)
→ PASS
```

---

## 8. Known limitations

- No HTTP layer (Form Request / controller).
- No discounts or tax engine.
- No idempotency or place order.
- Guest checkout flag `guest_checkout_enabled` not enforced here (HTTP concern).
- Display names on lines still taken from cart item labels; **prices** are authoritative from catalog.

---

## 9. Verdict

**CHECKOUT PREPARATION IMPLEMENTED AND VERIFIED**
