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
<body class="admin-body antialiased">
    @auth
        <div class="admin-shell">
            @include('admin.partials.sidebar')
            <div class="admin-main w-full max-w-7xl">
                @if (session('status'))
                    <p class="admin-card mb-4 border-emerald-300 text-sm text-emerald-800" role="status">
                        {{ session('status') }}
                    </p>
                @endif

                @if ($errors->any())
                    <div class="admin-card mb-4 border-red-300 text-sm text-red-800" role="alert">
                        <ul class="list-disc ps-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    @else
        <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
            @yield('content')
        </main>
    @endauth
</body>
</html>