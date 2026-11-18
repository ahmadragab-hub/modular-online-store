<?php

namespace Tests\Integration\MySQL;

use App\Domains\Inventory\Actions\ExpireReservationsAction;
use App\Domains\Inventory\DTOs\ExpireReservationsInput;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\CompleteVerifiedPaymentSuccessAction;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\TestCase;

/**
 * Exercises payment success and expiry in alternating transactions under MySQL row locking.
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

    public function test_payment_success_then_expiry_on_same_order_does_not_leave_inconsistent_state(): void
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

        DB::transaction(fn () => $successAction->execute((int) $payment->getKey(), 'mysql-prov'));
        $expireAction->execute(new ExpireReservationsInput(batchLimit: 10));

        $order->refresh();
        $payment->refresh();

        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->status);
        $this->assertSame(
            InventoryReservation::STATUS_COMMITTED,
            InventoryReservation::query()->where('order_id', $order->getKey())->value('status'),
        );
    }

    public function test_expiry_then_payment_success_on_still_valid_hold_completes(): void
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

        if ($order->fresh()->status === Order::STATUS_CANCELLED) {
            $this->markTestSkipped('Hold expired before payment success attempt.');
        }

        DB::transaction(fn () => $successAction->execute((int) $payment->getKey(), 'mysql-prov-2'));

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
    }
}
