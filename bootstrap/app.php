<?php

use App\Domains\Identity\Models\User;
use App\Http\Middleware\EnsureAdminUser;
use App\Http\Middleware\EnsureCustomerUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'customer' => EnsureCustomerUser::class,
            'admin' => EnsureAdminUser::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login.create');
            }

            return route('storefront.login.create');
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->role === User::ROLE_ADMIN && $user->isActive()) {
                return route('admin.dashboard');
            }

            return route('storefront.home');
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('inventory:expire-reservations')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
