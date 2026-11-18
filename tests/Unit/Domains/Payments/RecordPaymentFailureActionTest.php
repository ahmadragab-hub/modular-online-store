<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\RecordPaymentFailureAction;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\TestCase;

class RecordPaymentFailureActionTest extends TestCase
{
    use CreatesOrderPaymentFixtures;
    use RefreshDatabase;

    private RecordPaymentFailureAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);
        $this->action = $this->app->make(RecordPaymentFailureAction::class);
    }

    public function test_failure_retains_active_reservation(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $payment = $this->createPaymentForOrder($order);

        $this->action->execute((int) $payment->getKey(), 'DECLINED', 'Declined');

        $this->assertSame(Order::STATUS_PAYMENT_FAILED, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(
            InventoryReservation::STATUS_ACTIVE,
            InventoryReservation::query()->where('order_id', $order->getKey())->value('status'),
        );
    }

    public function test_failure_rejected_for_processing_order(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $order->update(['status' => Order::STATUS_PROCESSING, 'paid_at' => now()]);
        $payment = $this->createPaymentForOrder($order);

        $this->expectException(PaymentOperationException::class);
        $this->action->execute((int) $payment->getKey());
    }
}
