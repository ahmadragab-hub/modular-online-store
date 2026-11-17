<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Storefront\Concerns\ResolvesStorefrontCustomer;
use Illuminate\View\View;

class AccountController extends Controller
{
    use ResolvesStorefrontCustomer;

    public function index(): View
    {
        $customer = $this->currentCustomer()->load('user', 'addresses');
        $orderCount = $customer->orders()->count();

        return view('storefront.account.index', [
            'customer' => $customer,
            'orderCount' => $orderCount,
        ]);
    }
}
