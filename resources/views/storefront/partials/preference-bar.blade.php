<div class="sf-pref-bar">
    <div class="sf-container flex flex-wrap items-center justify-between gap-2 py-2">
        <p class="hidden text-[var(--sf-muted)] sm:block">{{ __('storefront.preferences.controls') }}</p>
        <nav class="flex flex-wrap items-center gap-2" aria-label="{{ __('storefront.preferences.controls') }}">
            <div class="sf-pref-bar__group">
                @foreach ($storefrontLocales as $code => $meta)
                    <form method="POST" action="{{ route('storefront.locale') }}" class="inline">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <button
                            type="submit"
                            @class([
                                'rounded px-2.5 py-1 font-medium transition',
                                'bg-[var(--sf-accent)] text-[var(--sf-accent-text)]' => $storefrontLocale === $code,
                                'text-[var(--sf-muted)] hover:text-[var(--sf-text)]' => $storefrontLocale !== $code,
                            ])
                            aria-current="{{ $storefrontLocale === $code ? 'true' : 'false' }}"
                        >
                            {{ $meta['native'] ?? $meta['label'] }}
                        </button>
                    </form>
                @endforeach
            </div>
            <div class="sf-pref-bar__group">
                @foreach ($storefrontThemes as $code => $meta)
                    @php
                        $themeLabel = $code === 'light'
                            ? __('storefront.preferences.theme_light')
                            : __('storefront.preferences.theme_dark');
                    @endphp
                    <form method="POST" action="{{ route('storefront.theme') }}" class="inline">
                        @csrf
                        <input type="hidden" name="theme" value="{{ $code }}">
                        <button
                            type="submit"
                            @class([
                                'rounded px-2.5 py-1 font-medium transition',
                                'bg-[var(--sf-accent)] text-[var(--sf-accent-text)]' => $storefrontTheme === $code,
                                'text-[var(--sf-muted)] hover:text-[var(--sf-text)]' => $storefrontTheme !== $code,
                            ])
                            aria-pressed="{{ $storefrontTheme === $code ? 'true' : 'false' }}"
                        >
                            {{ $themeLabel }}
                        </button>
                    </form>
                @endforeach
            </div>
            <div class="sf-pref-bar__group">
                @foreach ($storefrontDisplayCurrencies as $currency)
                    <form method="POST" action="{{ route('storefront.currency') }}" class="inline">
                        @csrf
                        <input type="hidden" name="display_currency_code" value="{{ $currency->code }}">
                        <button
                            type="submit"
                            @class([
                                'rounded px-2.5 py-1 font-medium transition',
                                'bg-[var(--sf-accent)] text-[var(--sf-accent-text)]' => $storefrontDisplayCurrency === $currency->code,
                                'text-[var(--sf-muted)] hover:text-[var(--sf-text)]' => $storefrontDisplayCurrency !== $currency->code,
                            ])
                        >
                            {{ $currency->code }}
                        </button>
                    </form>
                @endforeach
            </div>
        </nav>
    </div>
</div>