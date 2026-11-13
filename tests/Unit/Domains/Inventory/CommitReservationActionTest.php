<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Actions\CommitReservationAction;
use App\Domains\Inventory\DTOs\CommitReservationInput;
use App\Domains\Inventory\Exceptions\ExpiredInventoryReservationException;
use App\Domains\Inventory\Exceptions\InvalidInventoryReservationStateException;
use App\Domains\Inventory\Exceptions\InvalidStockReservationRequestException;
use App\Domains\Inventory\Exceptions\InventoryReservationNotFoundException;
use App\Domains\Inventory\Exceptions\MissingInventoryLevelException;
use App\Domains\Inventory\Exceptions\RequiresActiveDatabaseTransactionException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommitReservationActionTest extends TestCase
{
    use RefreshDatabase;

    private CommitReservationAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);

        $this->action = $this->app->make(CommitReservationAction::class);
    }

    private function createVariant(): ProductVariant
    {
        $product = Product::query()->create([
            'name' => 'Commit Product',
            'slug' => 'commit-product-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        $size = Size::query()->create(['code' => 'C', 'label' => 'Commit']);
        $color = Color::query()->create(['name' => 'Gray', 'slug' => 'gray-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'CMT-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '50.00',
        ]);
    }

    private function createCart(): Cart
    {
        return Cart::query()->create([
            'session_token' => 'commit-'.uniqid(),
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
        ?int $orderId = null,
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
            'order_id' => $orderId,
            'expires_at' => now()->addMinutes(30),
        ]);

        return ['reservation' => $reservation, 'level' => $level];
    }

    private function commitInTransaction(int $reservationId): mixed
    {
        return DB::transaction(fn () => $this->action->execute(new CommitReservationInput($reservationId)));
    }

    public function test_commits_active_reservation_and_updates_counters(): void
    {
        ['reservation' => $reservation, 'level' => $level] = $this->createActiveReservation(
            onHand: 10,
            reserved: 3,
            holdQty: 3,
        );

        $result = $this->commitInTransaction($reservation->getKey());

        $this->assertFalse($result->idempotentReplay);
        $this->assertSame(3, $result->quantity);
        $this->assertSame(7, $result->quantityOnHandAfter);

        $level->refresh();
        $this->assertSame(7, $level->quantity_on_hand);
        $this->assertSame(0, $level->quantity_reserved);

        $reservation->refresh();
        $this->assertSame(InventoryReservation::STATUS_COMMITTED, $reservation->status);
        $this->assertNotNull($reservation->committed_at);

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_SALE_COMMIT)
            ->where('reference_type', 'reservation')
            ->where('reference_id', $reservation->getKey())
            ->firstOrFail();

        $this->assertSame(-3, $movement->quantity_delta);
        $this->assertSame(7, $movement->quantity_on_hand_after);
    }

    public function test_sale_commit_references_order_when_order_id_set(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Buyer',
            'last_name' => 'Test',
        ]);
        $order = Order::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'base_currency_code' => 'USD',
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '100.0000',
            'discount_total' => '0.0000',
            'tax_total' => '0.0000',
            'shipping_total' => '0.0000',
            'grand_total' => '100.0000',
            'shipping_address' => ['line1' => '1 St'],
            'billing_address' => ['line1' => '1 St'],
            'idempotency_key' => hash('sha256', Str::random(32)),
        ]);

        ['reservation' => $reservation] = $this->createActiveReservation(
            onHand: 8,
            reserved: 2,
            holdQty: 2,
            orderId: $order->getKey(),
        );

        $this->commitInTransaction($reservation->getKey());

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_SALE_COMMIT)
            ->firstOrFail();

        $this->assertSame('order', $movement->reference_type);
        $this->assertSame($order->getKey(), $movement->reference_id);
    }

    public function test_repeated_commit_is_idempotent(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();

        $first = $this->commitInTransaction($reservation->getKey());
        $second = $this->commitInTransaction($reservation->getKey());

        $this->assertFalse($first->idempotentReplay);
        $this->assertTrue($second->idempotentReplay);
        $this->assertSame(1, InventoryMovement::query()->where('type', InventoryMovement::TYPE_SALE_COMMIT)->count());

        $level = InventoryLevel::query()->where('product_variant_id', $reservation->product_variant_id)->first();
        $this->assertSame(7, $level->quantity_on_hand);
        $this->assertSame(0, $level->quantity_reserved);
    }

    public function test_rejects_released_reservation(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();
        $reservation->update([
            'status' => InventoryReservation::STATUS_RELEASED,
            'released_at' => now(),
        ]);

        $this->expectException(InvalidInventoryReservationStateException::class);
        $this->commitInTransaction($reservation->getKey());
    }

    public function test_rejects_expired_status_reservation(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();
        $reservation->update([
            'status' => InventoryReservation::STATUS_EXPIRED,
            'released_at' => now(),
        ]);

        $this->expectException(InvalidInventoryReservationStateException::class);
        $this->commitInTransaction($reservation->getKey());
    }

    public function test_rejects_active_reservation_past_expiry_time(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();
        $reservation->update(['expires_at' => now()->subMinute()]);

        $this->expectException(ExpiredInventoryReservationException::class);
        $this->commitInTransaction($reservation->getKey());
    }

    public function test_rejects_missing_reservation(): void
    {
        $this->expectException(InventoryReservationNotFoundException::class);
        $this->commitInTransaction(999_999);
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
        $this->commitInTransaction($reservation->getKey());
    }

    public function test_rejects_insufficient_reserved_counter(): void
    {
        ['reservation' => $reservation, 'level' => $level] = $this->createActiveReservation(
            onHand: 10,
            reserved: 1,
            holdQty: 3,
        );

        try {
            $this->commitInTransaction($reservation->getKey());
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
        $this->commitInTransaction($reservation->getKey());
    }

    public function test_requires_outer_transaction(): void
    {
        ['reservation' => $reservation] = $this->createActiveReservation();

        DB::partialMock()->shouldReceive('transactionLevel')->once()->andReturn(0);

        $this->expectException(RequiresActiveDatabaseTransactionException::class);

        $this->action->execute(new CommitReservationInput($reservation->getKey()));
    }

    public function test_failure_rolls_back_state(): void
    {
        ['reservation' => $reservation, 'level' => $level] = $this->createActiveReservation(
            onHand: 2,
            reserved: 3,
            holdQty: 3,
        );

        try {
            DB::transaction(fn () => $this->action->execute(new CommitReservationInput($reservation->getKey())));
            $this->fail('Expected InvalidStockReservationRequestException.');
        } catch (InvalidStockReservationRequestException) {
            // expected — on_hand < quantity
        }

        $reservation->refresh();
        $level->refresh();

        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->status);
        $this->assertNull($reservation->committed_at);
        $this->assertSame(2, $level->quantity_on_hand);
        $this->assertSame(3, $level->quantity_reserved);
        $this->assertSame(0, InventoryMovement::query()->count());
    }
}
