@extends('layouts.storefront')

@section('title', __('storefront.checkout.title'))

@section('content')
    <main class="storefront-main mx-auto max-w-2xl px-4 py-10">
        <h1 class="mb-4 text-2xl font-semibold">{{ __('storefront.checkout.title') }}</h1>
        <p class="mb-6 text-sm opacity-80">{{ __('storefront.checkout.intro') }}</p>

        @if ($errors->has('checkout'))
            <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert">
                {{ $errors->first('checkout') }}
            </div>
        @endif

        <form method="post" action="{{ route('storefront.checkout.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium" for="cart_id">{{ __('storefront.checkout.cart_id') }}</label>
                <input class="mt-1 w-full rounded border px-3 py-2" type="number" name="cart_id" id="cart_id"
                       value="{{ old('cart_id') }}" required min="1">
            </div>
            <div>
                <label class="block text-sm font-medium" for="session_token">{{ __('storefront.checkout.session_token') }}</label>
                <input class="mt-1 w-full rounded border px-3 py-2" type="text" name="session_token" id="session_token"
                       value="{{ old('session_token') }}">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium" for="transaction_currency_code">{{ __('storefront.checkout.currency') }}</label>
                    <input class="mt-1 w-full rounded border px-3 py-2" type="text" name="transaction_currency_code"
                           id="transaction_currency_code" value="{{ old('transaction_currency_code', 'USD') }}" required maxlength="3">
                </div>
                <div>
                    <label class="block text-sm font-medium" for="content_locale">{{ __('storefront.checkout.locale') }}</label>
                    <input class="mt-1 w-full rounded border px-3 py-2" type="text" name="content_locale"
                           id="content_locale" value="{{ old('content_locale', $storefrontLocale ?? 'en') }}" required maxlength="5">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium" for="guest_email">{{ __('storefront.checkout.guest_email') }}</label>
                <input class="mt-1 w-full rounded border px-3 py-2" type="email" name="guest_email" id="guest_email"
                       value="{{ old('guest_email') }}">
            </div>
            <div>
                <label class="block text-sm font-medium" for="idempotency_key">{{ __('storefront.checkout.idempotency_key') }}</label>
                <input class="mt-1 w-full rounded border px-3 py-2" type="text" name="idempotency_key" id="idempotency_key"
                       value="{{ old('idempotency_key') }}" required maxlength="64">
            </div>

            <fieldset class="rounded border p-4">
                <legend class="px-1 text-sm font-semibold">{{ __('storefront.checkout.shipping_address') }}</legend>
                @foreach (['first_name', 'last_name', 'line1', 'city', 'country_code'] as $field)
                    <div class="mb-2">
                        <label class="block text-xs" for="shipping_{{ $field }}">{{ $field }}</label>
                        <input class="mt-1 w-full rounded border px-3 py-2" type="text"
                               name="shipping_address[{{ $field }}]" id="shipping_{{ $field }}"
                               value="{{ old('shipping_address.'.$field) }}" required>
                    </div>
                @endforeach
            </fieldset>

            <fieldset class="rounded border p-4">
                <legend class="px-1 text-sm font-semibold">{{ __('storefront.checkout.billing_address') }}</legend>
                @foreach (['first_name', 'last_name', 'line1', 'city', 'country_code'] as $field)
                    <div class="mb-2">
                        <label class="block text-xs" for="billing_{{ $field }}">{{ $field }}</label>
                        <input class="mt-1 w-full rounded border px-3 py-2" type="text"
                               name="billing_address[{{ $field }}]" id="billing_{{ $field }}"
                               value="{{ old('billing_address.'.$field) }}" required>
                    </div>
                @endforeach
            </fieldset>

            <button type="submit" class="rounded bg-black px-4 py-2 text-white dark:bg-white dark:text-black">
                {{ __('storefront.checkout.submit') }}
            </button>
        </form>
    </main>
@endsection
