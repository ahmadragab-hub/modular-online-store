@extends('layouts.admin')

@section('title', __('admin.auth.login.title'))

@section('content')
    @php
        $demoLoginPrefill = app()->environment(['local', 'testing']);
    @endphp
    <div class="admin-card w-full shadow-lg">
        <h1 class="sf-display mb-2 text-2xl text-[var(--adm-text)]">{{ __('admin.brand') }}</h1>
        <p class="mb-6 text-sm text-[var(--adm-muted)]">{{ __('admin.auth.login.title') }}</p>
        <form method="post" action="{{ route('admin.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="text-sm font-medium text-[var(--adm-text)]" for="email">{{ __('admin.auth.login.email') }}</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $demoLoginPrefill ? \Database\Seeders\DemoUsersSeeder::ADMIN_EMAIL : '') }}"
                    required
                    class="admin-input mt-1"
                >
            </div>
            <div>
                <label class="text-sm font-medium text-[var(--adm-text)]" for="password">{{ __('admin.auth.login.password') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    value="{{ old('password', $demoLoginPrefill ? \Database\Seeders\DemoUsersSeeder::ADMIN_PASSWORD : '') }}"
                    required
                    class="admin-input mt-1"
                >
            </div>
            <button type="submit" class="sf-btn sf-btn--primary w-full">{{ __('admin.auth.login.submit') }}</button>
        </form>

        @include('shared.partials.demo-login-hints', [
            'demoLoginEmail' => \Database\Seeders\DemoUsersSeeder::ADMIN_EMAIL,
            'demoLoginPassword' => \Database\Seeders\DemoUsersSeeder::ADMIN_PASSWORD,
        ])
    </div>
@endsection