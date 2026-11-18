@extends('layouts.admin')
@section('title', __('admin.nav.orders'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.nav.orders') }}</h1>
<form method="get" class="mb-4 flex flex-wrap gap-2 text-sm">
    <input name="order_number" value="{{ $filters['order_number'] ?? '' }}" placeholder="Order #">
    <input name="customer_email" value="{{ $filters['customer_email'] ?? '' }}" placeholder="Email">
    <select name="status">
        <option value="">All statuses</option>
        @foreach (['pending_payment','payment_failed','processing','shipped','delivered','cancelled'] as $s)
            <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
        @endforeach
    </select>
    <button type="submit">Filter</button>
</form>
<table class="w-full text-sm">
    <thead><tr class="border-b text-left"><th class="py-2">Order</th><th>Customer</th><th>Status</th><th>Total</th><th></th></tr></thead>
    <tbody>
    @foreach ($orders as $order)
        <tr class="border-b"><td class="py-2">{{ $order->order_number }}</td>
        <td>{{ $order->customer?->email ?? $order->guest_email }}</td>
        <td>{{ $order->status }}</td><td>{{ $order->grand_total }} {{ $order->currency_code }}</td>
        <td><a href="{{ route('admin.orders.show', $order) }}">View</a></td></tr>
    @endforeach
    </tbody>
</table>
{{ $orders->withQueryString()->links() }}
@endsection