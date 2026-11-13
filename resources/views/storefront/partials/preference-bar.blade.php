<header class="storefront-header border-b border-[var(--sf-border)] bg-[var(--sf-surface)]">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="{{ route('storefront.home') }}" class="text-lg font-semibold text-[var(--sf-text)]">
            {{ __('storefront.brand') }}
        </a>

        <nav class="flex flex-wrap items-center gap-3 text-sm" aria-label="{{ __('storefront.preferences.language') }}">
            <div class="flex items-center gap-1 rounded-lg border border-[var(--sf-border)] p-1">
                <span class="sr-only">{{ __('storefront.preferences.language') }}</span>
                @foreach ($storefrontLocales as $code => $meta)
                    <form method="POST" action="{{ route('storefront.locale') }}" class="inline">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <button
                            type="submit"
                            @class([
                                'rounded-md px-3 py-1.5 font-medium transition',
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

            <div class="flex items-center gap-1 rounded-lg border border-[var(--sf-border)] p-1">
                <span class="sr-only">{{ __('storefront.preferences.theme') }}</span>
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
                                'rounded-md px-3 py-1.5 font-medium transition',
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
        </nav>
    </div>
</header>
