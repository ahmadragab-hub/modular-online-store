@extends('layouts.admin')
@section('title', __('admin.nav.customers'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.nav.customers') }}</h1>
<form method="get" class="mb-4"><input name="search" value="{{ $filters['search'] ?? '' }}"> <button>Search</button></form>
<table class="w-full text-sm">
    <thead><tr class="border-b text-left"><th>Name</th><th>Email</th><th>Registered</th><th></th></tr></thead>
    <tbody>
    @foreach ($customers as $customer)
        <tr class="border-b">
            <td class="py-2">{{ $customer->first_name }} {{ $customer->last_name }}</td>
            <td>{{ $customer->email }}</td>
            <td>{{ $customer->user_id ? ($customer->user?->is_active ? 'Yes' : 'Inactive user') : 'Guest' }}</td>
            <td><a href="{{ route('admin.customers.show', $customer) }}">View</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
{{ $customers->withQueryString()->links() }}
@endsection