<?php

namespace App\Http\Controllers\Web\Storefront\Auth;

use App\Domains\Identity\Actions\LoginCustomerAction;
use App\Domains\Identity\Exceptions\CustomerLoginException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginCustomerRequest;
use App\Http\Storefront\CustomerCartMergeCoordinator;
use App\Shared\Support\GuestCartSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginCustomerController extends Controller
{
    public function create(): View
    {
        return view('storefront.auth.login');
    }

    public function store(
        LoginCustomerRequest $request,
        LoginCustomerAction $loginCustomerAction,
        CustomerCartMergeCoordinator $customerCartMergeCoordinator,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $user = $loginCustomerAction->execute(
                email: $validated['email'],
                password: $validated['password'],
            );
        } catch (CustomerLoginException) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('storefront.auth.login.failed')]);
        }

        Auth::login($user);

        $guestToken = $request->session()->get(GuestCartSession::SESSION_KEY);
        $guestToken = is_string($guestToken) ? trim($guestToken) : '';

        $request->session()->regenerate();

        if ($guestToken !== '') {
            $request->session()->put(GuestCartSession::SESSION_KEY, $guestToken);
        }

        $customerCartMergeCoordinator->attemptAfterAuthentication($request, $user);

        return redirect()->intended(route('storefront.home'));
    }
}
