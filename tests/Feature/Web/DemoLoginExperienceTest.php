<?php

namespace Tests\Feature\Web;

use App\Domains\Identity\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLoginExperienceTest extends TestCase
{
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

    public function test_demo_login_hints_visible_in_testing_environment(): void
    {
        $this->get(route('storefront.login.create'))
            ->assertOk()
            ->assertSee('Demo Username:', false)
            ->assertSee(DemoUsersSeeder::PRIMARY_CUSTOMER_EMAIL, false)
            ->assertSee(DemoUsersSeeder::CUSTOMER_PASSWORD, false)
            ->assertSee('value="'.DemoUsersSeeder::PRIMARY_CUSTOMER_EMAIL.'"', false)
            ->assertSee('value="'.DemoUsersSeeder::CUSTOMER_PASSWORD.'"', false);

        $this->get(route('admin.login.create'))
            ->assertOk()
            ->assertSee('Demo Username:', false)
            ->assertSee(DemoUsersSeeder::ADMIN_EMAIL, false)
            ->assertSee(DemoUsersSeeder::ADMIN_PASSWORD, false)
            ->assertSee('value="'.DemoUsersSeeder::ADMIN_EMAIL.'"', false)
            ->assertSee('value="'.DemoUsersSeeder::ADMIN_PASSWORD.'"', false);
    }

    public function test_demo_login_hints_hidden_when_environment_is_production(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->get(route('storefront.login.create'))
                ->assertOk()
                ->assertDontSee('demo-login-hints', false)
                ->assertDontSee(DemoUsersSeeder::CUSTOMER_PASSWORD, false);

            $this->get(route('admin.login.create'))
                ->assertOk()
                ->assertDontSee('demo-login-hints', false)
                ->assertDontSee(DemoUsersSeeder::ADMIN_PASSWORD, false);
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_documented_demo_credentials_login_successfully(): void
    {
        $this->seed(DemoUsersSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $this->post(route('storefront.login.store'), [
            'email' => DemoUsersSeeder::PRIMARY_CUSTOMER_EMAIL,
            'password' => DemoUsersSeeder::CUSTOMER_PASSWORD,
        ])->assertRedirect();

        $customer = User::query()->where('email', DemoUsersSeeder::PRIMARY_CUSTOMER_EMAIL)->firstOrFail();
        $this->assertAuthenticatedAs($customer);
        $this->assertSame(User::ROLE_CUSTOMER, $customer->role);

        $this->post(route('storefront.logout.destroy'));
        $this->assertGuest();

        $this->post(route('admin.login.store'), [
            'email' => DemoUsersSeeder::ADMIN_EMAIL,
            'password' => DemoUsersSeeder::ADMIN_PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));

        $admin = User::query()->where('email', DemoUsersSeeder::ADMIN_EMAIL)->firstOrFail();
        $this->assertAuthenticatedAs($admin);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
    }

    public function test_demo_customer_cannot_access_admin_dashboard(): void
    {
        $this->seed(DemoUsersSeeder::class);

        $this->post(route('storefront.login.store'), [
            'email' => DemoUsersSeeder::PRIMARY_CUSTOMER_EMAIL,
            'password' => DemoUsersSeeder::CUSTOMER_PASSWORD,
        ]);

        $this->get(route('admin.dashboard'))->assertForbidden();
    }
}
