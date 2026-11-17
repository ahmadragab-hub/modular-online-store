<?php

namespace Tests\Feature\Web;

use App\Domains\Identity\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCustomerAccountFixtures;
use Tests\TestCase;

class CustomerAccountStorefrontRegressionTest extends TestCase
{
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

    public function test_guest_navigation_shows_login_and_register(): void
    {
        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee(__('storefront.nav.login'), false)
            ->assertSee(__('storefront.nav.register'), false);
    }

    public function test_authenticated_customer_navigation_shows_account_and_logout(): void
    {
        $user = $this->createCustomerWithUser();

        $this->actingAs($user)
            ->get(route('storefront.home'))
            ->assertOk()
            ->assertSee(__('storefront.nav.account'), false)
            ->assertSee(__('storefront.nav.logout'), false);
    }

    public function test_guest_cart_and_checkout_pages_remain_accessible(): void
    {
        $this->get(route('storefront.cart.show'))->assertOk();
        $this->get(route('storefront.checkout.show'))->assertOk();
    }

    public function test_account_dashboard_renders_for_customer(): void
    {
        $user = $this->createCustomerWithUser();

        $this->actingAs($user)
            ->get(route('storefront.account.index'))
            ->assertOk()
            ->assertSee(__('storefront.account.dashboard.greeting', ['name' => 'Cust']), false);
    }

    public function test_admin_still_receives_403_on_account_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('storefront.account.index'))
            ->assertForbidden();
    }
}
