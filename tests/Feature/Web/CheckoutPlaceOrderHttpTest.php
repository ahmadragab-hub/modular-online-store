<?php

namespace Tests\Feature\Web;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Models\User;
use App\Domains\Orders\Models\Order;
use App\Domains\Store\Models\StoreSetting;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsCheckoutPlaceOrderFixtures;
use Tests\TestCase;

class CheckoutPlaceOrderHttpTest extends TestCase
{
    use BuildsCheckoutPlaceOrderFixtures;
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

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Cart $cart, string $idempotencyKey, ?string $guestEmail = 'guest@example.com'): array
    {
        $addresses = $this->defaultAddresses();

        return [
            'cart_id' => $cart->getKey(),
            'session_token' => $cart->session_token,
            'transaction_currency_code' => 'USD',
            'content_locale' => 'en',
            'idempotency_key' => $idempotencyKey,
            'guest_email' => $guestEmail,
            'shipping_address' => $addresses['shipping'],
            'billing_address' => $addresses['billing'],
        ];
    }

    public function test_checkout_page_renders(): void
    {
        $this->get(route('storefront.checkout.show'))->assertOk();
    }

    public function test_validation_errors_for_missing_required_fields(): void
    {
        $response = $this->from(route('storefront.checkout.show'))
            ->post(route('storefront.checkout.store'), []);

        $response->assertRedirect(route('storefront.checkout.show'));
        $response->assertSessionHasErrors(['cart_id', 'idempotency_key']);
    }

    public function test_successful_guest_checkout_redirects_to_success_page(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'http-'.uniqid());

        $response = $this->post(route('storefront.checkout.store'), $this->validPayload($cart, $idempotencyKey));

        $order = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('storefront.checkout.success', ['publicUuid' => $order->public_uuid]));
        $this->get(route('storefront.checkout.success', ['publicUuid' => $order->public_uuid]))
            ->assertOk()
            ->assertSee($order->order_number, false);
    }

    public function test_wrong_session_token_returns_cart_error_without_internal_details(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $payload = $this->validPayload($cart, hash('sha256', 'own-'.uniqid()));
        $payload['session_token'] = 'wrong-token';

        $response = $this->from(route('storefront.checkout.show'))
            ->post(route('storefront.checkout.store'), $payload);

        $response->assertRedirect(route('storefront.checkout.show'));
        $response->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_idempotency_conflict_returns_http_409(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'http-409-'.uniqid());
        $payload = $this->validPayload($cart, $idempotencyKey);

        $this->post(route('storefront.checkout.store'), $payload)->assertRedirect();

        $payload['billing_address']['city'] = 'Giza';
        $response = $this->post(route('storefront.checkout.store'), $payload);
        $response->assertStatus(409);
    }

    public function test_registered_user_can_checkout_without_guest_email(): void
    {
        $user = User::factory()->create();
        /** @var ProductVariant $variant */
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createRegisteredCartWithItem($user, $variant);
        $payload = $this->validPayload($cart, hash('sha256', 'auth-'.Str::random(8)), guestEmail: null);
        unset($payload['guest_email']);

        $this->actingAs($user)
            ->post(route('storefront.checkout.store'), $payload)
            ->assertRedirect();
    }
}
