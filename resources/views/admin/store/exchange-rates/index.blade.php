@extends('layouts.admin')
@section('title', __('admin.store.exchange_rates.title'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.store.exchange_rates.title') }}</h1>
<form method="post" action="{{ route('admin.exchange-rates.store') }}" class="mb-6 flex flex-wrap gap-2">
    @csrf
    <select name="quote_currency_code" required>
        <option value="EGP">EGP</option>
        <option value="KWD">KWD</option>
    </select>
    <input name="rate" type="number" step="0.00000001" min="0" placeholder="Rate" required>
    <input name="notes" placeholder="Notes">
    <button type="submit">{{ __('admin.actions.create') }}</button>
</form>
<table class="w-full text-sm">
    <thead><tr class="border-b text-left"><th>Currency</th><th>Rate</th><th>Effective</th><th>By</th></tr></thead>
    <tbody>
    @foreach ($rates as $rate)
        <tr class="border-b"><td class="py-2">{{ $rate->quote_currency_code }}</td><td>{{ $rate->rate }}</td><td>{{ $rate->effective_from }}</td><td>{{ $rate->createdByUser?->email }}</td></tr>
    @endforeach
    </tbody>
</table>
{{ $rates->links() }}
@endsection