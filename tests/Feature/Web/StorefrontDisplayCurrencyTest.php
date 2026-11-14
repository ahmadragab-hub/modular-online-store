<?php

namespace Tests\Feature\Web;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Models\Order;
use App\Domains\Store\Models\CurrencyExchangeRate;
use App\Domains\Store\Models\StoreSetting;
use App\Shared\Support\GuestCartSession;
use App\Shared\Support\StorefrontPreferences;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCheckoutPlaceOrderFixtures;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\TestCase;

class StorefrontDisplayCurrencyTest extends TestCase
{
    use BuildsCheckoutPlaceOrderFixtures;
    use CreatesCartMutationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);
    }

    public function test_customer_can_switch_display_currency_and_cart_syncs(): void
    {
        CurrencyExchangeRate::query()->create([
            'quote_currency_code' => 'EGP',
            'rate' => '48.50000000',
            'effective_from' => now()->subDay(),
            'created_at' => now(),
        ]);

        ['variant' => $variant] = $this->createSellableVariantForCart('100.00');

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);

        $sessionToken = session(GuestCartSession::SESSION_KEY);

        $this->from(route('storefront.cart.show'))
            ->post(route('storefront.currency'), ['display_currency_code' => 'EGP'])
            ->assertRedirect(route('storefront.cart.show'))
            ->assertCookie(StorefrontPreferences::COOKIE_DISPLAY_CURRENCY, 'EGP');

        $this->withSession([
            GuestCartSession::SESSION_KEY => $sessionToken,
            StorefrontPreferences::SESSION_DISPLAY_CURRENCY => 'EGP',
        ])->get(route('storefront.cart.show'))->assertOk();

        $cart = Cart::query()->firstOrFail();
        $this->assertSame('EGP', $cart->fresh()->display_currency_code);

        $this->withUnencryptedCookie(StorefrontPreferences::COOKIE_DISPLAY_CURRENCY, 'EGP')
            ->get(route('storefront.shop.index'))
            ->assertOk()
            ->assertSee('E£', false);
    }

    public function test_kwd_display_respects_three_decimal_places_on_shop_card(): void
    {
        CurrencyExchangeRate::query()->create([
            'quote_currency_code' => 'KWD',
            'rate' => '0.30700000',
            'effective_from' => now()->subDay(),
            'created_at' => now(),
        ]);

        ['variant' => $variant] = $this->createSellableVariantForCart('100.00');
        Product::query()->whereKey($variant->product_id)->update(['slug' => 'kwd-price-test']);

        $this->withSession([StorefrontPreferences::SESSION_DISPLAY_CURRENCY => 'KWD'])
            ->get(route('storefront.products.show', ['slug' => 'kwd-price-test']))
            ->assertOk()
            ->assertSee('30.700', false);
    }

    public function test_invalid_display_currency_rejected(): void
    {
        $this->from(route('storefront.home'))
            ->post(route('storefront.currency'), ['display_currency_code' => 'ZZZ'])
            ->assertSessionHasErrors('display_currency_code');
    }

    public function test_checkout_transaction_currency_independent_from_display_preference(): void
    {
        CurrencyExchangeRate::query()->create([
            'quote_currency_code' => 'EGP',
            'rate' => '48.50000000',
            'effective_from' => now()->subDay(),
            'created_at' => now(),
        ]);

        ['variant' => $variant] = $this->createSellableVariantForCart('50.00');

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);

        $this->post(route('storefront.currency'), ['display_currency_code' => 'EGP']);
        $sessionToken = session(GuestCartSession::SESSION_KEY);
        $cart = Cart::query()->firstOrFail();

        $this->withSession([
            GuestCartSession::SESSION_KEY => $sessionToken,
            StorefrontPreferences::SESSION_DISPLAY_CURRENCY => 'EGP',
        ])->get(route('storefront.cart.show'))->assertOk();

        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update(['shipping_enabled' => false]);

        $idempotencyKey = hash('sha256', 'tx-currency-'.uniqid());
        $addresses = $this->defaultAddresses();
        $payload = [
            'transaction_currency_code' => 'USD',
            'content_locale' => 'en',
            'idempotency_key' => $idempotencyKey,
            'guest_email' => 'guest@example.com',
            'shipping_address' => $addresses['shipping'],
            'billing_address' => $addresses['billing'],
        ];

        $this->withSession([
            GuestCartSession::SESSION_KEY => $sessionToken,
            StorefrontPreferences::SESSION_DISPLAY_CURRENCY => 'EGP',
        ])
            ->post(route('storefront.checkout.store'), $payload)
            ->assertRedirect();

        $order = Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        $this->assertSame('USD', $order->currency_code);
        $this->assertSame('EGP', $cart->fresh()->display_currency_code);
    }
}
