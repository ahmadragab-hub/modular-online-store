<?php

namespace Tests\Unit\Domains\Cart;

use App\Domains\Cart\Actions\AddCartItemAction;
use App\Domains\Cart\Actions\UpdateCartItemQuantityAction;
use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\TestCase;

class UpdateCartItemQuantityActionTest extends TestCase
{
    use CreatesCartMutationFixtures;
    use RefreshDatabase;

    private AddCartItemAction $addAction;

    private UpdateCartItemQuantityAction $updateAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
        $this->addAction = $this->app->make(AddCartItemAction::class);
        $this->updateAction = $this->app->make(UpdateCartItemQuantityAction::class);
    }

    public function test_updates_quantity(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 5);
        $cart = $this->createActiveGuestCart();

        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $updated = $this->updateAction->execute($cart, $item->getKey(), 3, Product::LOCALE_EN);

        $this->assertSame(3, $updated->quantity);
    }

    public function test_rejects_insufficient_stock(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 2);
        $cart = $this->createActiveGuestCart();

        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $this->expectException(CartMutationException::class);

        try {
            $this->updateAction->execute($cart, $item->getKey(), 3, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::INSUFFICIENT_STOCK, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_item_from_another_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cartA = $this->createActiveGuestCart();
        $cartB = $this->createActiveGuestCart();

        $item = $this->addAction->execute($cartA, $variant->getKey(), 1, Product::LOCALE_EN);

        $this->expectException(CartMutationException::class);

        try {
            $this->updateAction->execute($cartB, $item->getKey(), 2, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::CART_ITEM_NOT_FOUND, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_unavailable_variant(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();

        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);
        $variant->update(['status' => ProductVariant::STATUS_INACTIVE]);

        $this->expectException(CartMutationException::class);

        try {
            $this->updateAction->execute($cart, $item->getKey(), 1, Product::LOCALE_EN);
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::VARIANT_UNAVAILABLE, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_zero_and_negative_quantity(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();
        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        foreach ([0, -2] as $invalidQuantity) {
            try {
                $this->updateAction->execute($cart, $item->getKey(), $invalidQuantity, Product::LOCALE_EN);
                $this->fail('Expected invalid quantity rejection.');
            } catch (CartMutationException $exception) {
                $this->assertSame(CartMutationException::INVALID_QUANTITY, $exception->errorCode);
            }
        }
    }

    public function test_refreshes_catalog_snapshots(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart('30.00');
        $cart = $this->createActiveGuestCart();

        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);
        $variant->update(['price' => '44.00']);

        $updated = $this->updateAction->execute($cart, $item->getKey(), 1, Product::LOCALE_AR);

        $this->assertSame('44.00', (string) $updated->unit_price_base);
        $this->assertSame('منتج السلة', $updated->product_name);
    }

    public function test_leaves_cart_items_unchanged_on_failed_update(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(stock: 1);
        $cart = $this->createActiveGuestCart();
        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        try {
            $this->updateAction->execute($cart, $item->getKey(), 5, Product::LOCALE_EN);
        } catch (CartMutationException) {
            // expected
        }

        $this->assertSame(1, CartItem::query()->whereKey($item->getKey())->value('quantity'));
    }
}
