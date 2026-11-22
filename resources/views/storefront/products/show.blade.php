@extends('layouts.storefront')

@section('title', $detail['title'])

@section('content')
    <main class="sf-container py-10 sm:py-12">
        <p class="mb-8">
            <a href="{{ route('storefront.shop.index') }}" class="text-sm text-[var(--sf-highlight)] hover:underline">
                {{ __('storefront.products.back_to_shop') }}
            </a>
        </p>

        <div class="grid gap-10 lg:grid-cols-2 lg:gap-14">
            <section aria-label="{{ __('storefront.products.images') }}">
                @if ($detail['images'] === [])
                    <div class="sf-img-fallback sf-card aspect-[4/5] w-full">
                        <span>{{ __('storefront.shop.no_image') }}</span>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($detail['images'] as $image)
                            @if ($image['url'])
                                <img
                                    src="{{ $image['url'] }}"
                                    alt="{{ $image['alt'] }}"
                                    class="sf-card aspect-[4/5] w-full object-cover"
                                />
                            @else
                                <div class="sf-img-fallback sf-card aspect-[4/5] w-full">
                                    <span>{{ __('storefront.shop.no_image') }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <h1 class="sf-display text-3xl text-[var(--sf-text)] sm:text-4xl">{{ $detail['title'] }}</h1>
                @if (! empty($detail['short_description']))
                    <p class="mt-4 text-lg text-[var(--sf-muted)]">{{ $detail['short_description'] }}</p>
                @endif
                @if (! empty($detail['description']))
                    <div class="mt-6 text-sm leading-relaxed text-[var(--sf-text)]">
                        {!! nl2br(e($detail['description'])) !!}
                    </div>
                @endif

                <p class="mt-6 text-xs text-[var(--sf-muted)]">{{ __('storefront.shop.indicative_notice') }}</p>

                <h2 class="sf-display mt-10 text-xl text-[var(--sf-text)]">{{ __('storefront.products.variants') }}</h2>
                <ul class="mt-4 divide-y divide-[var(--sf-border)] sf-card">
                    @foreach ($detail['variants'] as $variant)
                        <li class="flex flex-wrap items-center justify-between gap-4 px-4 py-4 text-sm sm:px-5">
                            <div>
                                <p class="font-medium text-[var(--sf-text)]">{{ $variant['variant_label'] }}</p>
                                <p class="text-[var(--sf-muted)]">{{ __('storefront.products.sku') }}: {{ $variant['sku'] }}</p>
                            </div>
                            <div class="text-end">
                                <p class="text-base font-semibold text-[var(--sf-text)]">{{ $variant['price_display'] }}</p>
                                @if ($variant['in_stock'])
                                    <p class="text-[var(--sf-muted)]">
                                        {{ __('storefront.products.in_stock', ['count' => $variant['available_quantity']]) }}
                                    </p>
                                    <form method="post" action="{{ route('storefront.cart.items.store') }}" class="mt-3">
                                        @csrf
                                        <input type="hidden" name="product_variant_id" value="{{ $variant['id'] }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="sf-btn sf-btn--primary text-xs">
                                            {{ __('storefront.products.add_to_cart') }}
                                        </button>
                                    </form>
                                @else
                                    <p class="font-medium text-[var(--sf-danger)]">{{ __('storefront.products.out_of_stock') }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </main>
@endsection