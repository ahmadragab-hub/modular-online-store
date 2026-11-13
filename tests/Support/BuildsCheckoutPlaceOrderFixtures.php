<?php

namespace Tests\Support;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Checkout\DTOs\PlaceOrderInput;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Store\Models\CurrencyExchangeRate;
use Illuminate\Support\Str;

trait BuildsCheckoutPlaceOrderFixtures
{
    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    protected function createPublishedSellableVariant(string $priceUsd = '100.00', int $stock = 10, string $suffix = ''): array
    {
        $product = Product::query()->create([
            'name' => 'Place Order Product '.$suffix,
            'slug' => 'place-order-product-'.$suffix.'-'.uniqid(),
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_EN,
            'name' => 'Place Order EN',
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_AR,
            'name' => 'طلب',
        ]);

        $size = Size::query()->create(['code' => 'PO-'.$suffix, 'label' => 'Medium']);
        $color = Color::query()->create(['name' => 'Green', 'slug' => 'green-'.$suffix.'-'.uniqid()]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'PO-'.$suffix.'-'.uniqid(),
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

    protected function createGuestCartWithItem(ProductVariant $variant, int $quantity = 1): Cart
    {
        $cart = Cart::query()->create([
            'session_token' => hash('sha256', Str::random(32)),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => $quantity,
            'unit_price_base' => (string) $variant->price,
            'product_name' => 'Fixture Name',
            'sku' => $variant->sku,
            'variant_label' => 'M / Green',
        ]);

        return $cart;
    }

    protected function createRegisteredCartWithItem(User $user, ProductVariant $variant, int $quantity = 1): Cart
    {
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Reg',
            'last_name' => 'User',
        ]);

        $cart = Cart::query()->create([
            'user_id' => $user->getKey(),
            'customer_id' => $customer->getKey(),
            'session_token' => hash('sha256', Str::random(32)),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => $quantity,
            'unit_price_base' => (string) $variant->price,
            'product_name' => 'Fixture Name',
            'sku' => $variant->sku,
            'variant_label' => 'M / Green',
        ]);

        return $cart;
    }

    protected function seedRate(string $code, string $rate): void
    {
        CurrencyExchangeRate::query()->create([
            'quote_currency_code' => $code,
            'rate' => $rate,
            'effective_from' => now()->subHour(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{shipping: array<string, string>, billing: array<string, string>}
     */
    protected function defaultAddresses(string $billingCity = 'Cairo'): array
    {
        $base = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'line1' => '1 Main St',
            'city' => 'Cairo',
            'country_code' => 'EG',
        ];

        $billing = $base;
        $billing['city'] = $billingCity;

        return [
            'shipping' => $base,
            'billing' => $billing,
        ];
    }

    protected function placeOrderInput(
        Cart $cart,
        string $idempotencyKey,
        string $currency = 'USD',
        ?string $guestEmail = 'guest@example.com',
        ?int $userId = null,
        ?string $billingCity = 'Cairo',
    ): PlaceOrderInput {
        $addresses = $this->defaultAddresses($billingCity);

        return new PlaceOrderInput(
            cartId: $cart->getKey(),
            transactionCurrencyCode: $currency,
            contentLocale: 'en',
            idempotencyKey: $idempotencyKey,
            shippingAddress: $addresses['shipping'],
            billingAddress: $addresses['billing'],
            guestEmail: $userId === null ? $guestEmail : null,
            customerNote: null,
            userId: $userId,
            sessionToken: $userId === null ? (string) $cart->session_token : null,
        );
    }
}
