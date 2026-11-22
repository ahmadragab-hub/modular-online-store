@extends('layouts.storefront')

@section('title', __('storefront.home.title'))

@section('content')
    <section class="sf-container py-10 sm:py-14">
        <div class="sf-hero px-6 py-14 sm:px-12 sm:py-20 lg:py-24">
            <p class="text-xs font-medium uppercase tracking-[0.2em] text-[var(--sf-highlight)]">
                {{ __('storefront.tagline') }}
            </p>
            <h1 class="sf-display mt-4 max-w-2xl text-4xl text-[var(--sf-text)] sm:text-5xl lg:text-6xl">
                {{ __('storefront.home.hero_title') }}
            </h1>
            <p class="mt-6 max-w-xl text-base leading-relaxed text-[var(--sf-muted)] sm:text-lg">
                {{ __('storefront.home.hero_intro') }}
            </p>
            <div class="mt-10 flex flex-wrap gap-3">
                <a href="{{ route('storefront.shop.index') }}" class="sf-btn sf-btn--primary">
                    {{ __('storefront.home.browse_shop') }}
                </a>
                <a href="{{ route('storefront.shop.index') }}" class="sf-btn sf-btn--secondary">
                    {{ __('storefront.home.new_arrivals') }}
                </a>
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="sf-container pb-12" aria-labelledby="home-categories-title">
            <div class="mb-6 flex items-end justify-between gap-4">
                <h2 id="home-categories-title" class="sf-display text-2xl text-[var(--sf-text)] sm:text-3xl">
                    {{ __('storefront.home.categories_title') }}
                </h2>
                <a href="{{ route('storefront.shop.index') }}" class="text-sm text-[var(--sf-highlight)] hover:underline">
                    {{ __('storefront.home.shop_all') }}
                </a>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:gap-4">
                @foreach ($categories as $category)
                    <a
                        href="{{ route('storefront.shop.index', ['category' => $category->slug]) }}"
                        class="sf-card group p-5 transition hover:border-[var(--sf-highlight)]"
                    >
                        <p class="sf-display text-lg text-[var(--sf-text)] group-hover:text-[var(--sf-highlight)]">
                            {{ $category->name }}
                        </p>
                        <p class="mt-2 text-xs text-[var(--sf-muted)]">{{ __('storefront.home.explore_category') }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($featuredCards !== [])
        <section class="sf-container border-t border-[var(--sf-border)] py-12 sm:py-14" aria-labelledby="home-featured-title">
            <h2 id="home-featured-title" class="sf-display text-2xl text-[var(--sf-text)] sm:text-3xl">
                {{ __('storefront.home.featured_title') }}
            </h2>
            <p class="mt-2 max-w-xl text-sm text-[var(--sf-muted)]">{{ __('storefront.home.featured_intro') }}</p>
            <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                @foreach ($featuredCards as $card)
                    @include('storefront.partials.product-card', ['card' => $card])
                @endforeach
            </div>
        </section>
    @endif
@endsection