<header class="sf-header">
    <div class="sf-container flex flex-wrap items-center justify-between gap-4 py-4">
        <a href="{{ route('storefront.home') }}" class="sf-display text-2xl text-[var(--sf-text)] no-underline sm:text-3xl">
            {{ __('storefront.brand') }}
        </a>

        <nav class="hidden items-center gap-6 text-sm font-medium md:flex" aria-label="{{ __('storefront.nav.main') }}">
            <a href="{{ route('storefront.home') }}" class="text-[var(--sf-muted)] transition hover:text-[var(--sf-text)]">
                {{ __('storefront.nav.home') }}
            </a>
            <a href="{{ route('storefront.shop.index') }}" class="text-[var(--sf-muted)] transition hover:text-[var(--sf-text)]">
                {{ __('storefront.nav.shop') }}
            </a>
            <a href="{{ route('storefront.cart.show') }}" class="text-[var(--sf-muted)] transition hover:text-[var(--sf-text)]">
                {{ __('storefront.nav.cart') }}
            </a>
            @auth
                @if (auth()->user()->role === \App\Domains\Identity\Models\User::ROLE_CUSTOMER && auth()->user()->isActive())
                    <a href="{{ route('storefront.account.index') }}" class="text-[var(--sf-muted)] transition hover:text-[var(--sf-text)]">
                        {{ __('storefront.nav.account') }}
                    </a>
                @endif
            @else
                <a href="{{ route('storefront.login.create') }}" class="text-[var(--sf-muted)] transition hover:text-[var(--sf-text)]">
                    {{ __('storefront.nav.login') }}
                </a>
            @endauth
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('storefront.shop.index') }}" class="sf-btn sf-btn--ghost hidden sm:inline-flex" aria-label="{{ __('storefront.shop.search_label') }}">
                {{ __('storefront.nav.search') }}
            </a>
            <a href="{{ route('storefront.cart.show') }}" class="sf-btn sf-btn--secondary text-sm">
                {{ __('storefront.nav.cart') }}
            </a>
            <button
                type="button"
                class="sf-btn sf-btn--secondary px-3 md:hidden"
                data-sf-nav-toggle
                aria-expanded="false"
                aria-controls="sf-mobile-nav"
            >
                {{ __('storefront.nav.menu') }}
            </button>
        </div>
    </div>

    <div id="sf-mobile-nav" class="sf-nav-mobile-panel md:hidden" data-sf-nav-panel hidden>
        <nav class="flex flex-col gap-3 text-sm font-medium" aria-label="{{ __('storefront.nav.main') }}">
            <a href="{{ route('storefront.home') }}">{{ __('storefront.nav.home') }}</a>
            <a href="{{ route('storefront.shop.index') }}">{{ __('storefront.nav.shop') }}</a>
            <a href="{{ route('storefront.cart.show') }}">{{ __('storefront.nav.cart') }}</a>
            @auth
                @if (auth()->user()->role === \App\Domains\Identity\Models\User::ROLE_CUSTOMER && auth()->user()->isActive())
                    <a href="{{ route('storefront.account.index') }}">{{ __('storefront.nav.account') }}</a>
                    <form method="post" action="{{ route('storefront.logout.destroy') }}">
                        @csrf
                        <button type="submit" class="sf-btn sf-btn--ghost px-0">{{ __('storefront.nav.logout') }}</button>
                    </form>
                @endif
            @else
                <a href="{{ route('storefront.login.create') }}">{{ __('storefront.nav.login') }}</a>
                <a href="{{ route('storefront.register.create') }}">{{ __('storefront.nav.register') }}</a>
            @endauth
        </nav>
    </div>
</header>