<?php

namespace Tests\Unit\Domains\Checkout;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Actions\PlaceOrderAction;
use App\Domains\Checkout\Exceptions\CheckoutPreparationException;
use App\Domains\Checkout\Exceptions\PlaceOrderException;
use App\Domains\Checkout\Exceptions\PlaceOrderIdempotencyConflictException;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Payments\Models\Payment;
use App\Domains\Store\Models\StoreSetting;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsCheckoutPlaceOrderFixtures;
use Tests\TestCase;

class PlaceOrderActionTest extends TestCase
{
    use BuildsCheckoutPlaceOrderFixtures;
    use RefreshDatabase;

    private PlaceOrderAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);

        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => false,
        ]);

        $this->action = $this->app->make(PlaceOrderAction::class);
    }

    public function test_successful_order_creation(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'place-'.Str::random(16));

        $result = $this->action->execute($this->placeOrderInput($cart, $idempotencyKey));

        $this->assertFalse($result->idempotentReplay);
        $this->assertSame('100.0000', $result->grandTotal);
        $order = Order::query()->whereKey($result->orderId)->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertNotNull($order->placed_at);
    }

    public function test_multiple_order_items_and_snapshots(): void
    {
        ['variant' => $variantA] = $this->createPublishedSellableVariant('40.00', 10, 'a');
        ['variant' => $variantB] = $this->createPublishedSellableVariant('60.00', 10, 'b');
        $cart = $this->createGuestCartWithItem($variantA, 2);
        $cart->items()->create([
            'product_variant_id' => $variantB->getKey(),
            'quantity' => 1,
            'unit_price_base' => '60.00',
            'product_name' => 'Second',
            'sku' => $variantB->sku,
            'variant_label' => 'M / Green',
        ]);

        $result = $this->action->execute($this->placeOrderInput($cart, hash('sha256', 'multi-'.uniqid())));

        $this->assertCount(2, OrderItem::query()->where('order_id', $result->orderId)->get());
        $this->assertSame(1, OrderStatusHistory::query()->where('order_id', $result->orderId)->count());
    }

    public function test_reservations_linked_and_counters_reserved(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant(stock: 5);
        $cart = $this->createGuestCartWithItem($variant, 2);
        $levelId = InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('id');

        $result = $this->action->execute($this->placeOrderInput($cart, hash('sha256', 'reserve-'.uniqid())));

        $reservation = InventoryReservation::query()->where('order_id', $result->orderId)->first();
        $this->assertNotNull($reservation);
        $this->assertSame(InventoryReservation::STATUS_ACTIVE, $reservation->status);
        $this->assertSame($cart->getKey(), $reservation->cart_id);
        $this->assertSame(2, (int) InventoryLevel::query()->whereKey($levelId)->value('quantity_reserved'));
        $this->assertTrue(InventoryMovement::query()->where('type', InventoryMovement::TYPE_RESERVATION_HOLD)->exists());
    }

    public function test_initial_pending_payment_created(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'pay-'.uniqid());

        $result = $this->action->execute($this->placeOrderInput($cart, $idempotencyKey));

        $payment = Payment::query()->whereKey($result->paymentId)->first();
        $this->assertNotNull($payment);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(1, $payment->attempt_number);
        $this->assertSame('100.0000', (string) $payment->amount);
        $this->assertSame(hash('sha256', 'payment:'.$idempotencyKey.':attempt:1'), $payment->idempotency_key);
    }

    public function test_frozen_currency_and_fx_on_egp_order(): void
    {
        $this->seedRate('EGP', '48.50000000');
        ['variant' => $variant] = $this->createPublishedSellableVariant('100.00');
        $cart = $this->createGuestCartWithItem($variant);

        $result = $this->action->execute($this->placeOrderInput(
            $cart,
            hash('sha256', 'egp-'.uniqid()),
            currency: 'EGP',
        ));

        $order = Order::query()->whereKey($result->orderId)->firstOrFail();
        $this->assertSame('USD', $order->base_currency_code);
        $this->assertSame('EGP', $order->currency_code);
        $this->assertSame('48.50000000', (string) $order->exchange_rate);
        $this->assertNotNull($order->currency_exchange_rate_id);
    }

    public function test_cart_is_converted_after_success(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->action->execute($this->placeOrderInput($cart, hash('sha256', 'active-'.uniqid())));

        $fresh = $cart->fresh();
        $this->assertSame(Cart::STATUS_CONVERTED, $fresh->status);
        $this->assertNull($fresh->session_token);
    }

    public function test_insufficient_stock_rolls_back_persisted_rows(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant(stock: 1);
        $cart = $this->createGuestCartWithItem($variant, 2);

        try {
            $this->action->execute($this->placeOrderInput($cart, hash('sha256', 'stock-'.uniqid())));
            $this->fail('Expected CheckoutPreparationException.');
        } catch (CheckoutPreparationException) {
            $this->assertSame(0, Order::query()->count());
            $this->assertSame(0, Payment::query()->count());
            $this->assertSame(0, InventoryReservation::query()->count());
        }
    }

    public function test_missing_fx_rejects_place_order(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);

        $this->action->execute($this->placeOrderInput(
            $cart,
            hash('sha256', 'fx-'.uniqid()),
            currency: 'EGP',
        ));
    }

    public function test_invalid_shipping_configuration_rejects_place_order(): void
    {
        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => true,
            'flat_shipping_amount' => null,
        ]);

        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(CheckoutPreparationException::class);
        $this->action->execute($this->placeOrderInput($cart, hash('sha256', 'ship-'.uniqid())));
    }

    public function test_idempotent_replay_does_not_depend_on_current_cart_lines(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'idem-lines-'.uniqid());
        $input = $this->placeOrderInput($cart, $idempotencyKey);

        $first = $this->action->execute($input);

        $cart->items()->delete();

        $otherCart = $this->createGuestCartWithItem($variant);
        $replayInput = $this->placeOrderInput($otherCart, $idempotencyKey);

        $replay = $this->action->execute($replayInput);

        $this->assertTrue($replay->idempotentReplay);
        $this->assertSame($first->orderId, $replay->orderId);
    }

    public function test_idempotent_retry_returns_same_order(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'idem-'.uniqid());
        $input = $this->placeOrderInput($cart, $idempotencyKey);

        $first = $this->action->execute($input);
        $reservationCount = InventoryReservation::query()->count();
        $movementCount = InventoryMovement::query()->count();
        $second = $this->action->execute($input);

        $this->assertTrue($second->idempotentReplay);
        $this->assertSame($first->orderId, $second->orderId);
        $this->assertSame($first->paymentId, $second->paymentId);
        $this->assertSame(1, Order::query()->where('idempotency_key', $idempotencyKey)->count());
        $this->assertSame(1, Payment::query()->where('order_id', $first->orderId)->count());
        $this->assertSame($reservationCount, InventoryReservation::query()->count());
        $this->assertSame($movementCount, InventoryMovement::query()->count());
    }

    public function test_second_order_on_same_cart_with_different_idempotency_key_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $keyA = hash('sha256', 'order-a-'.uniqid());
        $keyB = hash('sha256', 'order-b-'.uniqid());

        $first = $this->action->execute($this->placeOrderInput($cart, $keyA));
        $orderAId = $first->orderId;

        $reservations = InventoryReservation::query()->where('order_id', $orderAId)->get();
        $reservedQty = (int) InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved');
        $movementCount = InventoryMovement::query()->count();

        try {
            $this->action->execute($this->placeOrderInput($cart, $keyB));
            $this->fail('Expected CheckoutPreparationException.');
        } catch (CheckoutPreparationException $exception) {
            $this->assertSame(CheckoutPreparationException::CART_NOT_ACTIVE, $exception->errorCode);
        }

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Payment::query()->count());
        foreach ($reservations as $reservation) {
            $this->assertSame($orderAId, $reservation->fresh()->order_id);
        }
        $this->assertSame($reservedQty, (int) InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
        $this->assertSame($movementCount, InventoryMovement::query()->count());

        $replay = $this->action->execute($this->placeOrderInput($cart, $keyA));
        $this->assertTrue($replay->idempotentReplay);
        $this->assertSame($orderAId, $replay->orderId);
    }

    public function test_same_idempotency_key_different_payload_rejected(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'conflict-'.uniqid());

        $this->action->execute($this->placeOrderInput($cart, $idempotencyKey));

        $this->expectException(PlaceOrderIdempotencyConflictException::class);
        $this->action->execute($this->placeOrderInput($cart, $idempotencyKey, billingCity: 'Alexandria'));
    }

    public function test_authenticated_user_idempotent_replay_with_same_user_succeeds(): void
    {
        $user = User::factory()->create();
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createRegisteredCartWithItem($user, $variant);
        $idempotencyKey = hash('sha256', 'auth-idem-'.uniqid());
        $input = $this->placeOrderInput($cart, $idempotencyKey, userId: $user->getKey());

        $first = $this->action->execute($input);
        $second = $this->action->execute($input);

        $this->assertTrue($second->idempotentReplay);
        $this->assertSame($first->orderId, $second->orderId);
    }

    public function test_different_authenticated_user_cannot_replay_same_idempotency_key(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createRegisteredCartWithItem($owner, $variant);
        $idempotencyKey = hash('sha256', 'auth-cross-'.uniqid());

        $this->action->execute($this->placeOrderInput($cart, $idempotencyKey, userId: $owner->getKey()));

        $intruderCart = $this->createRegisteredCartWithItem($intruder, $variant);

        $this->expectException(PlaceOrderIdempotencyConflictException::class);
        $this->action->execute($this->placeOrderInput($intruderCart, $idempotencyKey, userId: $intruder->getKey()));
    }

    public function test_guest_order_cannot_be_replayed_as_authenticated_user(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'guest-auth-'.uniqid());

        $this->action->execute($this->placeOrderInput($cart, $idempotencyKey));

        $user = User::factory()->create();
        $userCart = $this->createRegisteredCartWithItem($user, $variant);

        $this->expectException(PlaceOrderIdempotencyConflictException::class);
        $this->action->execute($this->placeOrderInput($userCart, $idempotencyKey, userId: $user->getKey()));
    }

    public function test_guest_same_key_replay_after_session_cart_change_still_succeeds(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'guest-rotate-'.uniqid());
        $input = $this->placeOrderInput($cart, $idempotencyKey);

        $first = $this->action->execute($input);

        $replacement = $this->createGuestCartWithItem($variant);
        $replayInput = $this->placeOrderInput($replacement, $idempotencyKey);

        $replay = $this->action->execute($replayInput);

        $this->assertTrue($replay->idempotentReplay);
        $this->assertSame($first->orderId, $replay->orderId);
    }

    public function test_guest_checkout_disabled_rejected(): void
    {
        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update(['guest_checkout_enabled' => false]);
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        $this->expectException(PlaceOrderException::class);
        $this->action->execute($this->placeOrderInput($cart, hash('sha256', 'guest-off-'.uniqid())));
    }

    public function test_registered_customer_cart_ownership(): void
    {
        $user = User::factory()->create();
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createRegisteredCartWithItem($user, $variant);

        $result = $this->action->execute($this->placeOrderInput(
            $cart,
            hash('sha256', 'reg-'.uniqid()),
            userId: $user->getKey(),
        ));

        $order = Order::query()->whereKey($result->orderId)->first();
        $this->assertSame($user->getKey(), $order->user_id);
        $this->assertNull($order->guest_email);
    }

    public function test_outer_transaction_rollback_on_failure_restores_all_rows(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);

        try {
            DB::transaction(function () use ($cart): void {
                $this->action->execute($this->placeOrderInput($cart, hash('sha256', 'rollback-'.uniqid())));
                throw new \RuntimeException('Force rollback.');
            });
            $this->fail('Expected runtime exception.');
        } catch (\RuntimeException) {
            $this->assertSame(0, Order::query()->count());
            $this->assertSame(0, Payment::query()->count());
            $this->assertSame(0, InventoryReservation::query()->count());
            $this->assertSame(0, (int) InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
        }
    }
}
