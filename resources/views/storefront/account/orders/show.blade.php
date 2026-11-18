@extends('layouts.storefront')

@section('title', __('storefront.account.orders.detail_title', ['number' => $order->order_number]))

@section('content')
    <main class="storefront-main mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-semibold text-[var(--sf-text)]">{{ __('storefront.account.orders.detail_title', ['number' => $order->order_number]) }}</h1>

        @include('storefront.partials.account-nav')

        <div class="mt-6 space-y-6">
            <dl class="rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)] p-6 text-sm">
                <div class="mb-3">
                    <dt class="font-medium text-[var(--sf-muted)]">{{ __('storefront.account.orders.status') }}</dt>
                    <dd>{{ __('storefront.account.orders.status_'.$order->status, ['default' => $order->status]) }}</dd>
                </div>
                <div class="mb-3">
                    <dt class="font-medium text-[var(--sf-muted)]">{{ __('storefront.checkout.total') }}</dt>
                    <dd>{{ $order->grand_total }} {{ $order->currency_code }}</dd>
                </div>
                @if ($latestPayment !== null)
                    <div>
                        <dt class="font-medium text-[var(--sf-muted)]">{{ __('storefront.checkout.payment_status') }}</dt>
                        <dd>{{ __('storefront.checkout.payment_status_'.$latestPayment->status, ['default' => $latestPayment->status]) }}</dd>
                    </div>
                @endif
            </dl>

            <div class="rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)] p-6">
                <h2 class="text-lg font-semibold text-[var(--sf-text)]">{{ __('storefront.account.orders.lines') }}</h2>
                <ul class="mt-4 divide-y divide-[var(--sf-border)] text-sm">
                    @foreach ($order->items as $item)
                        <li class="flex flex-wrap justify-between gap-2 py-3">
                            <span>{{ $item->product_name }} — {{ $item->variant_label }}</span>
                            <span>{{ $item->quantity }} × {{ $item->unit_price }} {{ $order->currency_code }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('storefront.account.orders.index') }}" class="rounded-lg border border-[var(--sf-border)] px-4 py-2 text-sm font-medium text-[var(--sf-text)]">
                    {{ __('storefront.account.orders.back_to_list') }}
                </a>
                <a href="{{ route('storefront.checkout.success', ['publicUuid' => $order->public_uuid]) }}" class="rounded-lg bg-[var(--sf-accent)] px-4 py-2 text-sm font-medium text-[var(--sf-accent-text)]">
                    {{ __('storefront.account.orders.confirmation_page') }}
                </a>
            </div>
        </div>
    </main>
@endsection