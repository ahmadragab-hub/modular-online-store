<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Customers\Actions\UpdateCustomerProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Storefront\Concerns\ResolvesStorefrontCustomer;
use App\Http\Requests\Web\Account\UpdateCustomerProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use ResolvesStorefrontCustomer;

    public function edit(): View
    {
        $customer = $this->currentCustomer();

        return view('storefront.account.profile.edit', [
            'customer' => $customer,
            'user' => $customer->user,
        ]);
    }

    public function update(
        UpdateCustomerProfileRequest $request,
        UpdateCustomerProfileAction $updateCustomerProfileAction,
    ): RedirectResponse {
        $customer = $this->currentCustomer();
        $validated = $request->validated();

        $updateCustomerProfileAction->execute(
            customer: $customer,
            user: $customer->user,
            firstName: $validated['first_name'],
            lastName: $validated['last_name'],
        );

        return redirect()
            ->route('storefront.account.index')
            ->with('account_status', __('storefront.account.profile.updated'));
    }
}
