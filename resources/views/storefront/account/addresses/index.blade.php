@extends('layouts.storefront')

@section('title', __('storefront.account.addresses.title'))

@section('content')
    <main class="storefront-main mx-auto max-w-3xl px-4 py-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-[var(--sf-text)]">{{ __('storefront.account.addresses.title') }}</h1>
            <a href="{{ route('storefront.account.addresses.create') }}" class="rounded-lg bg-[var(--sf-accent)] px-4 py-2 text-sm font-medium text-[var(--sf-accent-text)]">
                {{ __('storefront.account.addresses.add') }}
            </a>
        </div>

        @include('storefront.partials.account-nav')

        @if (session('account_status'))
            <p class="mb-4 rounded-lg border border-[var(--sf-border)] bg-[var(--sf-surface)] px-4 py-3 text-sm" role="status">
                {{ session('account_status') }}
            </p>
        @endif

        @if ($addresses->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-[var(--sf-border)] p-8 text-center text-sm text-[var(--sf-muted)]">
                {{ __('storefront.account.addresses.empty') }}
            </p>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($addresses as $address)
                    <li class="rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)] p-5 text-sm">
                        <p class="font-medium text-[var(--sf-text)]">
                            {{ $address->label ?: __('storefront.account.addresses.unlabeled') }}
                        </p>
                        <p class="mt-2 text-[var(--sf-muted)]">
                            {{ $address->first_name }} {{ $address->last_name }}<br>
                            {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif<br>
                            {{ $address->city }}@if ($address->region), {{ $address->region }}@endif {{ $address->postal_code }}<br>
                            {{ $address->country_code }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                            @if ($address->is_default_shipping)
                                <span class="rounded bg-[var(--sf-bg)] px-2 py-1">{{ __('storefront.account.addresses.default_shipping') }}</span>
                            @endif
                            @if ($address->is_default_billing)
                                <span class="rounded bg-[var(--sf-bg)] px-2 py-1">{{ __('storefront.account.addresses.default_billing') }}</span>
                            @endif
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('storefront.account.addresses.edit', $address) }}" class="rounded border border-[var(--sf-border)] px-3 py-1.5 text-xs font-medium">
                                {{ __('storefront.account.addresses.edit') }}
                            </a>
                            @unless ($address->is_default_shipping)
                                <form method="post" action="{{ route('storefront.account.addresses.default-shipping', $address) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded border border-[var(--sf-border)] px-3 py-1.5 text-xs font-medium">
                                        {{ __('storefront.account.addresses.set_default_shipping') }}
                                    </button>
                                </form>
                            @endunless
                            @unless ($address->is_default_billing)
                                <form method="post" action="{{ route('storefront.account.addresses.default-billing', $address) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded border border-[var(--sf-border)] px-3 py-1.5 text-xs font-medium">
                                        {{ __('storefront.account.addresses.set_default_billing') }}
                                    </button>
                                </form>
                            @endunless
                            <form
                                method="post"
                                action="{{ route('storefront.account.addresses.destroy', $address) }}"
                                class="inline"
                                onsubmit="return confirm(@js(__('storefront.account.addresses.delete_confirm')))"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700">
                                    {{ __('storefront.account.addresses.delete') }}
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
@endsection