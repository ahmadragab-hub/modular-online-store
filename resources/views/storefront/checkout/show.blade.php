@extends('layouts.storefront')

@section('title', __('storefront.checkout.title'))

@section('content')
    <main class="sf-container max-w-2xl py-10 sm:py-12">
        <h1 class="sf-display text-3xl text-[var(--sf-text)]">{{ __('storefront.checkout.title') }}</h1>
        <p class="mt-3 text-sm text-[var(--sf-muted)]">{{ __('storefront.checkout.intro') }}</p>

        @if ($errors->has('checkout'))
            <div class="sf-alert sf-alert--error mt-6" role="alert">
                {{ $errors->first('checkout') }}
            </div>
        @endif

        <div class="sf-card mt-8 space-y-6 p-6">
            <p class="text-sm text-[var(--sf-muted)]">
                {{ __('storefront.checkout.cart_items_count', ['count' => $cart->items->count()]) }}
            </p>
            <p class="text-sm text-[var(--sf-muted)]">{{ __('storefront.checkout.transaction_currency_note') }}</p>

            <form method="post" action="{{ route('storefront.checkout.store') }}" class="space-y-5">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium" for="transaction_currency_code">{{ __('storefront.checkout.currency') }}</label>
                        <select class="sf-select mt-1" name="transaction_currency_code" id="transaction_currency_code" required>
                            @foreach ($transactionCurrencies as $currency)
                                <option value="{{ $currency->code }}" @selected(old('transaction_currency_code', $defaultTransactionCurrency) === $currency->code)>
                                    {{ $currency->code }} — {{ $currency->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="content_locale">{{ __('storefront.checkout.locale') }}</label>
                        <input class="sf-input mt-1" type="text" name="content_locale"
                               id="content_locale" value="{{ old('content_locale', $storefrontLocale ?? 'en') }}" required maxlength="5">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium" for="guest_email">{{ __('storefront.checkout.guest_email') }}</label>
                    <input class="sf-input mt-1" type="email" name="guest_email" id="guest_email"
                           value="{{ old('guest_email') }}">
                </div>
                <div>
                    <label class="text-sm font-medium" for="idempotency_key">{{ __('storefront.checkout.idempotency_key') }}</label>
                    <input class="sf-input mt-1" type="text" name="idempotency_key" id="idempotency_key"
                           value="{{ old('idempotency_key', hash('sha256', uniqid('checkout-', true))) }}" required maxlength="64">
                </div>

                <fieldset class="rounded-lg border border-[var(--sf-border)] p-4">
                    <legend class="px-1 text-sm font-semibold">{{ __('storefront.checkout.shipping_address') }}</legend>
                    @foreach (['first_name', 'last_name', 'line1', 'city', 'country_code'] as $field)
                        <div class="mb-3">
                            <label class="text-xs text-[var(--sf-muted)]" for="shipping_{{ $field }}">{{ $field }}</label>
                            <input class="sf-input mt-1" type="text"
                                   name="shipping_address[{{ $field }}]" id="shipping_{{ $field }}"
                                   value="{{ old('shipping_address.'.$field) }}" required>
                        </div>
                    @endforeach
                </fieldset>

                <fieldset class="rounded-lg border border-[var(--sf-border)] p-4">
                    <legend class="px-1 text-sm font-semibold">{{ __('storefront.checkout.billing_address') }}</legend>
                    @foreach (['first_name', 'last_name', 'line1', 'city', 'country_code'] as $field)
                        <div class="mb-3">
                            <label class="text-xs text-[var(--sf-muted)]" for="billing_{{ $field }}">{{ $field }}</label>
                            <input class="sf-input mt-1" type="text"
                                   name="billing_address[{{ $field }}]" id="billing_{{ $field }}"
                                   value="{{ old('billing_address.'.$field) }}" required>
                        </div>
                    @endforeach
                </fieldset>

                <button type="submit" class="sf-btn sf-btn--primary w-full sm:w-auto">
                    {{ __('storefront.checkout.submit') }}
                </button>
            </form>
        </div>
    </main>
@endsection