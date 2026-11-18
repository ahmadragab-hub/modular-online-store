<?php

namespace Tests\Feature\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Orders\Models\Order;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAdminTestFixtures;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\TestCase;

class AdminOrderHttpTest extends TestCase
{
    use CreatesAdminTestFixtures;
    use CreatesOrderPaymentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAdminBaselines();
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);
    }

    public function test_admin_can_mark_processing_order_shipped(): void
    {
        $admin = $this->createActiveAdmin();
        $order = Order::query()->create($this->orderPayload(Order::STATUS_PROCESSING));

        $this->actingAs($admin)
            ->post(route('admin.orders.transition', $order), ['to_status' => Order::STATUS_SHIPPED])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
    }

    public function test_admin_cannot_cancel_processing_order_via_http(): void
    {
        $admin = $this->createActiveAdmin();
        $order = Order::query()->create($this->orderPayload(Order::STATUS_PROCESSING) + ['paid_at' => now()]);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.cancel', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('cancel');

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_guest_cannot_access_order_admin(): void
    {
        $order = Order::query()->create($this->orderPayload(Order::STATUS_PENDING_PAYMENT));

        $this->get(route('admin.orders.show', $order))
            ->assertRedirect(route('admin.login.create'));
    }

    public function test_customer_cannot_access_order_admin(): void
    {
        $customer = User::factory()->create();
        $order = Order::query()->create($this->orderPayload(Order::STATUS_PENDING_PAYMENT));

        $this->actingAs($customer)
            ->get(route('admin.orders.show', $order))
            ->assertForbidden();
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
            'shipping_address' => $this->minimalAddress(),
            'billing_address' => $this->minimalAddress(),
            'idempotency_key' => hash('sha256', uniqid((string) $status, true)),
            'placed_at' => now(),
        ];
    }
}
