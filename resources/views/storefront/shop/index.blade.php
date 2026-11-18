@extends('layouts.storefront')

@section('title', __('storefront.shop.title'))

@section('content')
    <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <header class="mb-8">
            <h1 class="text-3xl font-semibold tracking-tight text-[var(--sf-text)]">{{ __('storefront.shop.title') }}</h1>
            <p class="mt-2 text-[var(--sf-muted)]">{{ __('storefront.shop.intro') }}</p>
            <p class="mt-2 text-sm text-[var(--sf-muted)]">{{ __('storefront.shop.indicative_notice') }}</p>

            <form class="mt-6 flex flex-wrap gap-2" method="get" action="{{ route('storefront.shop.index') }}" role="search">
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
                    class="min-w-[12rem] flex-1 rounded-lg border border-[var(--sf-border)] bg-[var(--sf-bg)] px-3 py-2 text-sm text-[var(--sf-text)]"
                    placeholder="{{ __('storefront.shop.search_placeholder') }}"
                />
                <button type="submit" class="rounded-lg bg-[var(--sf-accent)] px-4 py-2 text-sm font-medium text-[var(--sf-accent-text)]">
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
                        @class([
                            'rounded-full border px-3 py-1',
                            'border-[var(--sf-accent)] bg-[var(--sf-accent)] text-[var(--sf-accent-text)]' => empty($activeCategorySlug),
                            'border-[var(--sf-border)] text-[var(--sf-text)] hover:border-[var(--sf-accent)]' => ! empty($activeCategorySlug),
                        ])
                    >
                        {{ __('storefront.shop.all_categories') }}
                    </a>
                    @foreach ($categories as $category)
                        <a
                            href="{{ route('storefront.shop.index', ['category' => $category->slug]) }}"
                            @class([
                                'rounded-full border px-3 py-1',
                                'border-[var(--sf-accent)] bg-[var(--sf-accent)] text-[var(--sf-accent-text)]' => $activeCategorySlug === $category->slug,
                                'border-[var(--sf-border)] text-[var(--sf-text)] hover:border-[var(--sf-accent)]' => $activeCategorySlug !== $category->slug,
                            ])
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