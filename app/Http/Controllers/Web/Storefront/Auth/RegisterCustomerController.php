<?php

namespace App\Http\Controllers\Web\Storefront\Auth;

use App\Domains\Customers\Exceptions\CustomerRegistrationException;
use App\Domains\Identity\Actions\RegisterCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\RegisterCustomerRequest;
use App\Http\Storefront\CustomerCartMergeCoordinator;
use App\Shared\Support\GuestCartSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterCustomerController extends Controller
{
    public function create(): View
    {
        return view('storefront.auth.register');
    }

    public function store(
        RegisterCustomerRequest $request,
        RegisterCustomerAction $registerCustomerAction,
        CustomerCartMergeCoordinator $customerCartMergeCoordinator,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $user = $registerCustomerAction->execute(
                email: $validated['email'],
                password: $validated['password'],
                firstName: $validated['first_name'],
                lastName: $validated['last_name'],
            );
        } catch (CustomerRegistrationException $exception) {
            $message = match ($exception->errorCode) {
                CustomerRegistrationException::EMAIL_TAKEN,
                CustomerRegistrationException::CUSTOMER_ALREADY_REGISTERED => __('storefront.auth.register.email_taken'),
                default => __('storefront.auth.register.failed'),
            };

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => $message]);
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
