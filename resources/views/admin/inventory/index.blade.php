@extends('layouts.admin')
@section('title', __('admin.nav.inventory'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.nav.inventory') }}</h1>
<form method="get" class="mb-4"><input name="sku" value="{{ $filters['sku'] ?? '' }}" placeholder="SKU"> <button>Filter</button></form>
<table class="w-full text-sm">
    <thead><tr class="border-b text-left"><th>SKU</th><th>On hand</th><th>Reserved</th><th>Available</th><th>Adjust</th></tr></thead>
    <tbody>
    @foreach ($levels as $level)
        @php $variant = $level->productVariant; @endphp
        <tr class="border-b align-top">
            <td class="py-2">{{ $variant?->sku }}</td>
            <td>{{ $level->quantity_on_hand }}</td>
            <td>{{ $level->quantity_reserved }}</td>
            <td>{{ $level->quantity_on_hand - $level->quantity_reserved }}</td>
            <td>
                <form method="post" action="{{ route('admin.inventory.adjust', $variant) }}" class="flex flex-col gap-1">
                    @csrf
                    <input name="quantity_delta" type="number" required class="w-24 border px-1">
                    <input name="notes" type="text" placeholder="Notes" class="border px-1">
                    <button type="submit" class="text-start">{{ __('admin.actions.save') }}</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
{{ $levels->withQueryString()->links() }}
<p class="mt-4"><a href="{{ route('admin.inventory.movements') }}">Movement history</a></p>
@endsection