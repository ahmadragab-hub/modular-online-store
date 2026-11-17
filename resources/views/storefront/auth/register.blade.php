@extends('layouts.storefront')

@section('title', __('storefront.auth.register.title'))

@section('content')
    <main class="storefront-main mx-auto max-w-md px-4 py-10">
        <h1 class="text-2xl font-semibold text-[var(--sf-text)]">{{ __('storefront.auth.register.title') }}</h1>
        <p class="mt-2 text-sm text-[var(--sf-muted)]">{{ __('storefront.auth.register.intro') }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('storefront.register.store') }}" class="mt-6 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium" for="first_name">{{ __('storefront.auth.register.first_name') }}</label>
                    <input
                        class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 text-[var(--sf-text)]"
                        type="text"
                        name="first_name"
                        id="first_name"
                        value="{{ old('first_name') }}"
                        required
                        maxlength="100"
                        autocomplete="given-name"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium" for="last_name">{{ __('storefront.auth.register.last_name') }}</label>
                    <input
                        class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 text-[var(--sf-text)]"
                        type="text"
                        name="last_name"
                        id="last_name"
                        value="{{ old('last_name') }}"
                        required
                        maxlength="100"
                        autocomplete="family-name"
                    >
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium" for="email">{{ __('storefront.auth.register.email') }}</label>
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
                <label class="block text-sm font-medium" for="password">{{ __('storefront.auth.register.password') }}</label>
                <input
                    class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 text-[var(--sf-text)]"
                    type="password"
                    name="password"
                    id="password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>
            <div>
                <label class="block text-sm font-medium" for="password_confirmation">{{ __('storefront.auth.register.password_confirmation') }}</label>
                <input
                    class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 text-[var(--sf-text)]"
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>
            <button
                type="submit"
                class="w-full rounded-lg bg-[var(--sf-accent)] px-4 py-2.5 text-sm font-medium text-[var(--sf-accent-text)] hover:opacity-90"
            >
                {{ __('storefront.auth.register.submit') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-[var(--sf-muted)]">
            {{ __('storefront.auth.register.login_prompt') }}
            <a href="{{ route('storefront.login.create') }}" class="font-medium text-[var(--sf-accent)] hover:underline">
                {{ __('storefront.auth.register.login_link') }}
            </a>
        </p>
    </main>
@endsection