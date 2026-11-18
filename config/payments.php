<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateway driver
    |--------------------------------------------------------------------------
    |
    | MVP uses the mock driver for development and testing only.
    |
    */

    'driver' => env('PAYMENT_GATEWAY', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Mock gateway
    |--------------------------------------------------------------------------
    |
    | Must remain disabled in production. Server-side enforcement lives in
    | MockPaymentGateway and payment simulation endpoints.
    */

    'mock_enabled' => env('PAYMENT_MOCK_ENABLED', true),

];
