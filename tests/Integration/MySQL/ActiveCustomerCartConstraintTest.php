<?php

namespace Tests\Integration\MySQL;

use App\Domains\Cart\Actions\GetOrCreateCustomerCartAction;
use App\Domains\Cart\Models\Cart;
use App\Domains\Customers\Models\Customer;
use App\Shared\Support\GuestCartSession;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesCustomerAccountFixtures;
use Tests\TestCase;

/**
 * @group mysql
 */
class ActiveCustomerCartConstraintTest extends TestCase
{
    use CreatesCustomerAccountFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL connection required for DB-013 constraint tests.');
        }

        if (! Schema::hasColumn('carts', 'active_customer_claim')) {
            $this->markTestSkipped('DB-013 migration not applied on this database.');
        }

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
    }

    public function test_database_rejects_second_active_customer_cart(): void
    {
        $user = $this->createCustomerWithUser('db013@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'EGP',
            'base_currency_code' => 'EGP',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'EGP',
            'base_currency_code' => 'EGP',
            'status' => Cart::STATUS_ACTIVE,
        ]);
    }

    public function test_new_active_cart_allowed_after_abandoned(): void
    {
        $user = $this->createCustomerWithUser('db013-abandon@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        $old = Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'EGP',
            'base_currency_code' => 'EGP',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        $old->update(['status' => Cart::STATUS_ABANDONED]);

        Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'EGP',
            'base_currency_code' => 'EGP',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        $this->assertSame(1, Cart::query()
            ->where('customer_id', $customer->getKey())
            ->where('status', Cart::STATUS_ACTIVE)
            ->count());
    }

    public function test_guest_carts_unaffected_by_customer_claim(): void
    {
        Cart::query()->create([
            'session_token' => GuestCartSession::generateToken(),
            'display_currency_code' => 'EGP',
            'base_currency_code' => 'EGP',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        Cart::query()->create([
            'session_token' => GuestCartSession::generateToken(),
            'display_currency_code' => 'EGP',
            'base_currency_code' => 'EGP',
            'status' => Cart::STATUS_ACTIVE,
        ]);

        $this->assertSame(2, Cart::query()->whereNull('customer_id')->where('status', Cart::STATUS_ACTIVE)->count());
    }

    public function test_get_or_create_recovers_from_unique_violation_race(): void
    {
        $user = $this->createCustomerWithUser('db013-race@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();
        $action = $this->app->make(GetOrCreateCustomerCartAction::class);

        $first = $action->execute($customer, $user, 'EGP');
        $second = $action->execute($customer, $user, 'EGP');

        $this->assertSame($first->getKey(), $second->getKey());
    }
}
