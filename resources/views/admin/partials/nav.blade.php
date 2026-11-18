<header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <a href="{{ route('admin.dashboard') }}" class="text-lg font-semibold">{{ __('admin.brand') }}</a>
        <nav class="flex flex-wrap gap-3 text-sm">
            <a href="{{ route('admin.dashboard') }}">{{ __('admin.nav.dashboard') }}</a>
            <a href="{{ route('admin.products.index') }}">{{ __('admin.nav.products') }}</a>
            <a href="{{ route('admin.categories.index') }}">{{ __('admin.nav.categories') }}</a>
            <a href="{{ route('admin.inventory.index') }}">{{ __('admin.nav.inventory') }}</a>
            <a href="{{ route('admin.orders.index') }}">{{ __('admin.nav.orders') }}</a>
            <a href="{{ route('admin.customers.index') }}">{{ __('admin.nav.customers') }}</a>
            <a href="{{ route('admin.settings.edit') }}">{{ __('admin.nav.settings') }}</a>
            <a href="{{ route('admin.exchange-rates.index') }}">{{ __('admin.nav.exchange_rates') }}</a>
        </nav>
        <div class="flex items-center gap-2 text-sm">
            @include('admin.partials.preference-bar')
            <form method="post" action="{{ route('admin.logout.destroy') }}">
                @csrf
                <button type="submit">{{ __('admin.nav.logout') }}</button>
            </form>
        </div>
    </div>
</header>