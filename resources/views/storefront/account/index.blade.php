@extends('layouts.storefront')

@section('title', __('storefront.account.title'))

@section('content')
    <main class="storefront-main mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-semibold text-[var(--sf-text)]">{{ __('storefront.account.title') }}</h1>
        <p class="mt-4 text-sm text-[var(--sf-muted)]">{{ __('storefront.account.placeholder') }}</p>

        <form method="post" action="{{ route('storefront.logout.destroy') }}" class="mt-8">
            @csrf
            <button
                type="submit"
                class="rounded-lg border border-[var(--sf-border)] px-4 py-2 text-sm font-medium text-[var(--sf-text)] hover:border-[var(--sf-muted)]"
            >
                {{ __('storefront.auth.logout.submit') }}
            </button>
        </form>
    </main>
@endsection