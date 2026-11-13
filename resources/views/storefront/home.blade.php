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
        <p class="mt-6 rounded-lg border border-dashed border-[var(--sf-border)] bg-[var(--sf-surface)] px-4 py-3 text-sm text-[var(--sf-muted)]">
            {{ __('storefront.home.catalog_soon') }}
        </p>
    </main>
@endsection
