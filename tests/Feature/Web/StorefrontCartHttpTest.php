<?php

namespace Tests\Feature\Web;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Product;
use App\Domains\Store\Models\CurrencyExchangeRate;
use App\Shared\Support\GuestCartSession;
use App\Shared\Support\StorefrontPreferences;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\TestCase;

class StorefrontCartHttpTest extends TestCase
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
    }

    private function sessionWithCart(Cart $cart): static
    {
        return $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token]);
    }

    public function test_first_cart_page_visit_creates_cart_and_stores_session_token(): void
    {
        $response = $this->get(route('storefront.cart.show'));

        $response->assertOk();
        $response->assertSessionHas(GuestCartSession::SESSION_KEY);
        $this->assertSame(1, Cart::query()->where('status', Cart::STATUS_ACTIVE)->count());
    }

    public function test_subsequent_visit_reuses_active_cart(): void
    {
        $this->get(route('storefront.cart.show'));
        $token = session(GuestCartSession::SESSION_KEY);
        $this->assertIsString($token);

        $this->withSession([GuestCartSession::SESSION_KEY => $token])
            ->get(route('storefront.cart.show'))
            ->assertOk();

        $this->assertSame(1, Cart::query()->where('session_token', $token)->count());
    }

    public function test_add_item_successfully(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart('40.00');

        $response = $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
        ]);

        $response->assertRedirect(route('storefront.cart.show'));
        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(2, CartItem::query()->value('quantity'));
    }

    public function test_adding_same_variant_merges_quantity(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 10);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);
        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
        ]);

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(3, CartItem::query()->value('quantity'));
    }

    public function test_expired_cart_is_not_used_for_add(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $token = GuestCartSession::generateToken();

        $expired = Cart::query()->create([
            'session_token' => $token,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withSession([GuestCartSession::SESSION_KEY => $token])
            ->post(route('storefront.cart.items.store'), [
                'product_variant_id' => $variant->getKey(),
                'quantity' => 1,
            ])
            ->assertRedirect(route('storefront.cart.show'));

        $this->assertSame(Cart::STATUS_ABANDONED, $expired->fresh()->status);
        $itemCartId = (int) CartItem::query()->value('cart_id');
        $this->assertNotSame($expired->getKey(), $itemCartId);
    }

    public function test_invalid_quantity_rejected_on_add(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();

        $this->from(route('storefront.cart.show'))
            ->post(route('storefront.cart.items.store'), [
                'product_variant_id' => $variant->getKey(),
                'quantity' => 0,
            ])
            ->assertSessionHasErrors('quantity');
    }

    public function test_invalid_quantity_rejected_on_update(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 5);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);

        $itemId = (int) CartItem::query()->value('id');
        $cart = Cart::query()->firstOrFail();

        $this->sessionWithCart($cart)
            ->from(route('storefront.cart.show'))
            ->patch(route('storefront.cart.items.update', ['cartItem' => $itemId]), ['quantity' => 0])
            ->assertSessionHasErrors('quantity');
    }

    public function test_cart_shows_fx_unavailable_notice_when_display_rate_missing(): void
    {
        CurrencyExchangeRate::query()->where('quote_currency_code', 'EGP')->delete();

        ['variant' => $variant] = $this->createSellableVariantForCart();

        $this->withSession([StorefrontPreferences::SESSION_DISPLAY_CURRENCY => 'EGP'])
            ->post(route('storefront.cart.items.store'), [
                'product_variant_id' => $variant->getKey(),
                'quantity' => 1,
            ])
            ->assertRedirect(route('storefront.cart.show'));

        $this->withSession([StorefrontPreferences::SESSION_DISPLAY_CURRENCY => 'EGP'])
            ->get(route('storefront.cart.show'))
            ->assertOk()
            ->assertSee(__('storefront.cart.fx_unavailable', [], 'en'), false)
            ->assertSee(__('storefront.cart.indicative_notice', [], 'en'), false);
    }

    public function test_insufficient_stock_rejected_on_add(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 1);

        $this->from(route('storefront.cart.show'))
            ->post(route('storefront.cart.items.store'), [
                'product_variant_id' => $variant->getKey(),
                'quantity' => 5,
            ])
            ->assertStatus(422)
            ->assertSessionHasErrors('cart');
    }

    public function test_populated_cart_page_renders_snapshots(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(enName: 'Cart HTTP Product');

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);

        $this->get(route('storefront.cart.show'))
            ->assertOk()
            ->assertSee('Cart HTTP Product', false)
            ->assertSee('Large / Navy', false);
    }

    public function test_empty_cart_renders(): void
    {
        $this->get(route('storefront.cart.show'))
            ->assertOk()
            ->assertSee(__('storefront.cart.empty_title', [], 'en'), false);
    }

    public function test_arabic_cart_page_renders(): void
    {
        $this->from(route('storefront.cart.show'))
            ->post(route('storefront.locale'), ['locale' => 'ar']);

        $this->get(route('storefront.cart.show'))
            ->assertOk()
            ->assertSee(__('storefront.cart.title', [], 'ar'), false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_update_and_remove_item_via_http(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 5);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);

        $itemId = (int) CartItem::query()->value('id');
        $cart = Cart::query()->firstOrFail();

        $this->sessionWithCart($cart)
            ->patch(route('storefront.cart.items.update', ['cartItem' => $itemId]), ['quantity' => 3])
            ->assertRedirect(route('storefront.cart.show'));

        $this->assertSame(3, CartItem::query()->whereKey($itemId)->value('quantity'));

        $this->sessionWithCart($cart)
            ->delete(route('storefront.cart.items.destroy', ['cartItem' => $itemId]))
            ->assertRedirect(route('storefront.cart.show'));

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_cannot_modify_item_from_another_session_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);

        $itemId = (int) CartItem::query()->value('id');

        $this->withSession([GuestCartSession::SESSION_KEY => GuestCartSession::generateToken()])
            ->patch(route('storefront.cart.items.update', ['cartItem' => $itemId]), ['quantity' => 2])
            ->assertNotFound();
    }

    public function test_can_remove_item_when_product_becomes_unpublished(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->createSellableVariantForCart();

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);

        $itemId = (int) CartItem::query()->value('id');
        $cart = Cart::query()->firstOrFail();
        $product->update(['status' => Product::STATUS_DRAFT]);

        $this->sessionWithCart($cart)
            ->delete(route('storefront.cart.items.destroy', ['cartItem' => $itemId]))
            ->assertRedirect(route('storefront.cart.show'));

        $this->assertSame(0, CartItem::query()->count());
    }
}
