@extends('layouts.admin')
@section('title', __('admin.store.settings.title'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.store.settings.title') }}</h1>
<form method="post" action="{{ route('admin.settings.update') }}" class="max-w-xl space-y-3">
    @csrf
    @method('put')
    <label>Store name <input name="store_name" value="{{ old('store_name', $settings->store_name) }}" class="block w-full border px-2 py-1"></label>
    <label>Contact email <input name="contact_email" type="email" value="{{ old('contact_email', $settings->contact_email) }}" class="block w-full border px-2 py-1"></label>
    <label>Order prefix <input name="order_number_prefix" value="{{ old('order_number_prefix', $settings->order_number_prefix) }}" class="block w-full border px-2 py-1"></label>
    <label>Reservation TTL (minutes) <input name="reservation_ttl_minutes" type="number" value="{{ old('reservation_ttl_minutes', $settings->reservation_ttl_minutes) }}" class="block w-full border px-2 py-1"></label>
    <label><input type="checkbox" name="guest_checkout_enabled" value="1" @checked(old('guest_checkout_enabled', $settings->guest_checkout_enabled))> Guest checkout</label>
    <label><input type="checkbox" name="shipping_enabled" value="1" @checked(old('shipping_enabled', $settings->shipping_enabled))> Shipping enabled</label>
    <label>Flat shipping <input name="flat_shipping_amount" value="{{ old('flat_shipping_amount', $settings->flat_shipping_amount) }}" class="block w-full border px-2 py-1"></label>
    <button type="submit">{{ __('admin.actions.save') }}</button>
</form>
@endsection