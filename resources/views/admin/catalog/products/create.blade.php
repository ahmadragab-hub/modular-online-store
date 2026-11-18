@extends('layouts.admin')
@section('title', 'Create product')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Create product</h1>
<form method="post" action="{{ route('admin.products.store') }}" class="max-w-lg space-y-3">
    @csrf
    <label>Name <input name="name" value="{{ old('name') }}" required class="block w-full border px-2 py-1"></label>
    <label>Slug <input name="slug" value="{{ old('slug') }}" class="block w-full border px-2 py-1"></label>
    <button type="submit">{{ __('admin.actions.create') }}</button>
</form>
@endsection