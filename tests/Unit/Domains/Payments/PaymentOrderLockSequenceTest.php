<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Actions\ExpireReservationsAction;
use App\Domains\Inventory\DTOs\ExpireReservationsInput;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Actions\CancelOrderAction;
use App\Domains\Payments\Actions\CompleteVerifiedPaymentSuccessAction;
use App\Domains\Payments\Actions\RecordPaymentFailureAction;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\Support\RecordsQueryTableOrder;
use Tests\TestCase;

class PaymentOrderLockSequenceTest extends TestCase
{
    use CreatesOrderPaymentFixtures;
    use RecordsQueryTableOrder;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);
    }

    public function test_payment_success_queries_order_before_payments(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $payment = $this->createPaymentForOrder($order);
        $action = $this->app->make(CompleteVerifiedPaymentSuccessAction::class);

        $tables = $this->recordSelectTableOrder(fn () => $action->execute((int) $payment->getKey(), 'prov-lock'));

        $this->assertLockOrderAfterOrderLock($tables, 'orders', 'payments');
        $this->assertLockOrderAfterOrderLock($tables, 'payments', 'carts');
        $this->assertCartRowLockBeforeReservationLockAfterOrderLock($tables);
    }

    public function test_payment_failure_queries_order_before_payments(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $payment = $this->createPaymentForOrder($order);
        $action = $this->app->make(RecordPaymentFailureAction::class);

        $tables = $this->recordSelectTableOrder(fn () => $action->execute((int) $payment->getKey(), 'DECLINED'));

        $this->assertLockOrderAfterOrderLock($tables, 'orders', 'payments');
    }

    public function test_order_linked_expiry_queries_payments_before_inventory_reservations(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $action = $this->app->make(ExpireReservationsAction::class);

        $tables = $this->recordSelectTableOrder(
            fn () => $action->execute(new ExpireReservationsInput(batchLimit: 10)),
        );

        $this->assertLockOrderAfterOrderLock($tables, 'orders', 'payments');
        $this->assertLockOrderAfterOrderLock($tables, 'payments', 'carts');
        $this->assertCartRowLockBeforeReservationLockAfterOrderLock($tables);
    }

    public function test_cancel_order_queries_payments_before_inventory_reservations(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $this->createPaymentForOrder($order);
        $admin = User::factory()->admin()->create();
        $action = $this->app->make(CancelOrderAction::class);

        $tables = $this->recordSelectTableOrder(
            fn () => $action->execute((int) $order->getKey(), (int) $admin->getKey()),
        );

        $this->assertLockOrderAfterOrderLock($tables, 'orders', 'payments');
        $this->assertLockOrderAfterOrderLock($tables, 'payments', 'carts');
        $this->assertCartRowLockBeforeReservationLockAfterOrderLock($tables);
    }

    public function test_pending_payments_cancelled_via_model_save_not_bulk_update(): void
    {
        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);
        $payment = $this->createPaymentForOrder($order);

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $updates = [];

        DB::listen(function ($query) use (&$updates): void {
            if (preg_match('/^update\s+[`"]?payments[`"]?/i', trim($query->sql)) === 1
                && ! str_contains(strtolower($query->sql), 'where "id" =')) {
                $updates[] = $query->sql;
            }
        });

        $this->app->make(ExpireReservationsAction::class)
            ->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame([], $updates);
        $this->assertSame(Payment::STATUS_CANCELLED, $payment->fresh()->status);
    }
}
