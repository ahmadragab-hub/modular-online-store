@php
    /** @var array{slug: string, title: string, short_description: ?string, image_url: ?string, image_alt: string, from_price_display: string, display_currency_symbol: string, fx_available: bool, variant_summary: string} $card */
@endphp
<article class="flex flex-col overflow-hidden rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)] shadow-sm transition hover:border-[var(--sf-muted)]">
    <a href="{{ route('storefront.products.show', ['slug' => $card['slug']]) }}" class="block">
        @if ($card['image_url'])
            <img
                src="{{ $card['image_url'] }}"
                alt="{{ $card['image_alt'] }}"
                class="aspect-[4/5] w-full object-cover"
                loading="lazy"
            />
        @else
            <div class="flex aspect-[4/5] w-full items-center justify-center bg-[var(--sf-bg)] text-sm text-[var(--sf-muted)]">
                {{ __('storefront.shop.no_image') }}
            </div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-4">
        <h2 class="text-base font-semibold text-[var(--sf-text)]">
            <a href="{{ route('storefront.products.show', ['slug' => $card['slug']]) }}" class="hover:underline">
                {{ $card['title'] }}
            </a>
        </h2>
        @if (! empty($card['short_description']))
            <p class="line-clamp-2 text-sm text-[var(--sf-muted)]">{{ $card['short_description'] }}</p>
        @endif
        <p class="text-sm text-[var(--sf-muted)]">{{ $card['variant_summary'] }}</p>
        <p class="mt-auto text-sm font-medium text-[var(--sf-text)]">
            <span class="text-[var(--sf-muted)] font-normal">{{ __('storefront.shop.from') }}</span>
            {{ $card['display_currency_symbol'] }}{{ $card['from_price_display'] }}
        </p>
    </div>
</article>