@extends('layouts.storefront')

@section('title', __('storefront.checkout.success_title'))

@section('content')
    <main class="storefront-main mx-auto max-w-2xl px-4 py-10">
        <h1 class="mb-4 text-2xl font-semibold">{{ __('storefront.checkout.success_title') }}</h1>
        <p class="mb-2">{{ __('storefront.checkout.success_intro') }}</p>

        @if ($errors->has('payment'))
            <p class="mb-4 text-sm text-rose-700">{{ $errors->first('payment') }}</p>
        @endif

        <dl class="rounded border p-4 text-sm">
            <dt class="font-semibold">{{ __('storefront.checkout.order_number') }}</dt>
            <dd class="mb-2">{{ $order->order_number }}</dd>
            <dt class="font-semibold">{{ __('storefront.checkout.total') }}</dt>
            <dd class="mb-2">{{ $order->grand_total }} {{ $order->currency_code }}</dd>
            @if ($latestPayment !== null)
                <dt class="font-semibold">{{ __('storefront.checkout.payment_status') }}</dt>
                <dd>{{ __('storefront.checkout.payment_status_'.$latestPayment->status, ['default' => $latestPayment->status]) }}</dd>
            @endif
        </dl>

        @if ($latestPayment !== null && $latestPayment->status === 'pending')
            <p class="mt-4 text-sm">{{ __('storefront.checkout.payment_pending') }}</p>
        @elseif ($latestPayment !== null && $latestPayment->status === 'failed')
            <p class="mt-4 text-sm">{{ __('storefront.checkout.payment_failed') }}</p>
        @elseif ($order->status === 'processing')
            <p class="mt-4 text-sm">{{ __('storefront.checkout.payment_success') }}</p>
        @endif

        @if (in_array($order->status, ['pending_payment', 'payment_failed'], true) && $latestPayment !== null && in_array($latestPayment->status, ['pending', 'failed'], true))
            <form class="mt-6" method="post" action="{{ route('storefront.orders.payments.retry', ['publicUuid' => $order->public_uuid]) }}">
                @csrf
                <input type="hidden" name="payment_idempotency_key" value="{{ hash('sha256', 'retry-'.$order->public_uuid.'-'.uniqid('', true)) }}">
                <button class="rounded border px-4 py-2" type="submit">{{ __('storefront.checkout.retry_payment') }}</button>
            </form>
        @endif

        <a class="mt-6 inline-block underline" href="{{ route('storefront.home') }}">
            {{ __('storefront.nav.home') }}
        </a>
    </main>
@endsection