@extends('layouts.storefront')

@section('title', __('storefront.account.profile.title'))

@section('content')
    <main class="storefront-main mx-auto max-w-xl px-4 py-10">
        <h1 class="text-2xl font-semibold text-[var(--sf-text)]">{{ __('storefront.account.profile.title') }}</h1>
        @include('storefront.partials.account-nav')

        @if ($errors->any())
            <div class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('storefront.account.profile.update') }}" class="mt-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium" for="first_name">{{ __('storefront.auth.register.first_name') }}</label>
                <input
                    class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 text-[var(--sf-text)]"
                    type="text"
                    name="first_name"
                    id="first_name"
                    value="{{ old('first_name', $customer->first_name) }}"
                    required
                >
            </div>
            <div>
                <label class="block text-sm font-medium" for="last_name">{{ __('storefront.auth.register.last_name') }}</label>
                <input
                    class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 text-[var(--sf-text)]"
                    type="text"
                    name="last_name"
                    id="last_name"
                    value="{{ old('last_name', $customer->last_name) }}"
                    required
                >
            </div>
            <p class="text-sm text-[var(--sf-muted)]">{{ __('storefront.account.profile.email_readonly', ['email' => $customer->email]) }}</p>
            <button type="submit" class="rounded-lg bg-[var(--sf-accent)] px-4 py-2.5 text-sm font-medium text-[var(--sf-accent-text)]">
                {{ __('storefront.account.profile.save') }}
            </button>
        </form>
    </main>
@endsection