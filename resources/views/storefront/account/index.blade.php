@extends('layouts.storefront')

@section('title', __('storefront.account.title'))

@section('content')
    <main class="storefront-main mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-semibold text-[var(--sf-text)]">{{ __('storefront.account.title') }}</h1>

        @include('storefront.partials.account-nav')

        @if (session('account_status'))
            <p class="mb-4 rounded-lg border border-[var(--sf-border)] bg-[var(--sf-surface)] px-4 py-3 text-sm text-[var(--sf-text)]" role="status">
                {{ session('account_status') }}
            </p>
        @endif

        <div class="rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)] p-6">
            <h2 class="text-lg font-semibold text-[var(--sf-text)]">{{ __('storefront.account.dashboard.greeting', ['name' => $customer->first_name]) }}</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex flex-wrap gap-2">
                    <dt class="font-medium text-[var(--sf-muted)]">{{ __('storefront.account.dashboard.email') }}</dt>
                    <dd class="text-[var(--sf-text)]">{{ $customer->email }}</dd>
                </div>
                <div class="flex flex-wrap gap-2">
                    <dt class="font-medium text-[var(--sf-muted)]">{{ __('storefront.account.dashboard.name') }}</dt>
                    <dd class="text-[var(--sf-text)]">{{ $customer->first_name }} {{ $customer->last_name }}</dd>
                </div>
                <div class="flex flex-wrap gap-2">
                    <dt class="font-medium text-[var(--sf-muted)]">{{ __('storefront.account.dashboard.orders') }}</dt>
                    <dd class="text-[var(--sf-text)]">{{ __('storefront.account.dashboard.order_count', ['count' => $orderCount]) }}</dd>
                </div>
                <div class="flex flex-wrap gap-2">
                    <dt class="font-medium text-[var(--sf-muted)]">{{ __('storefront.account.dashboard.addresses') }}</dt>
                    <dd class="text-[var(--sf-text)]">{{ $customer->addresses->count() }}</dd>
                </div>
            </dl>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('storefront.account.profile.edit') }}" class="rounded-lg bg-[var(--sf-accent)] px-4 py-2 text-sm font-medium text-[var(--sf-accent-text)]">
                    {{ __('storefront.account.dashboard.edit_profile') }}
                </a>
                <a href="{{ route('storefront.account.addresses.index') }}" class="rounded-lg border border-[var(--sf-border)] px-4 py-2 text-sm font-medium text-[var(--sf-text)]">
                    {{ __('storefront.account.dashboard.manage_addresses') }}
                </a>
            </div>
        </div>
    </main>
@endsection