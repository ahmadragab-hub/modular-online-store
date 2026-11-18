@extends('layouts.storefront')

@section('title', __('storefront.account.orders.title'))

@section('content')
    <main class="storefront-main mx-auto max-w-4xl px-4 py-10">
        <h1 class="text-2xl font-semibold text-[var(--sf-text)]">{{ __('storefront.account.orders.title') }}</h1>

        @include('storefront.partials.account-nav')

        @if ($orders->isEmpty())
            <div class="mt-6 rounded-xl border border-dashed border-[var(--sf-border)] bg-[var(--sf-surface)] px-6 py-10 text-center text-sm text-[var(--sf-muted)]">
                {{ __('storefront.account.orders.empty') }}
            </div>
        @else
            <ul class="mt-6 divide-y divide-[var(--sf-border)] rounded-xl border border-[var(--sf-border)] bg-[var(--sf-surface)]">
                @foreach ($orders as $order)
                    <li class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium text-[var(--sf-text)]">{{ $order->order_number }}</p>
                            <p class="text-sm text-[var(--sf-muted)]">
                                {{ __('storefront.account.orders.placed_at', ['date' => $order->placed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—']) }}
                            </p>
                            <p class="text-sm text-[var(--sf-muted)]">
                                {{ __('storefront.account.orders.status_'.$order->status, ['default' => $order->status]) }}
                            </p>
                        </div>
                        <div class="text-sm">
                            <p class="font-semibold text-[var(--sf-text)]">
                                {{ $order->grand_total }} {{ $order->currency_code }}
                            </p>
                            <a class="mt-2 inline-block text-[var(--sf-accent)] hover:underline" href="{{ route('storefront.account.orders.show', ['publicUuid' => $order->public_uuid]) }}">
                                {{ __('storefront.account.orders.view') }}
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">
                {{ $orders->links() }}
            </div>
        @endif
    </main>
@endsection