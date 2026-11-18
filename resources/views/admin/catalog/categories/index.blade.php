@extends('layouts.admin')
@section('title', __('admin.nav.categories'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.nav.categories') }}</h1>
<p class="mb-4"><a href="{{ route('admin.categories.create') }}">{{ __('admin.actions.create') }}</a></p>
<table class="w-full text-sm">
    <thead><tr class="border-b text-left"><th>Name</th><th>Slug</th><th>Active</th><th></th></tr></thead>
    <tbody>
    @foreach ($categories as $category)
        <tr class="border-b"><td class="py-2">{{ $category->name }}</td><td>{{ $category->slug }}</td><td>{{ $category->is_active ? 'yes' : 'no' }}</td>
        <td><a href="{{ route('admin.categories.edit', $category) }}">Edit</a></td></tr>
    @endforeach
    </tbody>
</table>
{{ $categories->links() }}
@endsection