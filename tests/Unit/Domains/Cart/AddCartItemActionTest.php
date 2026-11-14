<?php

namespace Tests\Unit\Domains\Cart;

use App\Domains\Cart\Actions\AddCartItemAction;
use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryReservation;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\TestCase;

class AddCartItemActionTest extends TestCase
{
    use CreatesCartMutationFixtures;
    use RefreshDatabase;

    private AddCartItemAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
        $this->action = $this->app->make(AddCartItemAction::class);
    }

    public function test_creates_line_with_correct_snapshots(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart('75.00');
        $cart = $this->createActiveGuestCart();

        $item = $this->action->execute($cart, $variant->getKey(), 2, Product::LOCALE_EN);

        $this->assertSame($cart->getKey(), $item->cart_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('75.00', (string) $item->unit_price_base);
        $this->assertSame('Cart Product EN', $item->product_name);
        $this->assertSame($variant->sku, $item->sku);
        $this->assertSame('Large / Navy', $item->variant_label);
    }

    public function test_merges_quantity_for_existing_variant(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 5);
        $cart = $this->createActiveGuestCart();

        $first = $this->action->execute($cart, $variant->getKey(), 2, Product::LOCALE_EN);
        $second = $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(3, $second->quantity);
        $this->assertSame(1, CartItem::query()->where('cart_id', $cart->getKey())->count());
    }

    public function test_rejects_insufficient_stock_when_inventory_missing(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: null);
        $cart = $this->createActiveGuestCart();

        $this->expectException(CartMutationException::class);

        try {
            $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::INSUFFICIENT_STOCK, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_insufficient_stock_on_merge(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 2);
        $cart = $this->createActiveGuestCart();

        $this->action->execute($cart, $variant->getKey(), 2, Product::LOCALE_EN);

        $this->expectException(CartMutationException::class);

        try {
            $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::INSUFFICIENT_STOCK, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_inactive_variant(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(
            variantStatus: ProductVariant::STATUS_INACTIVE,
        );
        $cart = $this->createActiveGuestCart();

        $this->expectException(CartMutationException::class);

        try {
            $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::VARIANT_UNAVAILABLE, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_unpublished_product(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(
            productStatus: Product::STATUS_DRAFT,
        );
        $cart = $this->createActiveGuestCart();

        $this->expectException(CartMutationException::class);

        try {
            $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::VARIANT_UNAVAILABLE, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_inactive_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();
        $cart->update(['status' => Cart::STATUS_CONVERTED]);

        $this->expectException(CartMutationException::class);

        try {
            $this->action->execute($cart->fresh(), $variant->getKey(), 1, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::CART_NOT_ACTIVE, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_expired_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart(expiresAt: now()->subMinute());

        $this->expectException(CartMutationException::class);

        try {
            $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::CART_EXPIRED, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_zero_and_negative_quantity_delta(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();

        foreach ([0, -1] as $invalidQuantity) {
            try {
                $this->action->execute($cart, $variant->getKey(), $invalidQuantity, Product::LOCALE_EN);
                $this->fail('Expected invalid quantity rejection.');
            } catch (CartMutationException $exception) {
                $this->assertSame(CartMutationException::INVALID_QUANTITY, $exception->errorCode);
            }
        }

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_refreshes_snapshots_when_catalog_price_changes(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart('40.00');
        $cart = $this->createActiveGuestCart();

        $item = $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);
        $variant->update(['price' => '55.00']);

        $updated = $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $this->assertSame('55.00', (string) $updated->unit_price_base);
        $this->assertSame($item->getKey(), $updated->getKey());
        $this->assertSame(2, $updated->quantity);
    }

    public function test_does_not_change_inventory_reservations(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();

        InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->addHour(),
        ]);

        $level = InventoryLevel::query()->where('product_variant_id', $variant->getKey())->firstOrFail();
        $reservedBefore = $level->quantity_reserved;

        $this->action->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $this->assertSame($reservedBefore, $level->fresh()->quantity_reserved);
        $this->assertSame(1, InventoryReservation::query()->count());
    }
}
