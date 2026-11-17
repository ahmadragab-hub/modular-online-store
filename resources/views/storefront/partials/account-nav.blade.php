<nav class="mb-8 flex flex-wrap gap-3 border-b border-[var(--sf-border)] pb-4 text-sm font-medium" aria-label="{{ __('storefront.account.nav.label') }}">
    <a
        href="{{ route('storefront.account.index') }}"
        @class([
            'rounded-md px-3 py-1.5',
            'bg-[var(--sf-accent)] text-[var(--sf-accent-text)]' => request()->routeIs('storefront.account.index'),
            'text-[var(--sf-muted)] hover:text-[var(--sf-text)]' => ! request()->routeIs('storefront.account.index'),
        ])
    >
        {{ __('storefront.account.nav.dashboard') }}
    </a>
    <a
        href="{{ route('storefront.account.profile.edit') }}"
        @class([
            'rounded-md px-3 py-1.5',
            'bg-[var(--sf-accent)] text-[var(--sf-accent-text)]' => request()->routeIs('storefront.account.profile.*'),
            'text-[var(--sf-muted)] hover:text-[var(--sf-text)]' => ! request()->routeIs('storefront.account.profile.*'),
        ])
    >
        {{ __('storefront.account.nav.profile') }}
    </a>
    <a
        href="{{ route('storefront.account.addresses.index') }}"
        @class([
            'rounded-md px-3 py-1.5',
            'bg-[var(--sf-accent)] text-[var(--sf-accent-text)]' => request()->routeIs('storefront.account.addresses.*'),
            'text-[var(--sf-muted)] hover:text-[var(--sf-text)]' => ! request()->routeIs('storefront.account.addresses.*'),
        ])
    >
        {{ __('storefront.account.nav.addresses') }}
    </a>
</nav>