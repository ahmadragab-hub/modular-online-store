@if (app()->environment(['local', 'testing']))
    <aside
        class="demo-login-hints mt-4 rounded-lg px-4 py-3 text-xs leading-relaxed"
        aria-label="{{ __('storefront.auth.demo.hints_label') }}"
    >
        <p class="font-medium text-[var(--sf-text,var(--adm-text))]">{{ __('storefront.auth.demo.hints_title') }}</p>
        <p class="mt-2 break-all">
            <span>{{ __('storefront.auth.demo.username') }}:</span>
            <span class="font-mono text-[var(--sf-text,var(--adm-text))]">{{ $demoLoginEmail }}</span>
        </p>
        <p class="mt-1 break-all">
            <span>{{ __('storefront.auth.demo.password') }}:</span>
            <span class="font-mono text-[var(--sf-text,var(--adm-text))]">{{ $demoLoginPassword }}</span>
        </p>
    </aside>
@endif