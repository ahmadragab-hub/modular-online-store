@extends('layouts.storefront')

@section('title', __('storefront.checkout.success_title'))

@section('content')
    <main class="storefront-main mx-auto max-w-2xl px-4 py-10">
        <h1 class="mb-4 text-2xl font-semibold">{{ __('storefront.checkout.success_title') }}</h1>
        <p class="mb-2">{{ __('storefront.checkout.success_intro') }}</p>
        <dl class="rounded border p-4 text-sm">
            <dt class="font-semibold">{{ __('storefront.checkout.order_number') }}</dt>
            <dd class="mb-2">{{ $order->order_number }}</dd>
            <dt class="font-semibold">{{ __('storefront.checkout.total') }}</dt>
            <dd>{{ $order->grand_total }} {{ $order->currency_code }}</dd>
        </dl>
        <p class="mt-4 text-sm opacity-80">{{ __('storefront.checkout.payment_pending') }}</p>
        <a class="mt-6 inline-block underline" href="{{ route('storefront.home') }}">
            {{ __('storefront.nav.home') }}
        </a>
    </main>
@endsection
