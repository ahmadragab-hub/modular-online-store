<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Actions\ReleaseReservationAction;
use App\Domains\Inventory\DTOs\ReleaseReservationInput;
use App\Domains\Inventory\Exceptions\ExpiredInventoryReservationException;
use App\Domains\Inventory\Exceptions\InvalidInventoryReservationStateException;
use App\Domains\Inventory\Exceptions\InvalidStockReservationRequestException;
use App\Domains\Inventory\Exceptions\InventoryReservationNotFoundException;
use App\Domains\Inventory\Exceptions\MissingInventoryLevelException;
use App\Domains\Inventory\Exceptions\RequiresActiveDatabaseTransactionException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReleaseReservationActionTest extends TestCase
{
    use RefreshDatabase;

    private ReleaseReservationAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);

        $this->action = $this->app->make(ReleaseReservationAction::class);
    }

    private function createVariant(): ProductVariant
    {
        $product = Product::query()->create([
            'name' => 'Release Product',
            'slug' => 'release-product-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        $size = Size::query()->create(['code' => 'R', 'label' => 'Release']);
        $color = Color::query()->create(['name' => 'Green', 'slug' => 'green-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'REL-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '30.00',
        ]);
    }

    private function createCart(): Cart
    {
        return Cart::query()->create([
            'session_token' => 'rel-'.uniqid(),
            'base_currency_code' => 'USD',
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * @return array{reservation: InventoryReservation, level: InventoryLevel}
     */
    private function createActiveReservation(
        int $onHand = 10,
        int $reserved = 3,
        int $holdQty = 3,
    ): array {
        $cart = $this->createCart();
        $variant = $this->createVariant();

        $level = InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
        ]);

        $reservation = InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => $holdQty,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->addMinutes(30),
        ]);

        return ['reservation' => $reservation, 'level' => $level];
    }

    private function releaseInTransaction(int $reservationId): mixed
    {
        return DB::transaction(fn () => $this->action->execute(new ReleaseReservationInput($reservationId)));
    }

    public function test_releases_active_reservation_and_updates_reserved_counter_only(): void
    {
        ['reservation' => $reservation, 'level' => $level] = $this->createActiveReservation();

        $onHandBefore = $level->quantity_on_hand;

        $result = $this->releaseInTransaction($reservation->getKey());

        $this->assertFalse($result->idempotentReplay);
        $this->assertSame($onHandBefore, $result->quantityOnHandAfter);

        $level->refresh();
        $this->assertSame($onHandBefore, $level->quantity_on_hand);
        $this->assertSame(0, $level->quantity_reserved);

        $reservation->refresh();
        $this->assertSame(InventoryReservation::STATUS_RELEASED, $reservation->status);
        $this->assertNotNull($reservation->released_at);

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_RESERVATION_RELEASE)
            ->where('reference_id', $reservation->getKey())
            ->firstOrFail();

        $this->assertSame(0, $movement->quantity_delta);
        $this->assertSame($onHandBefore, $movement->quantity_on_hand_after);
        $this->assertSame('reservation', $movement->reference_type);
    }

    public function test_repeated_release_is_idempotent(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();

        $first = $this->releaseInTransaction($reservation->getKey());
        $second = $this->releaseInTransaction($reservation->getKey());

        $this->assertFalse($first->idempotentReplay);
        $this->assertTrue($second->idempotentReplay);
        $this->assertSame(1, InventoryMovement::query()->where('type', InventoryMovement::TYPE_RESERVATION_RELEASE)->count());
        $this->assertSame(0, InventoryLevel::query()->where('product_variant_id', $reservation->product_variant_id)->value('quantity_reserved'));
    }

    public function test_already_expired_status_is_idempotent_no_op(): void
    {
        ['reservation' => $reservation, 'level' => $level] = $this->createActiveReservation();
        $level->update(['quantity_reserved' => 0]);
        $reservation->update([
            'status' => InventoryReservation::STATUS_EXPIRED,
            'released_at' => now(),
        ]);

        $result = $this->releaseInTransaction($reservation->getKey());

        $this->assertTrue($result->idempotentReplay);
        $this->assertSame(InventoryReservation::STATUS_EXPIRED, $reservation->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_rejects_committed_reservation(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();
        $reservation->update([
            'status' => InventoryReservation::STATUS_COMMITTED,
            'committed_at' => now(),
        ]);

        $this->expectException(InvalidInventoryReservationStateException::class);
        $this->releaseInTransaction($reservation->getKey());
    }

    public function test_rejects_active_reservation_past_expiry_time(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();
        $reservation->update(['expires_at' => now()->subMinute()]);

        try {
            $this->releaseInTransaction($reservation->getKey());
            $this->fail('Expected ExpiredInventoryReservationException.');
        } catch (ExpiredInventoryReservationException $exception) {
            $this->assertSame('release', $exception->operation);
        }

        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_rejects_missing_reservation(): void
    {
        $this->expectException(InventoryReservationNotFoundException::class);
        $this->releaseInTransaction(999_999);
    }

    public function test_rejects_missing_inventory_level(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant();

        $reservation = InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->addHour(),
        ]);

        $this->expectException(MissingInventoryLevelException::class);
        $this->releaseInTransaction($reservation->getKey());
    }

    public function test_rejects_insufficient_reserved_counter(): void
    {
        ['reservation' => $reservation, 'level' => $level] = $this->createActiveReservation(
            onHand: 10,
            reserved: 1,
            holdQty: 3,
        );

        try {
            $this->releaseInTransaction($reservation->getKey());
            $this->fail('Expected InvalidStockReservationRequestException.');
        } catch (InvalidStockReservationRequestException $exception) {
            $this->assertSame(
                InvalidStockReservationRequestException::INVENTORY_COUNTERS_INCONSISTENT,
                $exception->errorCode,
            );
        }

        $reservation->refresh();
        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->status);
        $level->refresh();
        $this->assertSame(1, $level->quantity_reserved);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_rejects_invalid_reservation_quantity(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();
        $reservation->update(['quantity' => 0]);

        $this->expectException(InvalidStockReservationRequestException::class);
        $this->releaseInTransaction($reservation->getKey());
    }

    public function test_requires_outer_transaction(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();

        DB::partialMock()->shouldReceive('transactionLevel')->once()->andReturn(0);

        $this->expectException(RequiresActiveDatabaseTransactionException::class);

        $this->action->execute(new ReleaseReservationInput($reservation->getKey()));
    }

    public function test_failure_rolls_back_state(): void
    {
        ['reservation' => $reservation, 'level' => $level] = $this->createActiveReservation(
            onHand: 10,
            reserved: 1,
            holdQty: 3,
        );

        try {
            DB::transaction(fn () => $this->action->execute(new ReleaseReservationInput($reservation->getKey())));
            $this->fail('Expected InvalidStockReservationRequestException.');
        } catch (InvalidStockReservationRequestException) {
            // expected
        }

        $reservation->refresh();
        $level->refresh();

        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->status);
        $this->assertNull($reservation->released_at);
        $this->assertSame(1, $level->quantity_reserved);
        $this->assertSame(0, InventoryMovement::query()->count());
    }
}
