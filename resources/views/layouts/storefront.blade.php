<!DOCTYPE html>
<html
    lang="{{ $storefrontHtmlLang }}"
    dir="{{ $storefrontTextDirection }}"
    data-theme="{{ $storefrontTheme }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('storefront.brand')) — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|noto-sans-arabic:400,500,600"
        rel="stylesheet"
    />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @stack('head')
</head>
<body class="storefront-body min-h-screen antialiased">
    @include('storefront.partials.preference-bar')

    @yield('content')

    @stack('scripts')
</body>
</html>
