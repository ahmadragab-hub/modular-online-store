@extends('layouts.admin')
@section('title', $order->order_number)
@section('content')
<h1 class="mb-2 text-2xl font-semibold">{{ $order->order_number }}</h1>
<p class="mb-4 text-sm">Status: <strong>{{ $order->status }}</strong></p>
<section class="mb-6">
    <h2 class="font-medium">Line items</h2>
    <ul class="text-sm">
        @foreach ($order->items as $item)
            <li>{{ $item->sku }} × {{ $item->quantity }} — {{ $item->line_subtotal }}</li>
        @endforeach
    </ul>
</section>
<section class="mb-6">
    <h2 class="font-medium">Payments (read-only)</h2>
    <ul class="text-sm space-y-2">
        @foreach ($order->payments->sortBy('attempt_number') as $payment)
            <li>
                #{{ $payment->attempt_number }} — {{ $payment->status }}
                — {{ $payment->amount }} {{ $payment->currency_code }}
                @if ($payment->provider)
                    — {{ $payment->provider }}
                @endif
                @if ($payment->provider_payment_id)
                    — {{ $payment->provider_payment_id }}
                @endif
                @if ($payment->failed_at)
                    — {{ $payment->failure_code }}
                @endif
            </li>
        @endforeach
    </ul>
</section>
@if (in_array($order->status, ['pending_payment','payment_failed'], true))
<form method="post" action="{{ route('admin.orders.cancel', $order) }}" class="mb-4">
    @csrf
    <button type="submit" class="rounded border border-red-400 px-3 py-1 text-red-700">Cancel order</button>
</form>
@endif
@if ($order->status === 'processing')
<form method="post" action="{{ route('admin.orders.transition', $order) }}" class="mb-2">
    @csrf
    <input type="hidden" name="to_status" value="shipped">
    <button type="submit">Mark shipped</button>
</form>
@endif
@if ($order->status === 'shipped')
<form method="post" action="{{ route('admin.orders.transition', $order) }}">
    @csrf
    <input type="hidden" name="to_status" value="delivered">
    <button type="submit">Mark delivered</button>
</form>
@endif
@endsection