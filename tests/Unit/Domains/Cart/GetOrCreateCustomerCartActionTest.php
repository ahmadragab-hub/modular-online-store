<?php

namespace Tests\Unit\Domains\Cart;

use App\Domains\Cart\Actions\GetOrCreateCustomerCartAction;
use App\Domains\Cart\Exceptions\MultipleActiveCustomerCartsException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Customers\Models\Customer;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCustomerAccountFixtures;
use Tests\TestCase;

class GetOrCreateCustomerCartActionTest extends TestCase
{
    use CreatesCustomerAccountFixtures;
    use RefreshDatabase;

    private GetOrCreateCustomerCartAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
        $this->action = $this->app->make(GetOrCreateCustomerCartAction::class);
    }

    public function test_sequential_requests_resolve_same_active_cart(): void
    {
        $user = $this->createCustomerWithUser();
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();

        $first = $this->action->execute($customer, $user, 'USD');
        $second = $this->action->execute($customer, $user, 'USD');

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, Cart::query()
            ->where('customer_id', $customer->getKey())
            ->where('status', Cart::STATUS_ACTIVE)
            ->count());
    }

    public function test_multiple_active_carts_trigger_stop_exception(): void
    {
        $user = $this->createCustomerWithUser();
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

        $this->expectException(MultipleActiveCustomerCartsException::class);
        $this->action->execute($customer, $user, 'USD');
    }
}
