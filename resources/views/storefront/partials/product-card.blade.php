@php
    /** @var array{slug: string, title: string, short_description: ?string, image_url: ?string, image_alt: string, from_price_display: string, display_currency_symbol: string, fx_available: bool, variant_summary: string} $card */
@endphp
<article class="sf-product-card">
    <a href="{{ route('storefront.products.show', ['slug' => $card['slug']]) }}" class="sf-product-card__media block">
        @if ($card['image_url'])
            <img
                src="{{ $card['image_url'] }}"
                alt="{{ $card['image_alt'] }}"
                loading="lazy"
            />
        @else
            <div class="sf-img-fallback">
                <span>{{ __('storefront.shop.no_image') }}</span>
            </div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-[var(--sf-text)] sm:text-base">
            <a href="{{ route('storefront.products.show', ['slug' => $card['slug']]) }}" class="hover:text-[var(--sf-highlight)]">
                {{ $card['title'] }}
            </a>
        </h2>
        @if (! empty($card['short_description']))
            <p class="line-clamp-2 text-xs text-[var(--sf-muted)] sm:text-sm">{{ $card['short_description'] }}</p>
        @endif
        <p class="text-xs text-[var(--sf-muted)]">{{ $card['variant_summary'] }}</p>
        <p class="mt-auto pt-2 text-sm font-medium text-[var(--sf-text)]">
            <span class="font-normal text-[var(--sf-muted)]">{{ __('storefront.shop.from') }}</span>
            {{ $card['display_currency_symbol'] }}{{ $card['from_price_display'] }}
        </p>
    </div>
</article>