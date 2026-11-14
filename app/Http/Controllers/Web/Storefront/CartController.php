<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Cart\Actions\AddCartItemAction;
use App\Domains\Cart\Actions\RemoveCartItemAction;
use App\Domains\Cart\Actions\UpdateCartItemQuantityAction;
use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Models\CartItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreCartItemRequest;
use App\Http\Requests\Web\UpdateCartItemRequest;
use App\Http\Storefront\GuestCartResolver;
use App\Http\Storefront\MapsCartMutationException;
use App\Http\Storefront\StorefrontCartPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(
        Request $request,
        GuestCartResolver $guestCartResolver,
        StorefrontCartPresenter $presenter,
    ): View {
        $cart = $guestCartResolver->loadCartWithItems($guestCartResolver->resolve($request)->cart);

        return view('storefront.cart.show', [
            'cart' => $cart,
            'presentation' => $presenter->present($cart),
        ]);
    }

    public function store(
        StoreCartItemRequest $request,
        GuestCartResolver $guestCartResolver,
        AddCartItemAction $addCartItemAction,
    ): RedirectResponse {
        $cart = $guestCartResolver->resolve($request)->cart;
        $validated = $request->validated();

        try {
            $addCartItemAction->execute(
                cart: $cart,
                productVariantId: (int) $validated['product_variant_id'],
                quantityDelta: (int) $validated['quantity'],
                contentLocale: (string) app()->getLocale(),
            );
        } catch (CartMutationException $exception) {
            return $this->handleCartMutationException($request, $exception);
        }

        return redirect()
            ->route('storefront.cart.show')
            ->with('cart_status', __('storefront.cart.added'));
    }

    public function update(
        UpdateCartItemRequest $request,
        CartItem $cartItem,
        GuestCartResolver $guestCartResolver,
        UpdateCartItemQuantityAction $updateCartItemQuantityAction,
    ): RedirectResponse {
        $cart = $guestCartResolver->resolve($request)->cart;

        try {
            $updateCartItemQuantityAction->execute(
                cart: $cart,
                cartItemId: (int) $cartItem->getKey(),
                quantity: (int) $request->validated('quantity'),
                contentLocale: (string) app()->getLocale(),
            );
        } catch (CartMutationException $exception) {
            return $this->handleCartMutationException($request, $exception);
        }

        return redirect()
            ->route('storefront.cart.show')
            ->with('cart_status', __('storefront.cart.updated'));
    }

    public function destroy(
        Request $request,
        CartItem $cartItem,
        GuestCartResolver $guestCartResolver,
        RemoveCartItemAction $removeCartItemAction,
    ): RedirectResponse {
        $cart = $guestCartResolver->resolve($request)->cart;

        try {
            $removeCartItemAction->execute($cart, (int) $cartItem->getKey());
        } catch (CartMutationException $exception) {
            return $this->handleCartMutationException($request, $exception);
        }

        return redirect()
            ->route('storefront.cart.show')
            ->with('cart_status', __('storefront.cart.removed'));
    }

    private function handleCartMutationException(Request $request, CartMutationException $exception): RedirectResponse
    {
        if ($exception->errorCode === CartMutationException::CART_ITEM_NOT_FOUND) {
            abort(404, __('storefront.cart.errors.item_not_found'));
        }

        return MapsCartMutationException::toRedirect($request, $exception)
            ->setStatusCode(422);
    }
}
