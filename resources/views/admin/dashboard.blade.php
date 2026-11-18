@extends('layouts.admin')

@section('title', __('admin.dashboard.title'))

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">{{ __('admin.dashboard.title') }}</h1>

    <section class="mb-8">
        <h2 class="mb-3 font-medium">{{ __('admin.dashboard.order_counts') }}</h2>
        <dl class="grid grid-cols-2 gap-3 md:grid-cols-3">
            @foreach ($metrics['order_counts'] as $status => $count)
                <div class="rounded border border-zinc-200 p-3 dark:border-zinc-700">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-400">{{ $status }}</dt>
                    <dd class="text-xl font-semibold">{{ $count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="mb-8">
        <h2 class="mb-3 font-medium">{{ __('admin.dashboard.recent_orders') }}</h2>
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-2">#</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($metrics['recent_orders'] as $order)
                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="py-2">{{ $order->order_number }}</td>
                        <td>{{ $order->status }}</td>
                        <td>{{ $order->grand_total }} {{ $order->currency_code }}</td>
                        <td><a href="{{ route('admin.orders.show', $order) }}">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section>
        <h2 class="mb-3 font-medium">{{ __('admin.dashboard.low_stock') }} (≤ {{ $metrics['low_stock_threshold'] }})</h2>
        <ul class="space-y-2 text-sm">
            @forelse ($metrics['low_stock'] as $level)
                <li>
                    SKU {{ $level->productVariant?->sku }} —
                    available {{ $level->quantity_on_hand - $level->quantity_reserved }}
                </li>
            @empty
                <li>—</li>
            @endforelse
        </ul>
    </section>
@endsection