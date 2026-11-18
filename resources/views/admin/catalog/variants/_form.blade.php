<form method="post" action="{{ $variant ? route('admin.products.variants.update', [$product, $variant]) : route('admin.products.variants.store', $product) }}" class="max-w-lg space-y-3">
    @csrf
    @if ($variant) @method('put') @endif
    <label>SKU <input name="sku" value="{{ old('sku', $variant?->sku) }}" required class="block w-full border px-2 py-1"></label>
    <label>Price (USD) <input name="price" type="number" step="0.01" value="{{ old('price', $variant?->price) }}" required class="block w-full border px-2 py-1"></label>
    <label>Size <select name="size_id" required class="block w-full border px-2 py-1">
        @foreach ($sizes as $size)
            <option value="{{ $size->id }}" @selected((int) old('size_id', $variant?->size_id) === (int) $size->id)>{{ $size->label }}</option>
        @endforeach
    </select></label>
    <label>Color <select name="color_id" required class="block w-full border px-2 py-1">
        @foreach ($colors as $color)
            <option value="{{ $color->id }}" @selected((int) old('color_id', $variant?->color_id) === (int) $color->id)>{{ $color->name }}</option>
        @endforeach
    </select></label>
    <label>Status <select name="status" class="block w-full border px-2 py-1">
        <option value="active" @selected(old('status', $variant?->status ?? 'active') === 'active')>active</option>
        <option value="inactive" @selected(old('status', $variant?->status) === 'inactive')>inactive</option>
    </select></label>
    <button type="submit">{{ __('admin.actions.save') }}</button>
</form>