@extends('layouts.admin')
@section('title', 'Movements')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Inventory movements</h1>
<table class="w-full text-sm">
    <thead><tr class="border-b text-left"><th>When</th><th>Type</th><th>Δ</th><th>On hand after</th><th>Notes</th></tr></thead>
    <tbody>
    @foreach ($movements as $movement)
        <tr class="border-b">
            <td class="py-2">{{ $movement->created_at }}</td>
            <td>{{ $movement->type }}</td>
            <td>{{ $movement->quantity_delta }}</td>
            <td>{{ $movement->quantity_on_hand_after }}</td>
            <td>{{ $movement->notes }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
{{ $movements->links() }}
@endsection