<?php

return [
    'brand' => 'Administration',
    'nav' => [
        'dashboard' => 'Dashboard',
        'catalog' => 'Catalog',
        'categories' => 'Categories',
        'products' => 'Products',
        'sizes' => 'Sizes',
        'colors' => 'Colors',
        'inventory' => 'Inventory',
        'orders' => 'Orders',
        'customers' => 'Customers',
        'settings' => 'Store settings',
        'exchange_rates' => 'Exchange rates',
        'logout' => 'Sign out',
    ],
    'auth' => [
        'login' => [
            'title' => 'Admin sign in',
            'email' => 'Email',
            'password' => 'Password',
            'submit' => 'Sign in',
            'failed' => 'These credentials are invalid or the account is not an active admin.',
        ],
    ],
    'preferences' => [
        'language' => 'Language',
        'theme' => 'Theme',
        'theme_light' => 'Light',
        'theme_dark' => 'Dark',
    ],
    'dashboard' => [
        'title' => 'Dashboard',
        'order_counts' => 'Orders by status',
        'recent_orders' => 'Recent orders',
        'low_stock' => 'Low stock variants',
    ],
    'store' => [
        'settings' => [
            'title' => 'Store settings',
            'updated' => 'Store settings saved.',
        ],
        'exchange_rates' => [
            'title' => 'Exchange rates',
            'created' => 'Exchange rate added.',
        ],
    ],
    'catalog' => [
        'saved' => 'Saved.',
        'translations' => [
            'saved' => 'Translations saved.',
        ],
        'products' => [
            'published' => 'Product published.',
            'archived' => 'Product archived.',
        ],
        'images' => [
            'uploaded' => 'Image uploaded.',
            'deleted' => 'Image removed.',
        ],
    ],
    'inventory' => [
        'adjust' => [
            'success' => 'Stock adjusted.',
            'denied' => 'Stock adjustment was rejected.',
        ],
    ],
    'orders' => [
        'transition' => [
            'success' => 'Order status updated.',
            'denied' => 'Status change is not allowed.',
        ],
        'cancel' => [
            'success' => 'Order cancelled.',
            'denied' => 'This order cannot be cancelled.',
        ],
    ],
    'actions' => [
        'save' => 'Save',
        'create' => 'Create',
        'cancel' => 'Cancel',
        'publish' => 'Publish',
        'archive' => 'Archive',
    ],
];
