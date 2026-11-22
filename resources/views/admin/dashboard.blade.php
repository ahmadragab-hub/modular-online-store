@extends('layouts.admin')

@section('title', __('admin.dashboard.title'))

@section('content')
    <div class="admin-topbar">
        <h1 class="text-2xl font-semibold text-[var(--adm-text)]">{{ __('admin.dashboard.title') }}</h1>
    </div>

    <section class="mb-8">
        <h2 class="mb-4 text-sm font-medium uppercase tracking-wide text-[var(--adm-muted)]">{{ __('admin.dashboard.order_counts') }}</h2>
        <dl class="admin-stat-grid">
            @foreach ($metrics['order_counts'] as $status => $count)
                <div class="admin-stat">
                    <dt>{{ str_replace('_', ' ', $status) }}</dt>
                    <dd>{{ $count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="admin-card mb-8">
        <h2 class="mb-4 text-lg font-medium text-[var(--adm-text)]">{{ __('admin.dashboard.recent_orders') }}</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($metrics['recent_orders'] as $order)
                        <tr>
                            <td class="font-medium">{{ $order->order_number }}</td>
                            <td><span class="sf-badge sf-badge--neutral">{{ $order->status }}</span></td>
                            <td>{{ $order->grand_total }} {{ $order->currency_code }}</td>
                            <td>
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-[var(--adm-sidebar-active)] hover:underline">
                                    View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-card">
        <h2 class="mb-4 text-lg font-medium text-[var(--adm-text)]">
            {{ __('admin.dashboard.low_stock') }} (≤ {{ $metrics['low_stock_threshold'] }})
        </h2>
        <ul class="space-y-2 text-sm text-[var(--adm-muted)]">
            @forelse ($metrics['low_stock'] as $level)
                <li class="flex justify-between gap-4 border-b border-[var(--adm-border)] py-2 last:border-0">
                    <span>SKU {{ $level->productVariant?->sku }}</span>
                    <span>Available: {{ $level->quantity_on_hand - $level->quantity_reserved }}</span>
                </li>
            @empty
                <li>—</li>
            @endforelse
        </ul>
    </section>
@endsection