<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Customers\Actions\CreateCustomerAddressAction;
use App\Domains\Customers\Actions\DeleteCustomerAddressAction;
use App\Domains\Customers\Actions\SetCustomerDefaultBillingAddressAction;
use App\Domains\Customers\Actions\SetCustomerDefaultShippingAddressAction;
use App\Domains\Customers\Actions\UpdateCustomerAddressAction;
use App\Domains\Customers\Models\CustomerAddress;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Storefront\Concerns\ResolvesStorefrontCustomer;
use App\Http\Requests\Web\Account\CustomerAddressRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    use ResolvesStorefrontCustomer;

    public function index(): View
    {
        $customer = $this->currentCustomer();
        $addresses = $customer->addresses()->orderBy('id')->get();

        return view('storefront.account.addresses.index', [
            'customer' => $customer,
            'addresses' => $addresses,
        ]);
    }

    public function create(): View
    {
        return view('storefront.account.addresses.create');
    }

    public function store(
        CustomerAddressRequest $request,
        CreateCustomerAddressAction $createCustomerAddressAction,
    ): RedirectResponse {
        $customer = $this->currentCustomer();

        $createCustomerAddressAction->execute($customer, $request->validated());

        return redirect()
            ->route('storefront.account.addresses.index')
            ->with('account_status', __('storefront.account.addresses.created'));
    }

    public function edit(CustomerAddress $address): View
    {
        $address = $this->currentCustomerAddress($address);

        return view('storefront.account.addresses.edit', [
            'address' => $address,
        ]);
    }

    public function update(
        CustomerAddressRequest $request,
        CustomerAddress $address,
        UpdateCustomerAddressAction $updateCustomerAddressAction,
    ): RedirectResponse {
        $address = $this->currentCustomerAddress($address);

        $updateCustomerAddressAction->execute($address, $request->validated());

        return redirect()
            ->route('storefront.account.addresses.index')
            ->with('account_status', __('storefront.account.addresses.updated'));
    }

    public function destroy(
        CustomerAddress $address,
        DeleteCustomerAddressAction $deleteCustomerAddressAction,
    ): RedirectResponse {
        $address = $this->currentCustomerAddress($address);
        $customer = $this->currentCustomer();

        $deleteCustomerAddressAction->execute($customer, $address);

        return redirect()
            ->route('storefront.account.addresses.index')
            ->with('account_status', __('storefront.account.addresses.deleted'));
    }

    public function defaultShipping(
        CustomerAddress $address,
        SetCustomerDefaultShippingAddressAction $setCustomerDefaultShippingAddressAction,
    ): RedirectResponse {
        $address = $this->currentCustomerAddress($address);
        $customer = $this->currentCustomer();

        $setCustomerDefaultShippingAddressAction->execute($customer, $address);

        return redirect()
            ->route('storefront.account.addresses.index')
            ->with('account_status', __('storefront.account.addresses.default_shipping_set'));
    }

    public function defaultBilling(
        CustomerAddress $address,
        SetCustomerDefaultBillingAddressAction $setCustomerDefaultBillingAddressAction,
    ): RedirectResponse {
        $address = $this->currentCustomerAddress($address);
        $customer = $this->currentCustomer();

        $setCustomerDefaultBillingAddressAction->execute($customer, $address);

        return redirect()
            ->route('storefront.account.addresses.index')
            ->with('account_status', __('storefront.account.addresses.default_billing_set'));
    }
}
