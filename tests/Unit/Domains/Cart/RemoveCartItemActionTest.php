<?php

namespace Tests\Unit\Domains\Cart;

use App\Domains\Cart\Actions\AddCartItemAction;
use App\Domains\Cart\Actions\RemoveCartItemAction;
use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\TestCase;

class RemoveCartItemActionTest extends TestCase
{
    use CreatesCartMutationFixtures;
    use RefreshDatabase;

    private AddCartItemAction $addAction;

    private RemoveCartItemAction $removeAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
        $this->addAction = $this->app->make(AddCartItemAction::class);
        $this->removeAction = $this->app->make(RemoveCartItemAction::class);
    }

    public function test_deletes_item_in_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();
        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $this->removeAction->execute($cart, $item->getKey());

        $this->assertNull(CartItem::query()->find($item->getKey()));
    }

    public function test_rejects_item_from_another_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cartA = $this->createActiveGuestCart();
        $cartB = $this->createActiveGuestCart();
        $item = $this->addAction->execute($cartA, $variant->getKey(), 1, Product::LOCALE_EN);

        $this->expectException(CartMutationException::class);

        try {
            $this->removeAction->execute($cartB, $item->getKey());
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::CART_ITEM_NOT_FOUND, $exception->errorCode);

            throw $exception;
        }

        $this->assertNotNull(CartItem::query()->find($item->getKey()));
    }

    public function test_allows_removal_when_product_unpublished(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();
        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $product->update(['status' => Product::STATUS_DRAFT]);

        $this->removeAction->execute($cart, $item->getKey());

        $this->assertNull(CartItem::query()->find($item->getKey()));
    }

    public function test_allows_removal_when_variant_inactive(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();
        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $variant->update(['status' => ProductVariant::STATUS_INACTIVE]);

        $this->removeAction->execute($cart, $item->getKey());

        $this->assertNull(CartItem::query()->find($item->getKey()));
    }

    public function test_allows_removal_from_expired_active_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();
        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $cart->update(['expires_at' => now()->subMinute()]);

        $this->removeAction->execute($cart->fresh(), $item->getKey());

        $this->assertNull(CartItem::query()->find($item->getKey()));
    }

    public function test_rejects_removal_from_converted_cart(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();
        $cart = $this->createActiveGuestCart();
        $item = $this->addAction->execute($cart, $variant->getKey(), 1, Product::LOCALE_EN);

        $cart->update(['status' => Cart::STATUS_CONVERTED]);

        $this->expectException(CartMutationException::class);

        try {
            $this->removeAction->execute($cart->fresh(), $item->getKey());
        } catch (CartMutationException $exception) {
            $this->assertSame(CartMutationException::CART_NOT_ACTIVE, $exception->errorCode);

            throw $exception;
        }

        $this->assertNotNull(CartItem::query()->find($item->getKey()));
    }
}
