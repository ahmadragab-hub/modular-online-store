<?php

return [

  /*
    |--------------------------------------------------------------------------
    | Supported storefront locales
    |--------------------------------------------------------------------------
    |
    | UI and translation files use these keys. Product/catalog translations
    | will be added separately when the Catalog domain is implemented.
    |
    */

    'default' => env('STOREFRONT_LOCALE', 'en'),

    'locales' => [
        'en' => [
            'label' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
            'html' => 'en',
        ],
        'ar' => [
            'label' => 'Arabic',
            'native' => 'العربية',
            'dir' => 'rtl',
            'html' => 'ar',
        ],
    ],

];
