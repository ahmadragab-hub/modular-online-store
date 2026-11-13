<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Actions\ReserveStockForCartAction;
use App\Domains\Inventory\DTOs\ReserveStockForCartInput;
use App\Domains\Inventory\DTOs\ReserveStockForCartLineInput;
use App\Domains\Inventory\Exceptions\ActiveCartReservationMismatchException;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Exceptions\InvalidStockReservationRequestException;
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

class ReserveStockForCartActionTest extends TestCase
{
    use RefreshDatabase;

    private ReserveStockForCartAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);

        $this->action = $this->app->make(ReserveStockForCartAction::class);
    }

    private function createVariant(string $suffix = ''): ProductVariant
    {
        $product = Product::query()->create([
            'name' => 'Reserve Product '.$suffix,
            'slug' => 'reserve-product-'.$suffix.'-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        $size = Size::query()->create(['code' => 'R-'.$suffix, 'label' => 'Medium']);
        $color = Color::query()->create(['name' => 'Blue', 'slug' => 'blue-'.$suffix.'-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'RSV-'.$suffix.'-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '40.00',
        ]);
    }

    private function createLevel(ProductVariant $variant, int $onHand, int $reserved = 0): InventoryLevel
    {
        return InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
        ]);
    }

    private function createCart(): Cart
    {
        return Cart::query()->create([
            'session_token' => 'cart-'.uniqid(),
            'base_currency_code' => 'USD',
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * @param  list<ReserveStockForCartLineInput>  $lines
     */
    private function input(Cart $cart, array $lines, ?\DateTimeInterface $expiresAt = null): ReserveStockForCartInput
    {
        return new ReserveStockForCartInput(
            cartId: $cart->getKey(),
            lines: $lines,
            expiresAt: $expiresAt ?? now()->addMinutes(15),
        );
    }

    private function reserveInTransaction(ReserveStockForCartInput $input): mixed
    {
        return DB::transaction(fn () => $this->action->execute($input));
    }

    public function test_reserves_single_variant_successfully(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('one');
        $level = $this->createLevel($variant, 10, 2);

        $result = $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 3),
        ]));

        $this->assertFalse($result->reusedExistingReservations);
        $this->assertCount(1, $result->lines);
        $this->assertSame(3, $result->lines[0]->quantity);

        $level->refresh();
        $this->assertSame(5, $level->quantity_reserved);
        $this->assertSame(10, $level->quantity_on_hand);

        $reservation = InventoryReservation::query()->findOrFail($result->lines[0]->inventoryReservationId);
        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->status);
        $this->assertSame($cart->getKey(), $reservation->cart_id);
        $this->assertNull($reservation->order_id);

        $movement = InventoryMovement::query()->where('reference_id', $reservation->getKey())->firstOrFail();
        $this->assertSame(InventoryMovement::TYPE_RESERVATION_HOLD, $movement->type);
        $this->assertSame(0, $movement->quantity_delta);
        $this->assertSame(10, $movement->quantity_on_hand_after);
        $this->assertSame('reservation', $movement->reference_type);
    }

    public function test_reserves_multiple_variants_in_id_order(): void
    {
        $cart = $this->createCart();
        $variantA = $this->createVariant('a');
        $variantB = $this->createVariant('b');
        $this->createLevel($variantA, 8);
        $this->createLevel($variantB, 8);

        $result = $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variantB->getKey(), 2),
            new ReserveStockForCartLineInput($variantA->getKey(), 4),
        ]));

        $variantIds = array_map(fn ($line) => $line->productVariantId, $result->lines);
        $this->assertSame([
            $variantA->getKey(),
            $variantB->getKey(),
        ], $variantIds);
    }

    public function test_exact_available_stock_boundary_succeeds(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('boundary');
        $this->createLevel($variant, 5, 2);

        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 3),
        ]));

        $this->assertSame(5, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
    }

    public function test_insufficient_stock_throws_and_rolls_back(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('low');
        $this->createLevel($variant, 5, 4);

        try {
            DB::transaction(fn () => $this->action->execute($this->input($cart, [
                new ReserveStockForCartLineInput($variant->getKey(), 2),
            ])));
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(1, $exception->availableQuantity);
        }

        $this->assertSame(0, InventoryReservation::query()->count());
        $this->assertSame(4, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_multi_variant_failure_rolls_back_entire_operation(): void
    {
        $cart = $this->createCart();
        $ok = $this->createVariant('ok');
        $bad = $this->createVariant('bad');
        $this->createLevel($ok, 10);
        $this->createLevel($bad, 1, 1);

        try {
            DB::transaction(fn () => $this->action->execute($this->input($cart, [
                new ReserveStockForCartLineInput($ok->getKey(), 2),
                new ReserveStockForCartLineInput($bad->getKey(), 1),
            ])));
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame(0, InventoryReservation::query()->count());
        $this->assertSame(0, InventoryLevel::query()->where('product_variant_id', $ok->getKey())->value('quantity_reserved'));
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_identical_repeat_request_reuses_active_reservations(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('idem');
        $this->createLevel($variant, 10);

        $first = $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 2),
        ]));

        $second = $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 2),
        ]));

        $this->assertFalse($first->reusedExistingReservations);
        $this->assertTrue($second->reusedExistingReservations);
        $this->assertSame($first->lines[0]->inventoryReservationId, $second->lines[0]->inventoryReservationId);
        $this->assertSame(1, InventoryReservation::query()->where('cart_id', $cart->getKey())->count());
        $this->assertSame(2, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_changed_quantities_with_active_reservations_rejected(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('change');
        $this->createLevel($variant, 10);
        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 2),
        ]));

        $this->expectException(ActiveCartReservationMismatchException::class);

        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 3),
        ]));
    }

    public function test_different_variant_set_with_active_reservations_rejected(): void
    {
        $cart = $this->createCart();
        $variantOne = $this->createVariant('v1');
        $variantTwo = $this->createVariant('v2');
        $this->createLevel($variantOne, 10);
        $this->createLevel($variantTwo, 10);

        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variantOne->getKey(), 1),
        ]));

        $this->expectException(ActiveCartReservationMismatchException::class);

        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variantOne->getKey(), 1),
            new ReserveStockForCartLineInput($variantTwo->getKey(), 1),
        ]));
    }

    public function test_new_reserve_after_released_reservations(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('released');
        $this->createLevel($variant, 10, 0);

        InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'status' => InventoryReservation::STATUS_RELEASED,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->addMinutes(10),
            'released_at' => now(),
        ]);

        $result = $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 2),
        ]));

        $this->assertFalse($result->reusedExistingReservations);
        $this->assertSame(2, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
    }

    public function test_new_reserve_after_expired_reservations(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('expired');
        $this->createLevel($variant, 8);

        InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'status' => InventoryReservation::STATUS_EXPIRED,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->subMinute(),
            'released_at' => now(),
        ]);

        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 2),
        ]));

        $this->assertSame(2, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
    }

    public function test_new_reserve_allowed_after_committed_reservations(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('committed');
        $this->createLevel($variant, 10, 0);

        InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'status' => InventoryReservation::STATUS_COMMITTED,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->addMinutes(10),
            'committed_at' => now(),
        ]);

        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 1),
        ]));

        $this->assertSame(1, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
        $this->assertSame(1, InventoryReservation::query()->where('status', InventoryReservation::STATUS_ACTIVE)->count());
    }

    public function test_rejects_invalid_quantities_and_duplicate_variants(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('invalid');

        $this->expectException(InvalidStockReservationRequestException::class);
        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 0),
        ]));
    }

    public function test_rejects_duplicate_variant_lines(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('dup');
        $this->createLevel($variant, 5);

        $this->expectException(InvalidStockReservationRequestException::class);
        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 1),
            new ReserveStockForCartLineInput($variant->getKey(), 2),
        ]));
    }

    public function test_missing_inventory_level_fails_without_partial_state(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('missing');

        try {
            $this->reserveInTransaction($this->input($cart, [
                new ReserveStockForCartLineInput($variant->getKey(), 1),
            ]));
            $this->fail('Expected MissingInventoryLevelException.');
        } catch (MissingInventoryLevelException) {
            // expected
        }

        $this->assertSame(0, InventoryReservation::query()->count());
    }

    public function test_rejects_past_expiry(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('exp');
        $this->createLevel($variant, 5);

        $this->expectException(InvalidStockReservationRequestException::class);
        $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 1),
        ], now()->subMinute()));
    }

    public function test_requires_active_database_transaction(): void
    {
        DB::partialMock()->shouldReceive('transactionLevel')->once()->andReturn(0);

        $cart = $this->createCart();
        $variant = $this->createVariant('tx');
        $this->createLevel($variant, 5);

        $this->expectException(RequiresActiveDatabaseTransactionException::class);

        $this->action->execute($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 1),
        ]));
    }

    public function test_rejects_missing_cart(): void
    {
        $variant = $this->createVariant('nocart');
        $this->createLevel($variant, 5);

        $this->expectException(InvalidStockReservationRequestException::class);

        $this->reserveInTransaction(new ReserveStockForCartInput(
            cartId: 999_999,
            lines: [new ReserveStockForCartLineInput($variant->getKey(), 1)],
            expiresAt: now()->addMinutes(15),
        ));
    }

    public function test_rejects_inactive_cart(): void
    {
        $cart = $this->createCart();
        $cart->update(['status' => Cart::STATUS_CONVERTED]);
        $variant = $this->createVariant('inactive');
        $this->createLevel($variant, 5);

        try {
            $this->reserveInTransaction($this->input($cart, [
                new ReserveStockForCartLineInput($variant->getKey(), 1),
            ]));
            $this->fail('Expected InvalidStockReservationRequestException.');
        } catch (InvalidStockReservationRequestException $exception) {
            $this->assertSame(InvalidStockReservationRequestException::CART_NOT_ACTIVE, $exception->errorCode);
        }
    }

    public function test_rejects_expired_cart(): void
    {
        $cart = $this->createCart();
        $cart->update(['expires_at' => now()->subMinute()]);
        $variant = $this->createVariant('cartexp');
        $this->createLevel($variant, 5);

        try {
            $this->reserveInTransaction($this->input($cart, [
                new ReserveStockForCartLineInput($variant->getKey(), 1),
            ]));
            $this->fail('Expected InvalidStockReservationRequestException.');
        } catch (InvalidStockReservationRequestException $exception) {
            $this->assertSame(InvalidStockReservationRequestException::CART_EXPIRED, $exception->errorCode);
        }
    }

    public function test_rejects_stale_active_reservations_on_cart(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('stale');
        $this->createLevel($variant, 10, 1);

        InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->subMinute(),
        ]);

        try {
            $this->reserveInTransaction($this->input($cart, [
                new ReserveStockForCartLineInput($variant->getKey(), 1),
            ]));
            $this->fail('Expected InvalidStockReservationRequestException.');
        } catch (InvalidStockReservationRequestException $exception) {
            $this->assertSame(InvalidStockReservationRequestException::STALE_ACTIVE_RESERVATIONS, $exception->errorCode);
        }

        $this->assertSame(1, InventoryReservation::query()->where('status', InventoryReservation::STATUS_ACTIVE)->count());
    }

    private function createMinimalOrder(): Order
    {
        return Order::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.uniqid(),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'base_currency_code' => 'USD',
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'tax_total' => '0.0000',
            'shipping_total' => '0.0000',
            'grand_total' => '10.0000',
            'shipping_address' => ['line1' => '1 St', 'city' => 'Cairo', 'country_code' => 'EG'],
            'billing_address' => ['line1' => '1 St', 'city' => 'Cairo', 'country_code' => 'EG'],
            'idempotency_key' => hash('sha256', Str::random(32)),
        ]);
    }

    public function test_order_linked_active_reservations_cannot_be_reused(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('linked');
        $level = $this->createLevel($variant, 10, 2);
        $order = $this->createMinimalOrder();

        $reservation = InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'order_id' => $order->getKey(),
            'expires_at' => now()->addMinutes(15),
        ]);

        try {
            $this->reserveInTransaction($this->input($cart, [
                new ReserveStockForCartLineInput($variant->getKey(), 2),
            ]));
            $this->fail('Expected InvalidStockReservationRequestException.');
        } catch (InvalidStockReservationRequestException $exception) {
            $this->assertSame(
                InvalidStockReservationRequestException::ACTIVE_RESERVATIONS_LINKED_TO_ORDER,
                $exception->errorCode,
            );
        }

        $reservation->refresh();
        $this->assertSame($order->getKey(), $reservation->order_id);
        $this->assertSame(2, $level->fresh()->quantity_reserved);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame(1, InventoryReservation::query()->where('cart_id', $cart->getKey())->count());
    }

    public function test_cart_only_active_reservations_with_null_order_id_still_reused(): void
    {
        $cart = $this->createCart();
        $variant = $this->createVariant('cart-only');
        $this->createLevel($variant, 10);

        InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'order_id' => null,
            'expires_at' => now()->addMinutes(15),
        ]);
        InventoryLevel::query()->where('product_variant_id', $variant->getKey())->update(['quantity_reserved' => 2]);

        $result = $this->reserveInTransaction($this->input($cart, [
            new ReserveStockForCartLineInput($variant->getKey(), 2),
        ]));

        $this->assertTrue($result->reusedExistingReservations);
        $this->assertSame(1, InventoryReservation::query()->where('cart_id', $cart->getKey())->where('status', InventoryReservation::STATUS_ACTIVE)->count());
    }
}
