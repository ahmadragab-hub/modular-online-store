<?php

namespace Tests\Integration\MySQL;

use App\Domains\Inventory\Actions\ExpireReservationsAction;
use App\Domains\Inventory\DTOs\ExpireReservationsInput;
use App\Domains\Inventory\Exceptions\ExpiredInventoryReservationException;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\CompleteVerifiedPaymentSuccessAction;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\TestCase;

/**
 * Payment success vs reservation expiry under MySQL row locking (ADMIN-006 §E, CHECKOUT-009).
 *
 * @group mysql
 */
class PaymentExpiryConcurrencyTest extends TestCase
{
    use CreatesOrderPaymentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL connection required for payment/expiry concurrency tests.');
        }

        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);
    }

    public function test_payment_success_then_expiry_does_not_leave_inconsistent_state(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $payment = $this->createPaymentForOrder($order);

        $successAction = $this->app->make(CompleteVerifiedPaymentSuccessAction::class);
        $expireAction = $this->app->make(ExpireReservationsAction::class);

        DB::transaction(fn () => $successAction->execute((int) $payment->getKey(), 'mysql-prov'));

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $expireResult = $expireAction->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(0, $expireResult->expiredCount);

        $order->refresh();
        $payment->refresh();

        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->status);
        $this->assertSame(
            InventoryReservation::STATUS_COMMITTED,
            InventoryReservation::query()->where('order_id', $order->getKey())->value('status'),
        );
    }

    public function test_late_payment_success_after_expiry_cancels_order_is_rejected(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $payment = $this->createPaymentForOrder($order);

        $successAction = $this->app->make(CompleteVerifiedPaymentSuccessAction::class);
        $expireAction = $this->app->make(ExpireReservationsAction::class);

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $expireAction->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(
            InventoryReservation::STATUS_EXPIRED,
            InventoryReservation::query()->where('order_id', $order->getKey())->value('status'),
        );

        $this->expectException(PaymentOperationException::class);

        try {
            DB::transaction(fn () => $successAction->execute((int) $payment->getKey(), 'mysql-prov-late'));
        } finally {
            $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
            $this->assertSame(Payment::STATUS_CANCELLED, $payment->fresh()->status);
            $this->assertNull($order->fresh()->paid_at);
        }
    }

    public function test_payment_success_rejected_when_hold_past_ttl_under_mysql_locks(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $payment = $this->createPaymentForOrder($order);

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $successAction = $this->app->make(CompleteVerifiedPaymentSuccessAction::class);

        $this->expectException(ExpiredInventoryReservationException::class);

        try {
            DB::transaction(fn () => $successAction->execute((int) $payment->getKey(), 'mysql-prov-ttl'));
        } finally {
            $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
            $this->assertNull($order->fresh()->paid_at);
            $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
            $this->assertSame(
                InventoryReservation::STATUS_ACTIVE,
                InventoryReservation::query()->where('order_id', $order->getKey())->value('status'),
            );
        }
    }
}
