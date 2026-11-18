<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\CompleteVerifiedPaymentSuccessAction;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\TestCase;

class CompleteVerifiedPaymentSuccessActionTest extends TestCase
{
    use CreatesOrderPaymentFixtures;
    use RefreshDatabase;

    private CompleteVerifiedPaymentSuccessAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);
        $this->action = $this->app->make(CompleteVerifiedPaymentSuccessAction::class);
    }

    public function test_duplicate_success_is_idempotent(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $payment = $this->createPaymentForOrder($order);

        $this->action->execute((int) $payment->getKey(), 'prov-1');
        $firstMovementCount = InventoryReservation::query()
            ->where('order_id', $order->getKey())
            ->where('status', InventoryReservation::STATUS_COMMITTED)
            ->count();

        $again = $this->action->execute((int) $payment->getKey(), 'prov-1');

        $this->assertSame(Payment::STATUS_CAPTURED, $again->status);
        $this->assertSame(
            $firstMovementCount,
            InventoryReservation::query()
                ->where('order_id', $order->getKey())
                ->where('status', InventoryReservation::STATUS_COMMITTED)
                ->count(),
        );
    }

    public function test_retry_from_failed_payment_captures_when_holds_active(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(
            orderStatus: Order::STATUS_PAYMENT_FAILED,
            lines: [['variant' => $variant, 'quantity' => 1]],
        );
        $payment = $this->createPaymentForOrder($order, Payment::STATUS_FAILED);

        $result = $this->action->execute((int) $payment->getKey());

        $this->assertSame(Payment::STATUS_CAPTURED, $result->status);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
    }
}
