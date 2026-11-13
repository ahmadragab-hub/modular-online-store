<?php

namespace Tests\Unit\Domains\Identity;

use App\Domains\Identity\Models\User;
use App\Domains\Store\Models\CurrencyExchangeRate;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_uses_users_table_and_incrementing_primary_key(): void
    {
        $user = User::factory()->create();

        $this->assertSame('users', $user->getTable());
        $this->assertSame('id', $user->getKeyName());
        $this->assertTrue($user->getIncrementing());
        $this->assertSame('int', $user->getKeyType());
    }

    public function test_password_is_hashed_and_hidden(): void
    {
        $user = User::factory()->create([
            'password' => 'plain-secret',
        ]);

        $user->refresh();

        $this->assertTrue(Hash::check('plain-secret', $user->password));
        $this->assertNotContains('password', array_keys($user->toArray()));
        $this->assertNotContains('remember_token', array_keys($user->toArray()));
    }

    public function test_role_and_active_casts(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $inactive = User::factory()->inactive()->create();

        $this->assertSame(User::ROLE_CUSTOMER, $customer->role);
        $this->assertTrue($customer->isActive());
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertFalse($inactive->is_active);
        $this->assertFalse($inactive->isActive());
    }

    public function test_email_verified_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
    }

    public function test_auth_provider_uses_identity_user_model(): void
    {
        $model = config('auth.providers.users.model');

        $this->assertSame(User::class, $model);
    }

    public function test_currency_exchange_rate_created_by_user_relationship(): void
    {
        $this->seed(CurrencySeeder::class);

        $admin = User::factory()->admin()->create();
        $rate = CurrencyExchangeRate::query()->create([
            'quote_currency_code' => 'KWD',
            'rate' => '0.30750000',
            'effective_from' => now(),
            'created_by_user_id' => $admin->getKey(),
            'created_at' => now(),
        ]);

        $this->assertInstanceOf(User::class, $rate->createdByUser);
        $this->assertTrue($admin->createdCurrencyExchangeRates()->whereKey($rate->getKey())->exists());
    }
}
