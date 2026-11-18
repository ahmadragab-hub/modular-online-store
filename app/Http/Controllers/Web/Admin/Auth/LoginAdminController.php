<?php

namespace App\Http\Controllers\Web\Admin\Auth;

use App\Domains\Identity\Actions\LoginAdminAction;
use App\Domains\Identity\Exceptions\AdminLoginException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\LoginAdminRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginAdminController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(LoginAdminRequest $request, LoginAdminAction $loginAdminAction): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $user = $loginAdminAction->execute(
                email: $validated['email'],
                password: $validated['password'],
            );
        } catch (AdminLoginException) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('admin.auth.login.failed')]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }
}
