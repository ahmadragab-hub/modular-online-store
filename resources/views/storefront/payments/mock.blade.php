@extends('layouts.storefront')

@section('title', __('storefront.payments.mock_title'))

@section('content')
    <main class="storefront-main mx-auto max-w-2xl px-4 py-10">
        <h1 class="mb-4 text-2xl font-semibold">{{ __('storefront.payments.mock_title') }}</h1>
        <p class="mb-4 text-sm opacity-90">{{ __('storefront.payments.mock_disclaimer') }}</p>

        <dl class="mb-6 rounded border p-4 text-sm">
            <dt class="font-semibold">{{ __('storefront.checkout.order_number') }}</dt>
            <dd class="mb-2">{{ $order->order_number }}</dd>
            <dt class="font-semibold">{{ __('storefront.checkout.total') }}</dt>
            <dd>{{ $payment->amount }} {{ $payment->currency_code }}</dd>
        </dl>

        <form class="mb-3" method="post" action="{{ URL::signedRoute('storefront.payments.mock.success', ['payment' => $payment->getKey()]) }}">
            @csrf
            <button class="rounded bg-emerald-700 px-4 py-2 text-white" type="submit">
                {{ __('storefront.payments.mock_success') }}
            </button>
        </form>

        <form method="post" action="{{ URL::signedRoute('storefront.payments.mock.failure', ['payment' => $payment->getKey()]) }}">
            @csrf
            <button class="rounded bg-rose-700 px-4 py-2 text-white" type="submit">
                {{ __('storefront.payments.mock_failure') }}
            </button>
        </form>
    </main>
@endsection