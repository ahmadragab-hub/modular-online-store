<?php

namespace App\Http\Controllers\Web\Admin\Customers;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Queries\FindCustomerForAdminQuery;
use App\Domains\Customers\Queries\ListCustomersForAdminQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\ListCustomersRequest;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(ListCustomersRequest $request, ListCustomersForAdminQuery $query): View
    {
        return view('admin.customers.index', [
            'customers' => $query->execute($request->validated('search')),
            'filters' => $request->validated(),
        ]);
    }

    public function show(Customer $customer, FindCustomerForAdminQuery $query): View
    {
        $customer = $query->execute((int) $customer->getKey());

        abort_if($customer === null, 404);

        return view('admin.customers.show', compact('customer'));
    }
}
