<?php

namespace Tests\Unit\Domains\Orders;

use App\Domains\Identity\Models\User;
use App\Domains\Orders\Actions\TransitionOrderStatusAction;
use App\Domains\Orders\Exceptions\OrderTransitionException;
use App\Domains\Orders\Models\Order;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionOrderStatusActionTest extends TestCase
{
    use RefreshDatabase;

    private TransitionOrderStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);
        $this->action = $this->app->make(TransitionOrderStatusAction::class);
    }

    public function test_processing_to_shipped_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::query()->create($this->orderPayload(Order::STATUS_PROCESSING));

        $result = $this->action->execute((int) $order->getKey(), Order::STATUS_SHIPPED, (int) $admin->getKey(), 'Packed');

        $this->assertSame(Order::STATUS_SHIPPED, $result->status);
    }

    public function test_pending_payment_to_shipped_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::query()->create($this->orderPayload(Order::STATUS_PENDING_PAYMENT));

        $this->expectException(OrderTransitionException::class);
        $this->action->execute((int) $order->getKey(), Order::STATUS_SHIPPED, (int) $admin->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(string $status): array
    {
        return [
            'public_uuid' => (string) str()->uuid(),
            'order_number' => 'ORD-'.uniqid(),
            'status' => $status,
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '10.0000',
            'grand_total' => '10.0000',
            'shipping_address' => ['line1' => '1', 'city' => 'C', 'country_code' => 'EG', 'first_name' => 'A', 'last_name' => 'B'],
            'billing_address' => ['line1' => '1', 'city' => 'C', 'country_code' => 'EG', 'first_name' => 'A', 'last_name' => 'B'],
            'idempotency_key' => hash('sha256', uniqid((string) $status, true)),
            'placed_at' => now(),
        ];
    }
}
