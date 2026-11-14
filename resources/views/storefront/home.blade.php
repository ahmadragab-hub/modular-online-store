@extends('layouts.storefront')

@section('title', __('storefront.home.title'))

@section('content')
    <main class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
        <p class="text-sm font-medium text-[var(--sf-accent)]">{{ __('storefront.tagline') }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--sf-text)] sm:text-4xl">
            {{ __('storefront.home.title') }}
        </h1>
        <p class="mt-4 max-w-2xl text-lg leading-relaxed text-[var(--sf-muted)]">
            {{ __('storefront.home.intro') }}
        </p>
        <a
            href="{{ route('storefront.shop.index') }}"
            class="mt-8 inline-flex items-center justify-center rounded-lg bg-[var(--sf-accent)] px-5 py-2.5 text-sm font-medium text-[var(--sf-accent-text)] transition hover:opacity-90"
        >
            {{ __('storefront.home.browse_shop') }}
        </a>
    </main>
@endsection
