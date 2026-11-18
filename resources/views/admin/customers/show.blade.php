@extends('layouts.admin')
@section('title', $customer->email)
@section('content')
<h1 class="mb-2 text-2xl font-semibold">{{ $customer->first_name }} {{ $customer->last_name }}</h1>
<p class="mb-4 text-sm">{{ $customer->email }}</p>
@if ($customer->user)
    <p class="mb-4 text-sm">User active: {{ $customer->user->is_active ? 'yes' : 'no' }}</p>
@endif
<h2 class="font-medium">Addresses</h2>
<ul class="mb-4 text-sm">
    @forelse ($customer->addresses as $address)
        <li>{{ $address->line1 }}, {{ $address->city }}</li>
    @empty
        <li>—</li>
    @endforelse
</ul>
<h2 class="font-medium">Recent orders</h2>
<ul class="text-sm">
    @foreach ($customer->orders as $order)
        <li><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a> — {{ $order->status }}</li>
    @endforeach
</ul>
@endsection