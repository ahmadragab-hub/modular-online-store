<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('storefront.account.index');
    }
}
