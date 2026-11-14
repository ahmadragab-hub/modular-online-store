@extends('layouts.storefront')

@section('title', __('storefront.shop.title'))

@section('content')
    <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <header class="mb-8">
            <h1 class="text-3xl font-semibold tracking-tight text-[var(--sf-text)]">{{ __('storefront.shop.title') }}</h1>
            <p class="mt-2 text-[var(--sf-muted)]">{{ __('storefront.shop.intro') }}</p>
            <p class="mt-2 text-sm text-[var(--sf-muted)]">{{ __('storefront.shop.indicative_notice') }}</p>
        </header>

        @if ($products->isEmpty())
            <div class="rounded-xl border border-dashed border-[var(--sf-border)] bg-[var(--sf-surface)] px-6 py-12 text-center">
                <p class="text-lg font-medium text-[var(--sf-text)]">{{ __('storefront.shop.empty_title') }}</p>
                <p class="mt-2 text-sm text-[var(--sf-muted)]">{{ __('storefront.shop.empty_message') }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $card)
                    @include('storefront.partials.product-card', ['card' => $card])
                @endforeach
            </div>

            <div class="mt-10">
                {{ $products->links() }}
            </div>
        @endif
    </main>
@endsection