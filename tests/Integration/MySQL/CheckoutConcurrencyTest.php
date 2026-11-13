<?php

namespace Tests\Integration\MySQL;

use App\Domains\Checkout\Actions\PlaceOrderAction;
use App\Domains\Inventory\Actions\ReserveStockForCartAction;
use App\Domains\Inventory\DTOs\ReserveStockForCartInput;
use App\Domains\Inventory\DTOs\ReserveStockForCartLineInput;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsCheckoutPlaceOrderFixtures;
use Tests\TestCase;

/**
 * Requires MySQL with row-level locking semantics.
 *
 * @group mysql
 */
class CheckoutConcurrencyTest extends TestCase
{
    use BuildsCheckoutPlaceOrderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL connection required for concurrency integration tests.');
        }

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);
    }

    public function test_two_carts_compete_for_last_unit_only_one_reserves(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant(stock: 1);
        $cartA = $this->createGuestCartWithItem($variant, 1);
        $cartB = $this->createGuestCartWithItem($variant, 1);

        $reserveAction = $this->app->make(ReserveStockForCartAction::class);
        $successes = 0;
        $failures = 0;

        foreach ([$cartA, $cartB] as $cart) {
            try {
                DB::transaction(function () use ($reserveAction, $cart, $variant): void {
                    $reserveAction->execute(new ReserveStockForCartInput(
                        cartId: $cart->getKey(),
                        lines: [new ReserveStockForCartLineInput($variant->getKey(), 1)],
                        expiresAt: now()->addMinutes(15),
                    ));
                });
                $successes++;
            } catch (InsufficientStockException) {
                $failures++;
            }
        }

        $this->assertSame(1, $successes);
        $this->assertSame(1, $failures);
    }

    public function test_duplicate_reserve_on_same_cart_reuses_hold(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant(stock: 2);
        $cart = $this->createGuestCartWithItem($variant, 1);
        $reserveAction = $this->app->make(ReserveStockForCartAction::class);
        $line = [new ReserveStockForCartLineInput($variant->getKey(), 1)];

        DB::transaction(fn () => $reserveAction->execute(new ReserveStockForCartInput(
            cartId: $cart->getKey(),
            lines: $line,
            expiresAt: now()->addMinutes(15),
        )));

        $second = DB::transaction(fn () => $reserveAction->execute(new ReserveStockForCartInput(
            cartId: $cart->getKey(),
            lines: $line,
            expiresAt: now()->addMinutes(15),
        )));

        $this->assertTrue($second->reusedExistingReservations);
        $this->assertSame(1, $cart->reservations()->where('status', 'active')->count());
    }

    public function test_concurrent_place_order_idempotency_key_creates_single_order(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant();
        $cart = $this->createGuestCartWithItem($variant);
        $idempotencyKey = hash('sha256', 'mysql-idem-'.Str::random(16));
        $input = $this->placeOrderInput($cart, $idempotencyKey);
        $action = $this->app->make(PlaceOrderAction::class);

        $first = DB::transaction(fn () => $action->execute($input));
        $second = DB::transaction(fn () => $action->execute($input));

        $this->assertSame($first->orderId, $second->orderId);
        $this->assertSame(1, Order::query()->where('idempotency_key', $idempotencyKey)->count());
        $this->assertSame(1, Payment::query()->where('order_id', $first->orderId)->count());
    }
}
