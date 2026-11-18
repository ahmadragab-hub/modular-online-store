@extends('layouts.admin')
@section('title', __('admin.nav.products'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.nav.products') }}</h1>
<p class="mb-4"><a href="{{ route('admin.products.create') }}">{{ __('admin.actions.create') }}</a></p>
<table class="w-full text-sm">
    <thead><tr class="border-b text-left"><th>Name</th><th>Status</th><th>Variants</th><th></th></tr></thead>
    <tbody>
    @foreach ($products as $product)
        <tr class="border-b"><td class="py-2">{{ $product->name }}</td><td>{{ $product->status }}</td><td>{{ $product->variants_count }}</td>
        <td><a href="{{ route('admin.products.edit', $product) }}">Edit</a></td></tr>
    @endforeach
    </tbody>
</table>
{{ $products->links() }}
@endsection