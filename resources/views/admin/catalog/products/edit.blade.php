@extends('layouts.admin')
@section('title', $product->name)
@section('content')
<h1 class="mb-2 text-2xl font-semibold">{{ $product->name }}</h1>
<p class="mb-4 text-sm">Status: {{ $product->status }}</p>
<form method="post" action="{{ route('admin.products.update', $product) }}" class="mb-6 max-w-lg space-y-3">
    @csrf @method('put')
    <label>Name <input name="name" value="{{ old('name', $product->name) }}" required class="block w-full border px-2 py-1"></label>
    <label>Slug <input name="slug" value="{{ old('slug', $product->slug) }}" class="block w-full border px-2 py-1"></label>
    <label>Brand <input name="brand" value="{{ old('brand', $product->brand) }}" class="block w-full border px-2 py-1"></label>
    <label>Description <textarea name="description" class="block w-full border px-2 py-1">{{ old('description', $product->description) }}</textarea></label>
    <label>Categories
        <select name="category_ids[]" multiple class="block w-full border px-2 py-1">
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(collect(old('category_ids', $product->categories->pluck('id')))->contains($category->id))>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit">{{ __('admin.actions.save') }}</button>
</form>
<form method="post" action="{{ route('admin.products.translations.update', $product) }}" class="mb-6 max-w-lg space-y-3 border-t pt-4">
    @csrf
    @php
        $en = $product->translations->firstWhere('locale', 'en');
        $ar = $product->translations->firstWhere('locale', 'ar');
    @endphp
    <h2 class="font-medium">Translations</h2>
    <label>EN name <input name="en_name" value="{{ old('en_name', $en?->name) }}" required class="block w-full border px-2 py-1"></label>
    <label>EN description <textarea name="en_description" class="block w-full border px-2 py-1">{{ old('en_description', $en?->description) }}</textarea></label>
    <label>AR name <input name="ar_name" value="{{ old('ar_name', $ar?->name) }}" required class="block w-full border px-2 py-1"></label>
    <label>AR description <textarea name="ar_description" class="block w-full border px-2 py-1">{{ old('ar_description', $ar?->description) }}</textarea></label>
    <button type="submit">{{ __('admin.actions.save') }}</button>
</form>
<div class="mb-4 flex gap-2">
    <form method="post" action="{{ route('admin.products.publish', $product) }}">@csrf<button type="submit">{{ __('admin.actions.publish') }}</button></form>
    <form method="post" action="{{ route('admin.products.archive', $product) }}">@csrf<button type="submit">{{ __('admin.actions.archive') }}</button></form>
    <a href="{{ route('admin.products.variants.create', $product) }}">Add variant</a>
</div>
<h2 class="mb-2 font-medium">Variants</h2>
<ul class="mb-6 text-sm">
    @foreach ($product->variants as $variant)
        <li><a href="{{ route('admin.products.variants.edit', [$product, $variant]) }}">{{ $variant->sku }}</a> — {{ $variant->price }} USD ({{ $variant->status }})</li>
    @endforeach
</ul>
<h2 class="mb-2 font-medium">Images</h2>
<form method="post" action="{{ route('admin.products.images.store', $product) }}" enctype="multipart/form-data" class="mb-4 space-y-2">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <input name="alt_text" placeholder="Alt text">
    <button type="submit">Upload</button>
</form>
<ul class="text-sm">
    @foreach ($product->images as $image)
        <li class="flex items-center gap-2">
            {{ $image->path }}
            <form method="post" action="{{ route('admin.products.images.destroy', [$product, $image]) }}">@csrf @method('delete')<button type="submit">Delete</button></form>
        </li>
    @endforeach
</ul>
@endsection