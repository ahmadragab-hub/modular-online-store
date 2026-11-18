<?php

namespace Tests\Feature\Web;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAdminTestFixtures;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use CreatesAdminTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAdminBaselines();
    }

    public function test_admin_can_login_and_logout(): void
    {
        $admin = $this->createActiveAdmin([
            'email' => 'admin@example.test',
            'password' => 'password12345',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'password12345',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->post(route('admin.logout.destroy'))->assertRedirect(route('admin.login.create'));
        $this->assertGuest();
    }

    public function test_customer_cannot_use_admin_login(): void
    {
        User::factory()->create([
            'email' => 'shopper@example.test',
            'password' => 'password12345',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'shopper@example.test',
            'password' => 'password12345',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login.create'));
    }

    public function test_customer_authenticated_gets_403_on_admin_dashboard(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);

        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_inactive_admin_denied_at_login(): void
    {
        $this->createActiveAdmin([
            'email' => 'inactive@example.test',
            'password' => 'password12345',
            'is_active' => false,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'inactive@example.test',
            'password' => 'password12345',
        ])->assertSessionHasErrors('email');
    }
}
