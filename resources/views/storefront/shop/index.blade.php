@extends('layouts.storefront')

@section('title', __('storefront.shop.title'))

@section('content')
    <main class="sf-container py-10 sm:py-12">
        <header class="mb-10 max-w-2xl">
            <h1 class="sf-display text-3xl text-[var(--sf-text)] sm:text-4xl">{{ __('storefront.shop.title') }}</h1>
            <p class="mt-3 text-[var(--sf-muted)]">{{ __('storefront.shop.intro') }}</p>
            <p class="mt-2 text-sm text-[var(--sf-muted)]">{{ __('storefront.shop.indicative_notice') }}</p>

            <form class="mt-8 flex flex-col gap-2 sm:flex-row" method="get" action="{{ route('storefront.shop.index') }}" role="search">
                @if (! empty($activeCategorySlug))
                    <input type="hidden" name="category" value="{{ $activeCategorySlug }}">
                @endif
                <label class="sr-only" for="shop-search">{{ __('storefront.shop.search_label') }}</label>
                <input
                    id="shop-search"
                    type="search"
                    name="q"
                    value="{{ $searchQuery ?? '' }}"
                    maxlength="100"
                    class="sf-input flex-1"
                    placeholder="{{ __('storefront.shop.search_placeholder') }}"
                />
                <button type="submit" class="sf-btn sf-btn--primary sm:shrink-0">
                    {{ __('storefront.shop.search_submit') }}
                </button>
            </form>

            @if (! empty($searchQuery))
                <p class="mt-3 text-sm text-[var(--sf-muted)]">{{ __('storefront.shop.search_results_for', ['query' => $searchQuery]) }}</p>
            @endif

            @if (isset($categories) && $categories->isNotEmpty())
                <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="{{ __('storefront.shop.categories') }}">
                    <a
                        href="{{ route('storefront.shop.index') }}"
                        @class(['sf-chip', 'sf-chip--active' => empty($activeCategorySlug)])
                    >
                        {{ __('storefront.shop.all_categories') }}
                    </a>
                    @foreach ($categories as $category)
                        <a
                            href="{{ route('storefront.shop.index', ['category' => $category->slug]) }}"
                            @class(['sf-chip', 'sf-chip--active' => $activeCategorySlug === $category->slug])
                        >
                            {{ $category->name }}
                        </a>
                    @endforeach
                </nav>
                @if (! empty($activeCategoryName))
                    <p class="mt-3 text-sm text-[var(--sf-muted)]">{{ __('storefront.shop.filtered_by', ['name' => $activeCategoryName]) }}</p>
                @endif
            @endif
        </header>

        @if ($products->isEmpty())
            <div class="sf-card px-6 py-16 text-center">
                <p class="sf-display text-xl text-[var(--sf-text)]">{{ __('storefront.shop.empty_title') }}</p>
                <p class="mt-2 text-sm text-[var(--sf-muted)]">{{ __('storefront.shop.empty_message') }}</p>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3">
                @foreach ($cards as $card)
                    @include('storefront.partials.product-card', ['card' => $card])
                @endforeach
            </div>

            <div class="mt-12">
                {{ $products->links() }}
            </div>
        @endif
    </main>
@endsection