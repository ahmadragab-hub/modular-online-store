<?php

namespace App\Providers;

use App\Domains\Payments\Contracts\PaymentGatewayContract;
use App\Domains\Payments\Gateways\MockPaymentGateway;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayContract::class, function () {
            $driver = config('payments.driver', 'mock');

            return match ($driver) {
                MockPaymentGateway::PROVIDER => $this->app->make(MockPaymentGateway::class),
                default => $this->app->make(MockPaymentGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
