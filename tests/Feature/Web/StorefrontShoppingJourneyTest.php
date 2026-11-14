<?php

namespace Tests\Feature\Web;

use App\Domains\Cart\Models\Cart;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use App\Domains\Store\Models\StoreSetting;
use App\Shared\Support\GuestCartSession;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\TestCase;

class StorefrontShoppingJourneyTest extends TestCase
{
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

        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => false,
        ]);
    }

    public function test_full_guest_shopping_journey_from_shop_to_idempotent_checkout(): void
    {
        ['product' => $product, 'variant' => $variantA] = $this->createSellableVariantForCart('60.00', stock: 10, enName: 'Journey Tee');
        ['variant' => $variantB] = $this->createSellableVariantForCart('40.00', stock: 10, enName: 'Journey Cap');

        $this->get(route('storefront.home'))->assertOk()->assertSee(__('storefront.home.view_cart', [], 'en'), false);
        $this->get(route('storefront.shop.index'))->assertOk()->assertSee('Journey Tee', false);
        $this->get(route('storefront.products.show', ['slug' => $product->slug]))->assertOk()->assertSee('Journey Tee', false);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variantA->getKey(),
            'quantity' => 1,
        ])->assertRedirect(route('storefront.cart.show'));

        $cart = Cart::query()->firstOrFail();
        $sessionToken = (string) $cart->session_token;
        $itemAId = (int) $cart->items()->value('id');

        $this->withSession([GuestCartSession::SESSION_KEY => $sessionToken])
            ->patch(route('storefront.cart.items.update', ['cartItem' => $itemAId]), ['quantity' => 2])
            ->assertRedirect(route('storefront.cart.show'));

        $this->withSession([GuestCartSession::SESSION_KEY => $sessionToken])
            ->post(route('storefront.cart.items.store'), [
                'product_variant_id' => $variantB->getKey(),
                'quantity' => 1,
            ])
            ->assertRedirect(route('storefront.cart.show'));

        $this->withSession([GuestCartSession::SESSION_KEY => $sessionToken])
            ->get(route('storefront.cart.show'))
            ->assertOk();

        $idempotencyKey = hash('sha256', 'journey-'.uniqid());
        $payload = [
            'transaction_currency_code' => 'USD',
            'content_locale' => 'en',
            'idempotency_key' => $idempotencyKey,
            'guest_email' => 'journey@example.com',
            'shipping_address' => [
                'first_name' => 'Journey', 'last_name' => 'Guest', 'line1' => '1 Main', 'city' => 'Cairo', 'country_code' => 'EG',
            ],
            'billing_address' => [
                'first_name' => 'Journey', 'last_name' => 'Guest', 'line1' => '1 Main', 'city' => 'Cairo', 'country_code' => 'EG',
            ],
        ];

        $this->withSession([GuestCartSession::SESSION_KEY => $sessionToken])
            ->post(route('storefront.checkout.store'), $payload)
            ->assertRedirect();

        $order = Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        $this->assertSame(Cart::STATUS_CONVERTED, $cart->fresh()->status);
        $this->assertSame(1, Payment::query()->where('order_id', $order->getKey())->count());
        $this->assertGreaterThan(0, InventoryReservation::query()->where('order_id', $order->getKey())->count());

        $rotated = session(GuestCartSession::SESSION_KEY);
        $this->assertNotSame($sessionToken, $rotated);

        $reservationCount = InventoryReservation::query()->count();
        $paymentCount = Payment::query()->count();

        $this->withSession([GuestCartSession::SESSION_KEY => $rotated])
            ->post(route('storefront.checkout.store'), $payload)
            ->assertRedirect(route('storefront.checkout.success', ['publicUuid' => $order->public_uuid]));

        $this->assertSame($reservationCount, InventoryReservation::query()->count());
        $this->assertSame($paymentCount, Payment::query()->count());

        $this->withSession([GuestCartSession::SESSION_KEY => $rotated])
            ->get(route('storefront.cart.show'))
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, Cart::query()->where('status', Cart::STATUS_ACTIVE)->count());
    }
}
