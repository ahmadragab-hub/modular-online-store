<?php

namespace Tests\Unit\Domains\Checkout;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Checkout\Actions\PrepareCheckoutAction;
use App\Domains\Checkout\DTOs\PrepareCheckoutInput;
use App\Domains\Checkout\Exceptions\CheckoutPreparationException;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\CurrencyExchangeRate;
use App\Domains\Store\Models\StoreSetting;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrepareCheckoutActionTest extends TestCase
{
    use RefreshDatabase;

    private PrepareCheckoutAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);

        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => false,
        ]);

        $this->action = $this->app->make(PrepareCheckoutAction::class);
    }

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function createPublishedSellableVariant(string $priceUsd = '100.00', int $stock = 10): array
    {
        $product = Product::query()->create([
            'name' => 'Checkout Product',
            'slug' => 'checkout-product-'.uniqid(),
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_EN,
            'name' => 'Checkout Product EN',
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_AR,
            'name' => 'منتج',
        ]);

        $size = Size::query()->create(['code' => 'M', 'label' => 'Medium']);
        $color = Color::query()->create(['name' => 'Black', 'slug' => 'black-'.uniqid()]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'CHK-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => $priceUsd,
            'status' => ProductVariant::STATUS_ACTIVE,
        ]);

        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => $stock,
            'quantity_reserved' => 0,
        ]);

        return ['product' => $product, 'variant' => $variant];
    }

    /**
     * @param  list<string>|null  $locales  Null = en+ar; empty list = none; subset = only those locales
     */
    private function createPublishedSellableVariantWithLocales(
        string $priceUsd = '100.00',
        int $stock = 10,
        ?array $locales = null,
        string $variantStatus = ProductVariant::STATUS_ACTIVE,
    ): array {
        $product = Product::query()->create([
            'name' => 'Checkout Product',
            'slug' => 'checkout-product-'.uniqid(),
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $locales ??= [Product::LOCALE_EN, Product::LOCALE_AR];
        foreach ($locales as $locale) {
            ProductTranslation::query()->create([
                'product_id' => $product->getKey(),
                'locale' => $locale,
                'name' => $locale === Product::LOCALE_AR ? 'منتج' : 'Checkout Product EN',
            ]);
        }

        $size = Size::query()->create(['code' => 'M', 'label' => 'Medium']);
        $color = Color::query()->create(['name' => 'Black', 'slug' => 'black-'.uniqid()]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'CHK-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => $priceUsd,
            'status' => $variantStatus,
        ]);

        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => $stock,
            'quantity_reserved' => 0,
        ]);

        return ['product' => $product, 'variant' => $variant];
    }

    private function createGuestCartWithItem(ProductVariant $variant, int $quantity = 1): Cart
    {
        $token = hash('sha256', Str::random(32));

        $cart = Cart::query()->create([
            'session_token' => $token,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => $quantity,
            'unit_price_base' => '1.00',
            'product_name' => 'Stale Name',
            'sku' => $variant->sku,
            'variant_label' => 'M / Black',
        ]);

        return $cart;
    }

    private function sessionTokenForCart(Cart $cart): string
    {
        return (string) $cart->session_token;
    }

    private function seedRate(string $code, string $rate): void
    {
        CurrencyExchangeRate::query()->create([
            'quote_currency_code' => $code,
            'rate' => $rate,
            'effective_from' => now()->subHour(),
            'created_at' => now(),
        ]);
    }

    public function test_valid_usd_checkout_preparation(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant('100.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'USD',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame('USD', $result->transactionCurrencyCode);
        $this->assertSame('1.00000000', $result->exchangeRate);
        $this->assertNull($result->currencyExchangeRateId);
        $this->assertSame('100.00', $result->lines[0]->unitPrice);
        $this->assertSame('100.00', $result->subtotal);
        $this->assertSame('100.00', $result->grandTotal);
        $this->assertFalse($result->taxEnabled);
        $this->assertSame('0.00', $result->taxTotal);
    }

    public function test_valid_egp_checkout_with_fx_rate(): void
    {
        $this->seedRate('EGP', '48.50000000');
        ['variant' => $variant] = $this->createPublishedSellableVariant('100.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'EGP',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame('4850.00', $result->lines[0]->unitPrice);
        $this->assertSame('4850.00', $result->subtotal);
        $this->assertNotNull($result->currencyExchangeRateId);
    }

    public function test_valid_kwd_checkout_three_decimal_rounding(): void
    {
        $this->seedRate('KWD', '0.30700000');
        ['variant' => $variant] = $this->createPublishedSellableVariant('10.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'KWD',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame('3.070', $result->lines[0]->unitPrice);
        $this->assertSame('3.070', $result->subtotal);
    }

    public function test_missing_fx_rate_blocks_non_usd(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);
        $this->expectExceptionMessage('No exchange rate');

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'EGP',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::EXCHANGE_RATE_MISSING, $e->errorCode);

            throw $e;
        }
    }

    public function test_unsupported_transaction_currency_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'EUR',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::CURRENCY_UNSUPPORTED, $e->errorCode);

            throw $e;
        }
    }

    public function test_transaction_disabled_currency_rejected(): void
    {
        Currency::query()->where('code', 'EGP')->update(['transaction_enabled' => false]);
        $this->seedRate('EGP', '48.50000000');

        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'EGP',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::CURRENCY_NOT_TRANSACTION_ENABLED, $e->errorCode);

            throw $e;
        } finally {
            Currency::query()->where('code', 'EGP')->update(['transaction_enabled' => true]);
        }
    }

    public function test_empty_cart_rejected(): void
    {
        $token = hash('sha256', 'empty-cart');
        $cart = Cart::query()->create([
            'session_token' => $token,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $token,
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::CART_EMPTY, $e->errorCode);

            throw $e;
        }
    }

    public function test_expired_cart_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $token = hash('sha256', 'expired');
        $cart = Cart::query()->create([
            'session_token' => $token,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '50.00',
            'product_name' => 'Name',
            'sku' => $variant->sku,
            'variant_label' => 'M / Black',
        ]);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $token,
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::CART_EXPIRED, $e->errorCode);

            throw $e;
        }
    }

    public function test_inactive_cart_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $token = hash('sha256', 'inactive');
        $cart = Cart::query()->create([
            'session_token' => $token,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_CONVERTED,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '50.00',
            'product_name' => 'Name',
            'sku' => $variant->sku,
            'variant_label' => 'M / Black',
        ]);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $token,
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::CART_NOT_ACTIVE, $e->errorCode);

            throw $e;
        }
    }

    public function test_unpublished_product_variant_rejected(): void
    {
        $product = Product::query()->create([
            'name' => 'Draft',
            'slug' => 'draft-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);
        $size = Size::query()->create(['code' => 'S', 'label' => 'Small']);
        $color = Color::query()->create(['name' => 'Blue', 'slug' => 'blue-'.uniqid()]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'DRF-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '50.00',
        ]);
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::VARIANT_UNAVAILABLE, $e->errorCode);

            throw $e;
        }
    }

    public function test_authoritative_catalog_price_not_cart_snapshot(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant('75.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'USD',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame('75.00', $result->lines[0]->unitPriceBaseUsd);
        $this->assertSame('75.00', $result->lines[0]->unitPrice);
    }

    public function test_shipping_flat_usd_converted_to_transaction_currency(): void
    {
        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => true,
            'flat_shipping_amount' => '10.00',
            'free_shipping_threshold' => null,
        ]);

        $this->seedRate('EGP', '48.50000000');
        ['variant' => $variant] = $this->createPublishedSellableVariant('20.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'EGP',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame('485.00', $result->shippingTotal);
        $this->assertSame('1455.00', $result->grandTotal);
    }

    public function test_free_shipping_threshold_in_usd(): void
    {
        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => true,
            'flat_shipping_amount' => '10.00',
            'free_shipping_threshold' => '50.00',
        ]);

        ['variant' => $variant] = $this->createPublishedSellableVariant('100.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'USD',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame('0.00', $result->shippingTotal);
        $this->assertSame('100.00', $result->grandTotal);
    }

    public function test_cart_ownership_for_registered_customer(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Own',
            'last_name' => 'Er',
        ]);

        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '100.00',
            'product_name' => 'Name',
            'sku' => $variant->sku,
            'variant_label' => 'M / Black',
        ]);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                userId: $other->getKey(),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::CART_OWNERSHIP, $e->errorCode);

            throw $e;
        }
    }

    public function test_money_amounts_are_decimal_strings_not_floats(): void
    {
        $this->seedRate('KWD', '0.33300000');
        ['variant' => $variant] = $this->createPublishedSellableVariant('10.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'KWD',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        foreach ([$result->subtotal, $result->grandTotal, $result->lines[0]->unitPrice] as $amount) {
            $this->assertIsString($amount);
            $this->assertMatchesRegularExpression('/^\d+(\.\d+)?$/', $amount);
        }

        $this->assertSame('3.330', $result->lines[0]->unitPrice);
    }

    public function test_shipping_enabled_without_flat_rate_rejects_preparation(): void
    {
        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => true,
            'flat_shipping_amount' => null,
        ]);

        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::SHIPPING_NOT_CONFIGURED, $e->errorCode);

            throw $e;
        }
    }

    public function test_explicit_zero_flat_shipping_amount_is_allowed(): void
    {
        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => true,
            'flat_shipping_amount' => '0.00',
            'free_shipping_threshold' => null,
        ]);

        ['variant' => $variant] = $this->createPublishedSellableVariant('25.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'USD',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame('0.00', $result->shippingTotal);
        $this->assertSame('25.00', $result->grandTotal);
    }

    public function test_insufficient_stock_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant('10.00', 1);
        $cart = $this->createGuestCartWithItem($variant, 2);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::INSUFFICIENT_STOCK, $e->errorCode);

            throw $e;
        }
    }

    public function test_inactive_variant_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariantWithLocales(
            variantStatus: ProductVariant::STATUS_INACTIVE,
        );
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::VARIANT_UNAVAILABLE, $e->errorCode);

            throw $e;
        }
    }

    public function test_missing_arabic_translation_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariantWithLocales(locales: [Product::LOCALE_EN]);
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::VARIANT_UNAVAILABLE, $e->errorCode);

            throw $e;
        }
    }

    public function test_missing_english_translation_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariantWithLocales(locales: [Product::LOCALE_AR]);
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: $this->sessionTokenForCart($cart),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::VARIANT_UNAVAILABLE, $e->errorCode);

            throw $e;
        }
    }

    public function test_guest_cart_wrong_session_token_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: hash('sha256', 'wrong-token'),
            ));
        } catch (CheckoutPreparationException $e) {
            $this->assertSame(CheckoutPreparationException::CART_OWNERSHIP, $e->errorCode);

            throw $e;
        }
    }

    public function test_free_shipping_threshold_met_exactly(): void
    {
        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => true,
            'flat_shipping_amount' => '10.00',
            'free_shipping_threshold' => '50.00',
        ]);

        ['variant' => $variant] = $this->createPublishedSellableVariant('50.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'USD',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame('0.00', $result->shippingTotal);
        $this->assertSame('50.00', $result->grandTotal);
    }

    public function test_quantity_greater_than_one_line_subtotal(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant('10.00');
        $cart = $this->createGuestCartWithItem($variant, 3);

        $result = $this->action->execute(new PrepareCheckoutInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: 'USD',
            sessionToken: $this->sessionTokenForCart($cart),
        ));

        $this->assertSame(3, $result->lines[0]->quantity);
        $this->assertSame('10.00', $result->lines[0]->unitPrice);
        $this->assertSame('30.00', $result->lines[0]->lineSubtotal);
        $this->assertSame('30.00', $result->subtotal);
    }

    public function test_failed_preparation_does_not_mutate_persisted_state(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $item = CartItem::query()->where('cart_id', $cart->getKey())->first();
        $level = InventoryLevel::query()->where('product_variant_id', $variant->getKey())->first();

        $before = [
            'cart_status' => $cart->status,
            'item_qty' => $item->quantity,
            'item_unit_price_base' => (string) $item->unit_price_base,
            'on_hand' => $level->quantity_on_hand,
            'reserved' => $level->quantity_reserved,
            'orders' => Order::query()->count(),
            'payments' => Payment::query()->count(),
            'reservations' => InventoryReservation::query()->count(),
        ];

        try {
            $this->action->execute(new PrepareCheckoutInput(
                cartId: $cart->getKey(),
                transactionCurrencyCode: 'USD',
                sessionToken: hash('sha256', 'wrong'),
            ));
            $this->fail('Expected CheckoutPreparationException was not thrown.');
        } catch (CheckoutPreparationException) {
            // expected
        }

        $item->refresh();
        $level->refresh();
        $cart->refresh();

        $this->assertSame($before['cart_status'], $cart->status);
        $this->assertSame($before['item_qty'], $item->quantity);
        $this->assertSame($before['item_unit_price_base'], (string) $item->unit_price_base);
        $this->assertSame($before['on_hand'], $level->quantity_on_hand);
        $this->assertSame($before['reserved'], $level->quantity_reserved);
        $this->assertSame($before['orders'], Order::query()->count());
        $this->assertSame($before['payments'], Payment::query()->count());
        $this->assertSame($before['reservations'], InventoryReservation::query()->count());
    }
}
