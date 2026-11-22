@php
    $current = request()->route()?->getName() ?? '';
    $link = static function (string $route, string $label) use ($current): string {
        $active = str_starts_with($current, str_replace('.index', '', $route)) || $current === $route;

        return sprintf(
            '<a href="%s" class="admin-sidebar__link%s">%s</a>',
            route($route),
            $active ? ' admin-sidebar__link--active' : '',
            e($label),
        );
    };
@endphp
<aside class="admin-sidebar" aria-label="{{ __('admin.nav.main') }}">
    <a href="{{ route('admin.dashboard') }}" class="admin-sidebar__brand">{{ __('admin.brand') }}</a>
    <nav class="admin-sidebar__nav">
        {!! $link('admin.dashboard', __('admin.nav.dashboard')) !!}
        {!! $link('admin.products.index', __('admin.nav.products')) !!}
        {!! $link('admin.categories.index', __('admin.nav.categories')) !!}
        {!! $link('admin.inventory.index', __('admin.nav.inventory')) !!}
        {!! $link('admin.orders.index', __('admin.nav.orders')) !!}
        {!! $link('admin.customers.index', __('admin.nav.customers')) !!}
        {!! $link('admin.settings.edit', __('admin.nav.settings')) !!}
        {!! $link('admin.exchange-rates.index', __('admin.nav.exchange_rates')) !!}
    </nav>
    <div class="mt-auto space-y-2 border-t border-white/10 pt-4 text-sm">
        @include('admin.partials.preference-bar')
        <form method="post" action="{{ route('admin.logout.destroy') }}">
            @csrf
            <button type="submit" class="admin-sidebar__link w-full text-start">{{ __('admin.nav.logout') }}</button>
        </form>
    </div>
</aside>
<nav class="admin-mobile-nav lg:hidden" aria-label="{{ __('admin.nav.main') }}">
    <a href="{{ route('admin.dashboard') }}">{{ __('admin.nav.dashboard') }}</a>
    <a href="{{ route('admin.products.index') }}">{{ __('admin.nav.products') }}</a>
    <a href="{{ route('admin.orders.index') }}">{{ __('admin.nav.orders') }}</a>
    <a href="{{ route('admin.inventory.index') }}">{{ __('admin.nav.inventory') }}</a>
</nav>