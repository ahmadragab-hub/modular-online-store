<?php

namespace Tests\Integration\MySQL;

use App\Domains\Cart\Actions\GetOrCreateCustomerCartAction;
use App\Domains\Cart\Models\Cart;
use App\Domains\Customers\Models\Customer;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCustomerAccountFixtures;
use Tests\TestCase;

/**
 * Customer-cart concurrency requires MySQL. True parallel contention needs separate DB connections;
 * this suite documents that gap until a dedicated parallel test is added.
 *
 * @group mysql
 */
class CustomerCartConcurrencyTest extends TestCase
{
    use CreatesCustomerAccountFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL connection required for customer cart concurrency tests.');
        }

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
    }

    public function test_sequential_customer_cart_creation_under_mysql_leaves_one_active_cart(): void
    {
        $user = $this->createCustomerWithUser('mysql-cart@example.test');
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();
        $action = $this->app->make(GetOrCreateCustomerCartAction::class);

        $first = $action->execute($customer, $user, 'USD');
        $second = $action->execute($customer, $user, 'USD');

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, Cart::query()
            ->where('customer_id', $customer->getKey())
            ->where('status', Cart::STATUS_ACTIVE)
            ->count());
    }
}
