<?php

namespace Tests\Feature\Web;

use App\Domains\Cart\Actions\AddCartItemAction;
use App\Domains\Cart\Actions\GetOrCreateCartAction;
use App\Domains\Cart\Actions\RemoveCartItemAction;
use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Models\Order;
use App\Domains\Store\Models\StoreSetting;
use App\Shared\Support\GuestCartSession;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCheckoutPlaceOrderFixtures;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\TestCase;

class GuestCartCheckoutLifecycleTest extends TestCase
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

        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => false,
        ]);
    }

    private function checkoutPayload(string $idempotencyKey): array
    {
        $addresses = $this->defaultAddresses();

        return [
            'transaction_currency_code' => 'USD',
            'content_locale' => 'en',
            'idempotency_key' => $idempotencyKey,
            'guest_email' => 'guest@example.com',
            'shipping_address' => $addresses['shipping'],
            'billing_address' => $addresses['billing'],
        ];
    }

    public function test_successful_checkout_blocks_second_order_with_different_idempotency_key(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $sessionToken = (string) $cart->session_token;

        $this->withSession([GuestCartSession::SESSION_KEY => $sessionToken])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload(hash('sha256', 'life-1-'.uniqid())))
            ->assertRedirect();

        $this->assertSame(Cart::STATUS_CONVERTED, $cart->fresh()->status);

        $this->withSession([GuestCartSession::SESSION_KEY => $sessionToken])
            ->from(route('storefront.checkout.show'))
            ->post(route('storefront.checkout.store'), $this->checkoutPayload(hash('sha256', 'life-2-'.uniqid())))
            ->assertRedirect(route('storefront.checkout.show'))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(1, Order::query()->count());
    }

    public function test_idempotent_retry_after_checkout_succeeds_with_empty_new_cart(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'life-idem-'.uniqid());

        $first = $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload($idempotencyKey));

        $order = Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        $first->assertRedirect(route('storefront.checkout.success', ['publicUuid' => $order->public_uuid]));

        $rotatedToken = session(GuestCartSession::SESSION_KEY);
        $this->assertNotSame($cart->session_token, $rotatedToken);

        $this->withSession([GuestCartSession::SESSION_KEY => $rotatedToken])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload($idempotencyKey))
            ->assertRedirect(route('storefront.checkout.success', ['publicUuid' => $order->public_uuid]));

        $this->assertSame(1, Order::query()->where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_guest_receives_new_usable_cart_after_successful_checkout(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload(hash('sha256', 'fresh-'.uniqid())))
            ->assertRedirect();

        $newToken = session(GuestCartSession::SESSION_KEY);
        $this->assertIsString($newToken);
        $this->assertNotSame($cart->session_token, $newToken);

        ['variant' => $variant2] = $this->createSellableVariantForCart(enName: 'Fresh cart item');

        $this->withSession([GuestCartSession::SESSION_KEY => $newToken])
            ->post(route('storefront.cart.items.store'), [
                'product_variant_id' => $variant2->getKey(),
                'quantity' => 1,
            ])
            ->assertRedirect(route('storefront.cart.show'));

        $activeCart = Cart::query()->where('session_token', $newToken)->where('status', Cart::STATUS_ACTIVE)->first();
        $this->assertNotNull($activeCart);
        $this->assertNotSame($cart->getKey(), $activeCart->getKey());
    }

    public function test_converted_cart_cannot_accept_new_items(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload(hash('sha256', 'mut-add-'.uniqid())));

        $addAction = $this->app->make(AddCartItemAction::class);

        try {
            $addAction->execute($cart->fresh(), $variant->getKey(), 1, Product::LOCALE_EN);
            $this->fail('Expected CartMutationException.');
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::CART_NOT_ACTIVE, $exception->errorCode);
        }
    }

    public function test_converted_cart_items_cannot_be_removed(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $itemId = (int) $cart->items()->value('id');

        $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload(hash('sha256', 'mut-rem-'.uniqid())));

        $removeAction = $this->app->make(RemoveCartItemAction::class);

        try {
            $removeAction->execute($cart->fresh(), $itemId);
            $this->fail('Expected CartMutationException.');
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::CART_NOT_ACTIVE, $exception->errorCode);
        }
    }

    public function test_expired_cart_token_rotation_still_works(): void
    {
        $token = GuestCartSession::generateToken();
        $expired = Cart::query()->create([
            'session_token' => $token,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withSession([GuestCartSession::SESSION_KEY => $token])
            ->get(route('storefront.cart.show'))
            ->assertOk();

        $newToken = session(GuestCartSession::SESSION_KEY);
        $this->assertNotSame($token, $newToken);
        $this->assertSame(Cart::STATUS_ABANDONED, $expired->fresh()->status);
        $this->assertNull($expired->fresh()->session_token);
    }

    public function test_get_or_create_recovers_from_duplicate_token_insert(): void
    {
        $action = $this->app->make(GetOrCreateCartAction::class);
        $token = GuestCartSession::generateToken();

        $first = $action->execute($token, 'USD');
        $second = $action->execute($token, 'USD');

        $this->assertSame($first->cart->getKey(), $second->cart->getKey());
    }
}
