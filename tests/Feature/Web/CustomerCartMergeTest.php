<?php

namespace Tests\Feature\Web;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Customers\Models\Customer;
use App\Shared\Support\CartMergeFlash;
use App\Shared\Support\GuestCartSession;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\Support\CreatesCustomerAccountFixtures;
use Tests\TestCase;

class CustomerCartMergeTest extends TestCase
{
    use CreatesCartMutationFixtures;
    use CreatesCustomerAccountFixtures;
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

    public function test_authenticated_customer_resolves_own_cart_not_session_guest_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $user = $this->createCustomerWithUser();
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        $customerCart = Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $customerCart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '10.00',
            'product_name' => 'Mine',
            'sku' => $variant->sku,
            'variant_label' => 'L',
        ]);

        $guestToken = GuestCartSession::generateToken();
        $guestCart = Cart::query()->create([
            'session_token' => $guestToken,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $guestCart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 5,
            'unit_price_base' => '10.00',
            'product_name' => 'Guest',
            'sku' => $variant->sku,
            'variant_label' => 'L',
        ]);

        $this->actingAs($user)
            ->withSession([GuestCartSession::SESSION_KEY => $guestToken])
            ->get(route('storefront.cart.show'))
            ->assertOk()
            ->assertSee('Mine', false)
            ->assertDontSee('Guest', false);
    }

    public function test_login_merges_guest_cart_into_customer_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 10);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
        ]);
        $guestToken = session(GuestCartSession::SESSION_KEY);

        $user = $this->createCustomerWithUser('merge-login@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        $existingCart = Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $existingCart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '10.00',
            'product_name' => 'Existing',
            'sku' => $variant->sku,
            'variant_label' => 'Label',
        ]);

        $this->withSession([GuestCartSession::SESSION_KEY => $guestToken])
            ->post(route('storefront.login.store'), [
                'email' => 'merge-login@example.test',
                'password' => 'password123',
            ])
            ->assertRedirect(route('storefront.home'));

        $this->assertAuthenticated();
        $this->assertFalse(session()->has(GuestCartSession::SESSION_KEY));

        $existingCart->refresh();
        $this->assertSame(Cart::STATUS_ACTIVE, $existingCart->status);
        $this->assertSame(3, (int) CartItem::query()->where('cart_id', $existingCart->getKey())->value('quantity'));

        $guestCart = Cart::query()->where('session_token', $guestToken)->first();
        $this->assertTrue($guestCart === null || $guestCart->status === Cart::STATUS_ABANDONED);
    }

    public function test_registration_merges_guest_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 5);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
        ]);

        $this->post(route('storefront.register.store'), [
            'first_name' => 'Merge',
            'last_name' => 'Reg',
            'email' => 'merge-reg@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $customer = Customer::query()->where('email', 'merge-reg@example.test')->firstOrFail();
        $cart = Cart::query()
            ->where('customer_id', $customer->getKey())
            ->where('status', Cart::STATUS_ACTIVE)
            ->first();

        $this->assertNotNull($cart);
        $this->assertSame(2, (int) $cart->items()->value('quantity'));
    }

    public function test_merge_caps_quantity_when_stock_insufficient(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 3);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 3,
        ]);
        $guestToken = session(GuestCartSession::SESSION_KEY);

        $user = $this->createCustomerWithUser('cap-merge@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        $response = $this->withSession([GuestCartSession::SESSION_KEY => $guestToken])
            ->post(route('storefront.login.store'), [
                'email' => 'cap-merge@example.test',
                'password' => 'password123',
            ]);

        $cart = Cart::query()->where('customer_id', $customer->getKey())->where('status', Cart::STATUS_ACTIVE)->first();
        $this->assertSame(3, (int) $cart->items()->value('quantity'));
        $response->assertSessionHas(CartMergeFlash::SESSION_KEY);
    }

    public function test_multiple_active_customer_carts_stops_merge(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);
        $guestToken = session(GuestCartSession::SESSION_KEY);

        $user = $this->createCustomerWithUser('multi-cart@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);
        Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        $this->withSession([GuestCartSession::SESSION_KEY => $guestToken])
            ->post(route('storefront.login.store'), [
                'email' => 'multi-cart@example.test',
                'password' => 'password123',
            ]);

        $this->assertSame(
            CartMergeFlash::STATUS_MULTIPLE_CARTS,
            session(CartMergeFlash::SESSION_KEY)['status'] ?? null,
        );
        $this->assertTrue(session()->has(GuestCartSession::SESSION_KEY));
    }

    public function test_customer_cannot_mutate_another_customers_cart_item(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $owner = $this->createCustomerWithUser('owner@example.test');
        $other = $this->createCustomerWithUser('other@example.test');

        $ownerCustomer = Customer::query()->where('user_id', $owner->getKey())->firstOrFail();
        $cart = Cart::query()->create([
            'customer_id' => $ownerCustomer->getKey(),
            'user_id' => $owner->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        $item = CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '10.00',
            'product_name' => 'X',
            'sku' => $variant->sku,
            'variant_label' => 'L',
        ]);

        $this->actingAs($other)
            ->patch(route('storefront.cart.items.update', $item), ['quantity' => 2])
            ->assertNotFound();
    }

    public function test_merge_does_not_abandon_guest_cart_when_all_lines_fail(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 0);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);
        $guestToken = session(GuestCartSession::SESSION_KEY);

        $user = $this->createCustomerWithUser('all-fail@example.test');

        $this->withSession([GuestCartSession::SESSION_KEY => $guestToken])
            ->post(route('storefront.login.store'), [
                'email' => 'all-fail@example.test',
                'password' => 'password123',
            ])
            ->assertRedirect(route('storefront.home'));

        $this->assertAuthenticated();
        $this->assertSame($guestToken, session(GuestCartSession::SESSION_KEY));
        $guestCart = Cart::query()->where('session_token', $guestToken)->firstOrFail();
        $this->assertSame(Cart::STATUS_ACTIVE, $guestCart->status);
    }

    public function test_partial_merge_abandons_guest_and_keeps_successful_lines(): void
    {
        ['variant' => $inStock] = $this->createSellableVariantForCart(stock: 5, enName: 'In stock EN');
        ['variant' => $outOfStock] = $this->createSellableVariantForCart(stock: 0, enName: 'Out EN');

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $inStock->getKey(),
            'quantity' => 1,
        ]);
        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $outOfStock->getKey(),
            'quantity' => 1,
        ]);
        $guestToken = session(GuestCartSession::SESSION_KEY);

        $user = $this->createCustomerWithUser('partial@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        $this->withSession([GuestCartSession::SESSION_KEY => $guestToken])
            ->post(route('storefront.login.store'), [
                'email' => 'partial@example.test',
                'password' => 'password123',
            ]);

        $cart = Cart::query()->where('customer_id', $customer->getKey())->where('status', Cart::STATUS_ACTIVE)->firstOrFail();
        $this->assertSame(1, $cart->items()->count());
        $this->assertFalse(session()->has(GuestCartSession::SESSION_KEY));
        $this->assertNull(Cart::query()->where('session_token', $guestToken)->value('id'));
    }

    public function test_cap_merge_with_existing_customer_quantity_and_flash_payload(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 3);

        $guestToken = GuestCartSession::generateToken();
        $guestCart = Cart::query()->create([
            'session_token' => $guestToken,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);
        CartItem::query()->create([
            'cart_id' => $guestCart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 4,
            'unit_price_base' => '10.00',
            'product_name' => 'Guest',
            'sku' => $variant->sku,
            'variant_label' => 'L',
        ]);

        $user = $this->createCustomerWithUser('cap-existing@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();
        $customerCart = Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);
        CartItem::query()->create([
            'cart_id' => $customerCart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'unit_price_base' => '10.00',
            'product_name' => 'Cap',
            'sku' => $variant->sku,
            'variant_label' => 'L',
        ]);

        $response = $this->withSession([GuestCartSession::SESSION_KEY => $guestToken])
            ->post(route('storefront.login.store'), [
                'email' => 'cap-existing@example.test',
                'password' => 'password123',
            ]);

        $this->assertSame(3, (int) $customerCart->fresh()->items()->value('quantity'));

        $response->assertSessionHas(CartMergeFlash::SESSION_KEY);
        $flash = session(CartMergeFlash::SESSION_KEY);
        $this->assertSame(CartMergeFlash::STATUS_SUCCESS, $flash['status'] ?? null);
        $capped = collect($flash['lines'] ?? [])->firstWhere('status', 'capped');
        $this->assertNotNull($capped);
        $this->assertSame(4, $capped['guest_quantity_requested']);
        $this->assertSame(1, $capped['quantity_added']);
        $this->assertSame(3, $capped['final_quantity']);
    }

    public function test_second_login_with_new_guest_cart_does_not_duplicate_prior_quantities(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 20);

        $this->post(route('storefront.cart.items.store'), ['product_variant_id' => $variant->getKey(), 'quantity' => 2]);
        $firstToken = session(GuestCartSession::SESSION_KEY);

        $user = $this->createCustomerWithUser('no-dup@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        $this->withSession([GuestCartSession::SESSION_KEY => $firstToken])
            ->post(route('storefront.login.store'), [
                'email' => 'no-dup@example.test',
                'password' => 'password123',
            ]);

        $customerCart = Cart::query()->where('customer_id', $customer->getKey())->where('status', Cart::STATUS_ACTIVE)->firstOrFail();
        $this->assertSame(2, (int) $customerCart->items()->value('quantity'));

        $this->post(route('storefront.logout.destroy'));
        $this->assertGuest();

        $this->post(route('storefront.cart.items.store'), ['product_variant_id' => $variant->getKey(), 'quantity' => 2]);
        $secondToken = session(GuestCartSession::SESSION_KEY);

        $this->withSession([GuestCartSession::SESSION_KEY => $secondToken])
            ->post(route('storefront.login.store'), [
                'email' => 'no-dup@example.test',
                'password' => 'password123',
            ]);

        $this->assertSame(4, (int) $customerCart->fresh()->items()->value('quantity'));
    }

    public function test_cart_mutation_exception_during_merge_does_not_undo_authentication(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 5);

        $this->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
        ]);
        $guestToken = session(GuestCartSession::SESSION_KEY);

        $user = $this->createCustomerWithUser('merge-exc@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->subHour(),
        ]);

        $this->withSession([GuestCartSession::SESSION_KEY => $guestToken])
            ->post(route('storefront.login.store'), [
                'email' => 'merge-exc@example.test',
                'password' => 'password123',
            ])
            ->assertRedirect(route('storefront.home'));

        $this->assertAuthenticated();
        $this->assertSame($guestToken, session(GuestCartSession::SESSION_KEY));
        $this->assertSame(
            CartMergeFlash::STATUS_FAILED,
            session(CartMergeFlash::SESSION_KEY)['status'] ?? null,
        );
        $this->assertSame(Cart::STATUS_ACTIVE, Cart::query()->where('session_token', $guestToken)->value('status'));
        $this->assertSame(0, CartItem::query()
            ->whereIn('cart_id', Cart::query()->where('customer_id', $customer->getKey())->pluck('id'))
            ->count());
    }

    public function test_unavailable_guest_line_does_not_change_customer_cart_quantity(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 0);
        $variant->update(['status' => ProductVariant::STATUS_INACTIVE]);
        Product::query()->whereKey($variant->product_id)->update(['status' => Product::STATUS_DRAFT]);

        $token = GuestCartSession::generateToken();
        $guestCart = Cart::query()->create([
            'session_token' => $token,
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);
        CartItem::query()->create([
            'cart_id' => $guestCart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'unit_price_base' => '10.00',
            'product_name' => 'Stale',
            'sku' => $variant->sku,
            'variant_label' => 'L',
        ]);

        $user = $this->createCustomerWithUser('unavail@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();
        $customerCart = Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
        ]);
        CartItem::query()->create([
            'cart_id' => $customerCart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '10.00',
            'product_name' => 'Stale',
            'sku' => $variant->sku,
            'variant_label' => 'L',
        ]);

        $this->withSession([GuestCartSession::SESSION_KEY => $token])
            ->post(route('storefront.login.store'), [
                'email' => 'unavail@example.test',
                'password' => 'password123',
            ]);

        $this->assertSame(1, (int) $customerCart->fresh()->items()->value('quantity'));
        $this->assertSame($token, session(GuestCartSession::SESSION_KEY));
        $this->assertSame(Cart::STATUS_ACTIVE, $guestCart->fresh()->status);
    }
}
