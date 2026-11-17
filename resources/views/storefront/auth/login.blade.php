@extends('layouts.storefront')

@section('title', __('storefront.auth.login.title'))

@section('content')
    <main class="storefront-main mx-auto max-w-md px-4 py-10">
        <h1 class="text-2xl font-semibold text-[var(--sf-text)]">{{ __('storefront.auth.login.title') }}</h1>
        <p class="mt-2 text-sm text-[var(--sf-muted)]">{{ __('storefront.auth.login.intro') }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('storefront.login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium" for="email">{{ __('storefront.auth.login.email') }}</label>
                <input
                    class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 text-[var(--sf-text)]"
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                >
            </div>
            <div>
                <label class="block text-sm font-medium" for="password">{{ __('storefront.auth.login.password') }}</label>
                <input
                    class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 text-[var(--sf-text)]"
                    type="password"
                    name="password"
                    id="password"
                    required
                    autocomplete="current-password"
                >
            </div>
            <button
                type="submit"
                class="w-full rounded-lg bg-[var(--sf-accent)] px-4 py-2.5 text-sm font-medium text-[var(--sf-accent-text)] hover:opacity-90"
            >
                {{ __('storefront.auth.login.submit') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-[var(--sf-muted)]">
            {{ __('storefront.auth.login.register_prompt') }}
            <a href="{{ route('storefront.register.create') }}" class="font-medium text-[var(--sf-accent)] hover:underline">
                {{ __('storefront.auth.login.register_link') }}
            </a>
        </p>
    </main>
@endsection