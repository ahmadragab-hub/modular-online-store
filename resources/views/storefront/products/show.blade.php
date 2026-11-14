@extends('layouts.storefront')

@section('title', $detail['title'])

@section('content')
    <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <p class="mb-6">
            <a href="{{ route('storefront.shop.index') }}" class="text-sm text-[var(--sf-muted)] hover:text-[var(--sf-text)] hover:underline">
                {{ __('storefront.products.back_to_shop') }}
            </a>
        </p>

        <div class="grid gap-10 lg:grid-cols-2">
            <section aria-label="{{ __('storefront.products.images') }}">
                @if ($detail['images'] === [])
                    <div class="flex aspect-[4/5] w-full items-center justify-center rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)] text-[var(--sf-muted)]">
                        {{ __('storefront.shop.no_image') }}
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($detail['images'] as $image)
                            @if ($image['url'])
                                <img
                                    src="{{ $image['url'] }}"
                                    alt="{{ $image['alt'] }}"
                                    class="w-full rounded-xl border border-[var(--sf-border)] object-cover"
                                />
                            @else
                                <div class="flex aspect-[4/5] w-full items-center justify-center rounded-xl border border-[var(--sf-border)] bg-[var(--sf-bg)] text-[var(--sf-muted)]">
                                    {{ __('storefront.shop.no_image') }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <h1 class="text-3xl font-semibold text-[var(--sf-text)]">{{ $detail['title'] }}</h1>
                @if (! empty($detail['short_description']))
                    <p class="mt-3 text-lg text-[var(--sf-muted)]">{{ $detail['short_description'] }}</p>
                @endif
                @if (! empty($detail['description']))
                    <div class="prose prose-sm mt-6 max-w-none text-[var(--sf-text)] dark:prose-invert">
                        {!! nl2br(e($detail['description'])) !!}
                    </div>
                @endif

                <p class="mt-4 text-sm text-[var(--sf-muted)]">{{ __('storefront.shop.indicative_notice') }}</p>
                @if (! $detail['fx_available'])
                    <p class="mt-2 text-sm text-[var(--sf-muted)]">{{ __('storefront.cart.fx_unavailable') }}</p>
                @endif
                <h2 class="mt-10 text-lg font-semibold text-[var(--sf-text)]">{{ __('storefront.products.variants') }}</h2>
                <ul class="mt-4 divide-y divide-[var(--sf-border)] rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)]">
                    @foreach ($detail['variants'] as $variant)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                            <div>
                                <p class="font-medium text-[var(--sf-text)]">{{ $variant['variant_label'] }}</p>
                                <p class="text-[var(--sf-muted)]">{{ __('storefront.products.sku') }}: {{ $variant['sku'] }}</p>
                            </div>
                            <div class="text-end">
                                <p class="font-medium text-[var(--sf-text)]">{{ $variant['price_display'] }}</p>
                                @if ($variant['in_stock'])
                                    <p class="text-[var(--sf-muted)]">
                                        {{ __('storefront.products.in_stock', ['count' => $variant['available_quantity']]) }}
                                    </p>
                                    <form method="post" action="{{ route('storefront.cart.items.store') }}" class="mt-2 inline-flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="product_variant_id" value="{{ $variant['id'] }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="rounded-md bg-[var(--sf-accent)] px-3 py-1.5 text-xs font-medium text-[var(--sf-accent-text)]">
                                            {{ __('storefront.products.add_to_cart') }}
                                        </button>
                                    </form>
                                @else
                                    <p class="font-medium text-red-600 dark:text-red-400">{{ __('storefront.products.out_of_stock') }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </main>
@endsection