<?php

namespace Tests\Unit\Domains\Orders;

use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Actions\MarkOrderPaidAction;
use App\Domains\Orders\Exceptions\OrderTransitionException;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\CompleteVerifiedPaymentSuccessAction;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\TestCase;

class MarkOrderPaidActionTest extends TestCase
{
    use CreatesOrderPaymentFixtures;
    use RefreshDatabase;

    private MarkOrderPaidAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);
        $this->action = $this->app->make(MarkOrderPaidAction::class);
    }

    public function test_success_commits_all_reservations_and_moves_to_processing(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 2],
        ]);

        $result = $this->action->execute((int) $order->getKey());

        $this->assertSame(Order::STATUS_PROCESSING, $result->status);
        $this->assertNotNull($result->paid_at);
        $reservation = InventoryReservation::query()->where('order_id', $order->getKey())->first();
        $this->assertSame(InventoryReservation::STATUS_COMMITTED, $reservation?->status);
        $level = InventoryLevel::query()->where('product_variant_id', $variant->getKey())->first();
        $this->assertSame(18, $level?->quantity_on_hand);
        $this->assertSame(0, $level?->quantity_reserved);
    }

    public function test_rejects_when_no_active_reservations(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);

        InventoryReservation::query()->where('order_id', $order->getKey())->update([
            'status' => InventoryReservation::STATUS_EXPIRED,
        ]);

        $this->expectException(OrderTransitionException::class);
        $this->action->execute((int) $order->getKey());

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_rejects_partial_active_reservations_for_multi_variant_order(): void
    {
        $variantA = $this->createCatalogVariant('A');
        $variantB = $this->createCatalogVariant('B');
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variantA, 'quantity' => 1],
            ['variant' => $variantB, 'quantity' => 1],
        ]);

        InventoryReservation::query()
            ->where('order_id', $order->getKey())
            ->where('product_variant_id', $variantB->getKey())
            ->update(['status' => InventoryReservation::STATUS_EXPIRED]);

        $this->expectException(OrderTransitionException::class);
        $this->action->execute((int) $order->getKey());

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_rejects_when_reservation_quantity_mismatch(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 2],
        ]);

        InventoryReservation::query()->where('order_id', $order->getKey())->update(['quantity' => 1]);

        $this->expectException(OrderTransitionException::class);
        $this->action->execute((int) $order->getKey());
    }

    public function test_retry_after_payment_failed_succeeds_with_active_holds(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(
            orderStatus: Order::STATUS_PAYMENT_FAILED,
            lines: [['variant' => $variant, 'quantity' => 1]],
        );

        $result = $this->action->execute((int) $order->getKey());

        $this->assertSame(Order::STATUS_PROCESSING, $result->status);
    }

    public function test_rejects_late_success_after_cancellation(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->expectException(OrderTransitionException::class);
        $this->action->execute((int) $order->getKey());
    }

    public function test_idempotent_when_already_processing_and_committed(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);

        $this->action->execute((int) $order->getKey());
        $movementCount = InventoryMovement::query()->where('type', InventoryMovement::TYPE_SALE_COMMIT)->count();

        $again = $this->action->execute((int) $order->getKey());

        $this->assertSame(Order::STATUS_PROCESSING, $again->status);
        $this->assertSame($movementCount, InventoryMovement::query()->where('type', InventoryMovement::TYPE_SALE_COMMIT)->count());
    }

    public function test_commit_failure_rolls_back_order_and_payment_transition(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);

        InventoryReservation::query()->where('order_id', $order->getKey())->update([
            'expires_at' => now()->subMinute(),
        ]);

        $payment = $this->createPaymentForOrder($order);
        $complete = $this->app->make(CompleteVerifiedPaymentSuccessAction::class);

        try {
            $complete->execute((int) $payment->getKey());
            $this->fail('Expected commit failure.');
        } catch (\Throwable) {
            // CommitReservationAction rejects TTL-expired active rows.
        }

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }
}
