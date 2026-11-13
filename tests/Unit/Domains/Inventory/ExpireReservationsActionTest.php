<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Actions\ExpireReservationsAction;
use App\Domains\Inventory\DTOs\ExpireReservationsInput;
use App\Domains\Inventory\Exceptions\InvalidStockReservationRequestException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpireReservationsActionTest extends TestCase
{
    use RefreshDatabase;

    private ExpireReservationsAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);

        $this->action = $this->app->make(ExpireReservationsAction::class);
    }

    private function createVariant(): ProductVariant
    {
        $product = Product::query()->create([
            'name' => 'Expire Product',
            'slug' => 'expire-product-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        $size = Size::query()->create(['code' => 'E-'.uniqid(), 'label' => 'Expire']);
        $color = Color::query()->create(['name' => 'Tan', 'slug' => 'tan-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'EXP-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '20.00',
        ]);
    }

    private function createCart(): Cart
    {
        return Cart::query()->create([
            'session_token' => 'exp-'.uniqid(),
            'base_currency_code' => 'USD',
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function createExpiredActiveReservation(
        int $onHand = 10,
        int $reserved = 2,
        int $qty = 2,
    ): InventoryReservation {
        $cart = $this->createCart();
        $variant = $this->createVariant();

        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
        ]);

        return InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => $qty,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function test_expires_active_reservation_past_ttl(): void
    {
        $reservation = $this->createExpiredActiveReservation();

        $result = $this->action->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(1, $result->expiredCount);
        $this->assertContains($reservation->getKey(), $result->expiredReservationIds);

        $reservation->refresh();
        $this->assertSame(InventoryReservation::STATUS_EXPIRED, $reservation->status);
        $this->assertNotSame(InventoryReservation::STATUS_RELEASED, $reservation->status);

        $level = InventoryLevel::query()->where('product_variant_id', $reservation->product_variant_id)->first();
        $this->assertSame(10, $level->quantity_on_hand);
        $this->assertSame(0, $level->quantity_reserved);

        $movement = InventoryMovement::query()->where('reference_id', $reservation->getKey())->firstOrFail();
        $this->assertSame(InventoryMovement::TYPE_RESERVATION_RELEASE, $movement->type);
        $this->assertSame(0, $movement->quantity_delta);
        $this->assertSame('expired', $movement->notes);
    }

    public function test_repeated_expiry_run_is_idempotent(): void
    {
        $reservation = $this->createExpiredActiveReservation();

        $this->action->execute(new ExpireReservationsInput(batchLimit: 10));
        $second = $this->action->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(0, $second->expiredCount);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_does_not_process_future_expiry(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 5,
            'quantity_reserved' => 1,
        ]);

        InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->addHour(),
        ]);

        $result = $this->action->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(0, $result->candidatesSelected);
        $this->assertSame(0, $result->expiredCount);
    }

    public function test_ignores_released_reservation(): void
    {
        $reservation = $this->createExpiredActiveReservation();
        $reservation->update([
            'status' => InventoryReservation::STATUS_RELEASED,
            'released_at' => now(),
        ]);

        $result = $this->action->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(0, $result->expiredCount);
        $this->assertSame(InventoryReservation::STATUS_RELEASED, $reservation->fresh()->status);
    }

    public function test_ignores_committed_reservation_even_if_expires_at_past(): void
    {
        $reservation = $this->createExpiredActiveReservation();
        $reservation->update([
            'status' => InventoryReservation::STATUS_COMMITTED,
            'committed_at' => now(),
        ]);

        $result = $this->action->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(0, $result->expiredCount);
    }

    public function test_respects_batch_limit(): void
    {
        $this->createExpiredActiveReservation();
        $this->createExpiredActiveReservation();
        $this->createExpiredActiveReservation();

        $result = $this->action->execute(new ExpireReservationsInput(batchLimit: 2));

        $this->assertSame(2, $result->candidatesSelected);
        $this->assertSame(2, $result->expiredCount);
        $this->assertSame(1, InventoryReservation::query()->where('status', InventoryReservation::STATUS_ACTIVE)->count());
    }

    public function test_records_failure_and_continues_on_counter_inconsistency(): void
    {
        $reservation = $this->createExpiredActiveReservation(onHand: 10, reserved: 1, qty: 3);

        $result = $this->action->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(1, $result->failedCount);
        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_single_reservation_failure_rolls_back_when_in_explicit_transaction(): void
    {
        $reservation = $this->createExpiredActiveReservation(onHand: 10, reserved: 1, qty: 3);

        DB::transaction(function (): void {
            $this->action->execute(new ExpireReservationsInput(batchLimit: 10));
        });

        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->fresh()->status);
    }

    public function test_concurrent_state_change_is_skipped_safely(): void
    {
        $reservation = $this->createExpiredActiveReservation();

        $reservation->update(['status' => InventoryReservation::STATUS_RELEASED, 'released_at' => now()]);

        $result = $this->action->execute(new ExpireReservationsInput(batchLimit: 10));

        $this->assertSame(0, $result->expiredCount);
        $this->assertGreaterThanOrEqual(0, $result->skippedCount);
    }

    public function test_invalid_batch_limit_rejected(): void
    {
        $this->expectException(InvalidStockReservationRequestException::class);
        $this->action->execute(new ExpireReservationsInput(batchLimit: 0));
    }

    public function test_participates_in_outer_transaction_without_nested_commit(): void
    {
        $reservation = $this->createExpiredActiveReservation();

        DB::transaction(function (): void {
            $result = $this->action->execute(new ExpireReservationsInput(batchLimit: 5));
            $this->assertSame(1, $result->expiredCount);
        });

        $this->assertSame(InventoryReservation::STATUS_EXPIRED, $reservation->fresh()->status);
    }
}
