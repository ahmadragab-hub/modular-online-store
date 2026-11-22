<footer class="sf-footer">
    <div class="sf-container grid gap-10 py-12 md:grid-cols-3">
        <div>
            <p class="sf-display text-xl text-[var(--sf-text)]">{{ __('storefront.brand') }}</p>
            <p class="mt-3 max-w-xs text-sm leading-relaxed text-[var(--sf-muted)]">
                {{ __('storefront.footer.about') }}
            </p>
        </div>
        <nav class="flex flex-col gap-2 text-sm" aria-label="{{ __('storefront.footer.shop_links') }}">
            <span class="font-medium text-[var(--sf-text)]">{{ __('storefront.footer.shop') }}</span>
            <a href="{{ route('storefront.shop.index') }}" class="text-[var(--sf-muted)] hover:text-[var(--sf-text)]">{{ __('storefront.nav.shop') }}</a>
            <a href="{{ route('storefront.cart.show') }}" class="text-[var(--sf-muted)] hover:text-[var(--sf-text)]">{{ __('storefront.nav.cart') }}</a>
        </nav>
        <nav class="flex flex-col gap-2 text-sm" aria-label="{{ __('storefront.footer.account_links') }}">
            <span class="font-medium text-[var(--sf-text)]">{{ __('storefront.footer.account') }}</span>
            @auth
                <a href="{{ route('storefront.account.index') }}" class="text-[var(--sf-muted)] hover:text-[var(--sf-text)]">{{ __('storefront.nav.account') }}</a>
            @else
                <a href="{{ route('storefront.login.create') }}" class="text-[var(--sf-muted)] hover:text-[var(--sf-text)]">{{ __('storefront.nav.login') }}</a>
                <a href="{{ route('storefront.register.create') }}" class="text-[var(--sf-muted)] hover:text-[var(--sf-text)]">{{ __('storefront.nav.register') }}</a>
            @endauth
        </nav>
    </div>
    <div class="border-t border-[var(--sf-border)] py-4">
        <p class="sf-container text-center text-xs text-[var(--sf-muted)]">
            © {{ date('Y') }} {{ __('storefront.brand') }}. {{ __('storefront.footer.rights') }}
        </p>
    </div>
</footer>