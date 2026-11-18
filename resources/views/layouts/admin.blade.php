<!DOCTYPE html>
<html lang="{{ $adminHtmlLang }}" dir="{{ $adminTextDirection }}" data-theme="{{ $adminTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.brand')) — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @auth
        @include('admin.partials.nav')
    @endauth

    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <p class="mb-4 rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </p>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
                <ul class="list-disc ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>