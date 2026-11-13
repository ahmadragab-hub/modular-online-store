<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Checkout\Actions\PlaceOrderAction;
use App\Domains\Checkout\Exceptions\CheckoutPreparationException;
use App\Domains\Checkout\Exceptions\PlaceOrderException;
use App\Domains\Checkout\Exceptions\PlaceOrderIdempotencyConflictException;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Exceptions\InvalidStockReservationRequestException;
use App\Domains\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\PlaceOrderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(): View
    {
        return view('storefront.checkout.show');
    }

    public function store(PlaceOrderRequest $request, PlaceOrderAction $placeOrderAction): RedirectResponse
    {
        try {
            $result = $placeOrderAction->execute($request->toPlaceOrderInput());

            return redirect()
                ->route('storefront.checkout.success', ['publicUuid' => $result->publicUuid])
                ->with('checkout_order_number', $result->orderNumber);
        } catch (PlaceOrderIdempotencyConflictException) {
            abort(409, __('storefront.checkout.errors.conflict'));
        } catch (PlaceOrderException $exception) {
            return back()
                ->withInput()
                ->withErrors(['checkout' => $this->mapPlaceOrderMessage($exception)]);
        } catch (CheckoutPreparationException $exception) {
            return back()
                ->withInput()
                ->withErrors(['checkout' => $this->mapPreparationMessage($exception)]);
        } catch (InsufficientStockException|InvalidStockReservationRequestException) {
            return back()
                ->withInput()
                ->withErrors(['checkout' => __('storefront.checkout.errors.stock')]);
        } catch (\Throwable) {
            return back()
                ->withInput()
                ->withErrors(['checkout' => __('storefront.checkout.errors.generic')]);
        }
    }

    public function success(string $publicUuid): View|RedirectResponse
    {
        $order = Order::query()->where('public_uuid', $publicUuid)->first();

        if ($order === null) {
            return redirect()->route('storefront.home')
                ->withErrors(['checkout' => __('storefront.checkout.errors.not_found')]);
        }

        return view('storefront.checkout.success', [
            'order' => $order,
        ]);
    }

    private function mapPlaceOrderMessage(PlaceOrderException $exception): string
    {
        return match ($exception->errorCode) {
            PlaceOrderException::GUEST_CHECKOUT_DISABLED => __('storefront.checkout.errors.guest_disabled'),
            PlaceOrderException::GUEST_EMAIL_REQUIRED => __('storefront.checkout.errors.guest_email'),
            PlaceOrderException::INVALID_CONTENT_LOCALE => __('storefront.checkout.errors.locale'),
            PlaceOrderException::ORDER_NOT_REPLAYABLE => __('storefront.checkout.errors.not_replayable'),
            default => __('storefront.checkout.errors.generic'),
        };
    }

    private function mapPreparationMessage(CheckoutPreparationException $exception): string
    {
        return match ($exception->errorCode) {
            CheckoutPreparationException::CART_EMPTY,
            CheckoutPreparationException::CART_NOT_ACTIVE,
            CheckoutPreparationException::CART_EXPIRED,
            CheckoutPreparationException::CART_OWNERSHIP,
            CheckoutPreparationException::CART_NOT_FOUND => __('storefront.checkout.errors.cart'),
            CheckoutPreparationException::INSUFFICIENT_STOCK,
            CheckoutPreparationException::VARIANT_UNAVAILABLE => __('storefront.checkout.errors.stock'),
            CheckoutPreparationException::EXCHANGE_RATE_MISSING,
            CheckoutPreparationException::CURRENCY_UNSUPPORTED,
            CheckoutPreparationException::CURRENCY_NOT_TRANSACTION_ENABLED => __('storefront.checkout.errors.currency'),
            CheckoutPreparationException::SHIPPING_NOT_CONFIGURED => __('storefront.checkout.errors.shipping'),
            default => __('storefront.checkout.errors.generic'),
        };
    }
}
