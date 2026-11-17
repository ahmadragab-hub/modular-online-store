<?php

namespace Tests\Feature\Web;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Orders\Models\Order;
use App\Shared\Support\GuestCartSession;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
    }

    public function test_registration_creates_user_and_customer(): void
    {
        $response = $this->post(route('storefront.register.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'Ada@Example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('storefront.home'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'ada@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertSame('Ada Lovelace', $user->name);

        $customer = Customer::query()->where('user_id', $user->getKey())->first();
        $this->assertNotNull($customer);
        $this->assertSame('ada@example.test', $customer->email);
    }

    public function test_registration_links_guest_customer_and_preserves_orders(): void
    {
        $guest = Customer::query()->create([
            'email' => 'guestbuyer@example.test',
            'first_name' => 'G',
            'last_name' => 'Guest',
        ]);

        $order = Order::query()->create([
            'public_uuid' => (string) str()->uuid(),
            'order_number' => 'ORD-GUEST-1',
            'customer_id' => $guest->getKey(),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '50.0000',
            'grand_total' => '50.0000',
            'shipping_address' => ['line1' => '1 St', 'city' => 'Cairo', 'country_code' => 'EG', 'first_name' => 'G', 'last_name' => 'G'],
            'billing_address' => ['line1' => '1 St', 'city' => 'Cairo', 'country_code' => 'EG', 'first_name' => 'G', 'last_name' => 'G'],
            'idempotency_key' => hash('sha256', 'guest-order'),
            'placed_at' => now(),
        ]);

        $this->post(route('storefront.register.store'), [
            'first_name' => 'New',
            'last_name' => 'Shopper',
            'email' => 'guestbuyer@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $guest->refresh();
        $this->assertNotNull($guest->user_id);
        $this->assertSame('New', $guest->first_name);
        $this->assertSame($guest->getKey(), $order->fresh()->customer_id);
    }

    public function test_registration_normalizes_email_for_uniqueness_and_persistence(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->post(route('storefront.register.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => "  \tTaken@Example.test \n",
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->where('email', 'taken@example.test')->count());
    }

    public function test_registration_links_guest_customer_with_mismatched_email_casing(): void
    {
        $guest = Customer::query()->create([
            'email' => 'GuestMix@Example.test',
            'first_name' => 'G',
            'last_name' => 'Guest',
        ]);

        $this->post(route('storefront.register.store'), [
            'first_name' => 'Reg',
            'last_name' => 'User',
            'email' => '  guestmix@example.test ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $guest->refresh();
        $this->assertNotNull($guest->user_id);
        $this->assertSame('guestmix@example.test', $guest->email);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_registration_rejects_when_customer_email_linked_but_no_user_with_that_email(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.test']);

        $customer = Customer::query()->create([
            'user_id' => $owner->getKey(),
            'email' => 'registered@example.test',
            'first_name' => 'Linked',
            'last_name' => 'Customer',
        ]);

        $userCountBefore = User::query()->count();

        $this->from(route('storefront.register.create'))
            ->post(route('storefront.register.store'), [
                'first_name' => 'New',
                'last_name' => 'User',
                'email' => 'registered@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors(['email' => __('storefront.auth.register.email_taken')])
            ->assertRedirect(route('storefront.register.create'));

        $this->assertGuest();
        $this->assertSame($userCountBefore, User::query()->count());
        $this->assertNull(User::query()->where('email', 'registered@example.test')->first());

        $customer->refresh();
        $this->assertSame($owner->getKey(), $customer->user_id);
        $this->assertSame('registered@example.test', $customer->email);
        $this->assertSame('Linked', $customer->first_name);
    }

    public function test_registration_rejects_taken_email(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->post(route('storefront.register.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'taken@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, Customer::query()->count());
    }

    public function test_registration_rejects_short_or_unconfirmed_password(): void
    {
        $this->post(route('storefront.register.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'short@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post(route('storefront.register.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'mismatch@example.test',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    }

    public function test_registration_preserves_guest_cart_session_token(): void
    {
        $token = hash('sha256', 'preserved-token');

        $this->withSession([GuestCartSession::SESSION_KEY => $token])
            ->post(route('storefront.register.store'), [
                'first_name' => 'Cart',
                'last_name' => 'Keeper',
                'email' => 'cartkeeper@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertRedirect();

        $this->assertSame($token, session(GuestCartSession::SESSION_KEY));
    }

    public function test_login_preserves_guest_cart_session_token_after_regeneration(): void
    {
        User::factory()->create([
            'email' => 'cartlogin@example.test',
            'password' => 'password123',
        ]);

        $token = hash('sha256', 'login-cart-token');

        $this->withSession([GuestCartSession::SESSION_KEY => $token])
            ->post(route('storefront.login.store'), [
                'email' => 'cartlogin@example.test',
                'password' => 'password123',
            ])
            ->assertRedirect(route('storefront.home'));

        $this->assertAuthenticated();
        $this->assertSame($token, session(GuestCartSession::SESSION_KEY));
    }

    public function test_login_succeeds_for_active_customer(): void
    {
        User::factory()->create([
            'email' => 'login@example.test',
            'password' => 'password123',
        ]);

        $beforeId = session()->getId();

        $response = $this->post(route('storefront.login.store'), [
            'email' => 'login@example.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('storefront.home'));
        $this->assertAuthenticated();
        $this->assertNotSame($beforeId, session()->getId());
    }

    public function test_login_uses_generic_failure_message(): void
    {
        User::factory()->create(['email' => 'known@example.test', 'password' => 'password123']);

        $wrongPassword = $this->from(route('storefront.login.create'))
            ->post(route('storefront.login.store'), [
                'email' => 'known@example.test',
                'password' => 'wrong-password',
            ]);
        $wrongPassword->assertSessionHasErrors(['email' => __('storefront.auth.login.failed')]);

        $unknown = $this->from(route('storefront.login.create'))
            ->post(route('storefront.login.store'), [
                'email' => 'unknown@example.test',
                'password' => 'password123',
            ]);
        $unknown->assertSessionHasErrors(['email' => __('storefront.auth.login.failed')]);

        User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'password' => 'password123',
        ]);

        $admin = $this->from(route('storefront.login.create'))
            ->post(route('storefront.login.store'), [
                'email' => 'admin@example.test',
                'password' => 'password123',
            ]);
        $admin->assertSessionHasErrors(['email' => __('storefront.auth.login.failed')]);
    }

    public function test_inactive_customer_cannot_log_in(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'password123',
        ]);

        $this->post(route('storefront.login.store'), [
            'email' => 'inactive@example.test',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $token = csrf_token();

        $response = $this->post(route('storefront.logout.destroy'));
        $response->assertRedirect(route('storefront.home'));
        $this->assertGuest();
        $this->assertNotSame($token, csrf_token());
    }

    public function test_guest_redirected_from_account_to_login(): void
    {
        $this->get(route('storefront.account.index'))
            ->assertRedirect(route('storefront.login.create'));
    }

    public function test_customer_can_access_account_placeholder(): void
    {
        $user = User::factory()->create();
        Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'A',
            'last_name' => 'B',
        ]);

        $this->actingAs($user)
            ->get(route('storefront.account.index'))
            ->assertOk();
    }

    public function test_admin_receives_403_on_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('storefront.account.index'))
            ->assertForbidden();
    }

    public function test_inactive_authenticated_customer_denied_on_account(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('storefront.account.index'))
            ->assertForbidden();
    }

    public function test_guest_can_still_access_shop_and_cart(): void
    {
        $this->get(route('storefront.shop.index'))->assertOk();
        $this->get(route('storefront.cart.show'))->assertOk();
    }

    public function test_login_is_throttled(): void
    {
        User::factory()->create(['email' => 'throttle@example.test', 'password' => 'password123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('storefront.login.store'), [
                'email' => 'throttle@example.test',
                'password' => 'wrong',
            ]);
        }

        $this->post(route('storefront.login.store'), [
            'email' => 'throttle@example.test',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_registration_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('storefront.register.store'), []);
        }

        $this->post(route('storefront.register.store'), [])->assertStatus(429);
    }
}
