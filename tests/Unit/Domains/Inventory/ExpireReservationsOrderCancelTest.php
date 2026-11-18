<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Inventory\Actions\ExpireReservationsAction;
use App\Domains\Inventory\DTOs\ExpireReservationsInput;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\TestCase;

class ExpireReservationsOrderCancelTest extends TestCase
{
    use CreatesOrderPaymentFixtures;
    use RefreshDatabase;

    public function test_expiring_last_hold_cancels_unpaid_order(): void
    {
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);

        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $this->app->make(ExpireReservationsAction::class)
            ->execute(new ExpireReservationsInput(batchLimit: 10));

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_multi_line_order_not_cancelled_after_first_reservation_expires(): void
    {
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);

        $variantA = $this->createCatalogVariant('A');
        $variantB = $this->createCatalogVariant('B');
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variantA, 'quantity' => 1],
            ['variant' => $variantB, 'quantity' => 1],
        ]);

        InventoryReservation::query()
            ->where('order_id', $order->getKey())
            ->where('product_variant_id', $variantA->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $this->app->make(ExpireReservationsAction::class)
            ->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_payment_failed_order_cancelled_after_all_holds_expire(): void
    {
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);

        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(
            orderStatus: Order::STATUS_PAYMENT_FAILED,
            lines: [['variant' => $variant, 'quantity' => 1]],
        );
        $payment = $this->createPaymentForOrder($order);

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $this->app->make(ExpireReservationsAction::class)
            ->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_CANCELLED, $payment->fresh()->status);
    }

    public function test_expiry_skips_paid_order_with_stray_active_hold(): void
    {
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);

        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(
            orderStatus: Order::STATUS_PROCESSING,
            lines: [['variant' => $variant, 'quantity' => 1]],
        );
        $order->update(['paid_at' => now()]);

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $this->app->make(ExpireReservationsAction::class)
            ->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(
            InventoryReservation::STATUS_ACTIVE,
            InventoryReservation::query()->where('order_id', $order->getKey())->value('status'),
        );
    }

    public function test_second_expiry_run_is_idempotent(): void
    {
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);

        $variant = $this->createCatalogVariant();
        $order = $this->createUnpaidOrderWithLines(lines: [
            ['variant' => $variant, 'quantity' => 1],
        ]);

        InventoryReservation::query()->where('order_id', $order->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        $action = $this->app->make(ExpireReservationsAction::class);
        $action->execute(new ExpireReservationsInput(batchLimit: 10));
        $action->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(1, InventoryReservation::query()->where('order_id', $order->getKey())->count());
    }
}
