@extends('layouts.storefront')

@section('title', __('storefront.cart.title'))

@section('content')
    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold text-[var(--sf-text)]">{{ __('storefront.cart.title') }}</h1>

        @if (session('cart_status'))
            <p class="mt-4 rounded-lg border border-[var(--sf-border)] bg-[var(--sf-surface)] px-4 py-3 text-sm text-[var(--sf-text)]" role="status">
                {{ session('cart_status') }}
            </p>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200" role="alert">
                <ul class="list-disc ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($presentation['lines'] === [])
            <div class="mt-10 rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)] p-10 text-center">
                <h2 class="text-lg font-semibold text-[var(--sf-text)]">{{ __('storefront.cart.empty_title') }}</h2>
                <p class="mt-2 text-[var(--sf-muted)]">{{ __('storefront.cart.empty_message') }}</p>
                <a href="{{ route('storefront.shop.index') }}" class="mt-6 inline-block rounded-lg bg-[var(--sf-accent)] px-5 py-2.5 text-sm font-medium text-[var(--sf-accent-text)]">
                    {{ __('storefront.cart.continue_shopping') }}
                </a>
            </div>
        @else
            <ul class="mt-8 divide-y divide-[var(--sf-border)] rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)]">
                @foreach ($presentation['lines'] as $line)
                    <li class="flex flex-col gap-4 p-4 sm:flex-row sm:items-start">
                        <div class="h-28 w-24 shrink-0 overflow-hidden rounded-lg border border-[var(--sf-border)] bg-[var(--sf-bg)]">
                            @if ($line['image_url'])
                                <img src="{{ $line['image_url'] }}" alt="{{ $line['image_alt'] }}" class="h-full w-full object-cover" />
                            @else
                                <div class="flex h-full items-center justify-center px-2 text-center text-xs text-[var(--sf-muted)]">
                                    {{ __('storefront.shop.no_image') }}
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-[var(--sf-text)]">{{ $line['product_name'] }}</p>
                            <p class="text-sm text-[var(--sf-muted)]">{{ $line['variant_label'] }}</p>
                            <p class="text-xs text-[var(--sf-muted)]">{{ __('storefront.products.sku') }}: {{ $line['sku'] }}</p>
                            <p class="mt-2 text-sm text-[var(--sf-text)]">
                                {{ $presentation['display_currency_symbol'] }}{{ $line['unit_price_display'] }}
                                <span class="text-[var(--sf-muted)]">× {{ $line['quantity'] }}</span>
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-3">
                            <p class="font-medium text-[var(--sf-text)]">
                                {{ $presentation['display_currency_symbol'] }}{{ $line['line_subtotal_display'] }}
                            </p>
                            <form method="post" action="{{ route('storefront.cart.items.update', ['cartItem' => $line['id']]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label class="sr-only" for="qty-{{ $line['id'] }}">{{ __('storefront.cart.quantity') }}</label>
                                <input
                                    id="qty-{{ $line['id'] }}"
                                    type="number"
                                    name="quantity"
                                    value="{{ $line['quantity'] }}"
                                    min="1"
                                    class="w-16 rounded border border-[var(--sf-border)] bg-[var(--sf-bg)] px-2 py-1 text-sm"
                                    required
                                />
                                <button type="submit" class="text-sm font-medium text-[var(--sf-accent)] hover:underline">
                                    {{ __('storefront.cart.update') }}
                                </button>
                            </form>
                            <form method="post" action="{{ route('storefront.cart.items.destroy', ['cartItem' => $line['id']]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                                    {{ __('storefront.cart.remove') }}
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)] p-6">
                @if (! $presentation['fx_available'])
                    <p class="mb-4 text-sm text-[var(--sf-muted)]">{{ __('storefront.cart.fx_unavailable') }}</p>
                @endif
                @if ($presentation['amounts_are_indicative'])
                    <p class="mb-4 text-sm text-[var(--sf-muted)]">{{ __('storefront.cart.indicative_notice') }}</p>
                @endif
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-lg font-semibold text-[var(--sf-text)]">
                        {{ __('storefront.cart.subtotal') }}
                        <span class="ms-2">{{ $presentation['display_currency_symbol'] }}{{ $presentation['subtotal_display'] }}</span>
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('storefront.shop.index') }}" class="rounded-lg border border-[var(--sf-border)] px-5 py-2.5 text-sm font-medium text-[var(--sf-text)]">
                            {{ __('storefront.cart.continue_shopping') }}
                        </a>
                        <a href="{{ route('storefront.checkout.show') }}" class="rounded-lg bg-[var(--sf-accent)] px-5 py-2.5 text-sm font-medium text-[var(--sf-accent-text)]">
                            {{ __('storefront.cart.checkout') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </main>
@endsection