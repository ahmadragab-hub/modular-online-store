<?php

namespace App\Http\Storefront;

use App\Domains\Cart\Actions\MergeGuestCartIntoCustomerCartAction;
use App\Domains\Cart\Exceptions\CartMergeException;
use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Exceptions\MultipleActiveCustomerCartsException;
use App\Domains\Customers\Queries\FindCustomerForUserQuery;
use App\Domains\Identity\Models\User;
use App\Domains\Store\Queries\ResolveDisplayCurrencyCodeQuery;
use App\Shared\Support\CartMergeFlash;
use App\Shared\Support\GuestCartSession;
use Illuminate\Http\Request;

final class CustomerCartMergeCoordinator
{
    public function __construct(
        private readonly MergeGuestCartIntoCustomerCartAction $mergeGuestCartIntoCustomerCartAction,
        private readonly FindCustomerForUserQuery $findCustomerForUserQuery,
        private readonly ResolveDisplayCurrencyCodeQuery $resolveDisplayCurrencyCodeQuery,
    ) {}

    public function attemptAfterAuthentication(Request $request, User $user): void
    {
        $customer = $this->findCustomerForUserQuery->execute($user);

        if ($customer === null) {
            return;
        }

        $guestToken = $request->session()->get(GuestCartSession::SESSION_KEY);
        $guestToken = is_string($guestToken) ? trim($guestToken) : '';

        $candidate = $request->attributes->get('storefront.display_currency');
        $candidate = is_string($candidate) ? $candidate : null;
        $displayCurrency = $this->resolveDisplayCurrencyCodeQuery->resolve($candidate);

        try {
            $result = $this->mergeGuestCartIntoCustomerCartAction->execute(
                guestSessionToken: $guestToken,
                customer: $customer,
                user: $user,
                displayCurrencyCode: $displayCurrency,
                contentLocale: (string) app()->getLocale(),
            );
        } catch (MultipleActiveCustomerCartsException $exception) {
            $request->session()->flash(CartMergeFlash::SESSION_KEY, [
                'status' => CartMergeFlash::STATUS_MULTIPLE_CARTS,
                'cart_ids' => $exception->cartIds,
            ]);

            return;
        } catch (CartMergeException|CartMutationException) {
            $request->session()->flash(CartMergeFlash::SESSION_KEY, [
                'status' => CartMergeFlash::STATUS_FAILED,
            ]);

            return;
        }

        if ($result->guestCartAbandoned) {
            $request->session()->forget(GuestCartSession::SESSION_KEY);
        }

        if ($result->hasLineOutcomes()) {
            $status = $result->guestCartAbandoned
                ? CartMergeFlash::STATUS_SUCCESS
                : CartMergeFlash::STATUS_NO_QUANTITY_APPLIED;

            $request->session()->flash(CartMergeFlash::SESSION_KEY, [
                'status' => $status,
                'lines' => $result->flashPayload(),
            ]);
        }
    }
}
