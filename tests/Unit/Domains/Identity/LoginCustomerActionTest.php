<?php

namespace Tests\Unit\Domains\Identity;

use App\Domains\Identity\Actions\LoginCustomerAction;
use App\Domains\Identity\Exceptions\CustomerLoginException;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCustomerActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticates_active_customer(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.test',
            'password' => 'password123',
        ]);

        $result = app(LoginCustomerAction::class)->execute('Customer@example.test', 'password123');

        $this->assertTrue($result->is($user));
    }

    public function test_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'customer@example.test']);

        $this->expectException(CustomerLoginException::class);
        app(LoginCustomerAction::class)->execute('customer@example.test', 'wrong');
    }

    public function test_rejects_unknown_email(): void
    {
        $this->expectException(CustomerLoginException::class);
        app(LoginCustomerAction::class)->execute('missing@example.test', 'password123');
    }

    public function test_rejects_inactive_customer(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'password123',
        ]);

        $this->expectException(CustomerLoginException::class);
        app(LoginCustomerAction::class)->execute('inactive@example.test', 'password123');
    }

    public function test_rejects_admin(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'password' => 'password123',
        ]);

        $this->expectException(CustomerLoginException::class);
        app(LoginCustomerAction::class)->execute('admin@example.test', 'password123');
    }
}
