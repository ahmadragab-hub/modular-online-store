<form method="post" action="{{ $category ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="max-w-lg space-y-3">
    @csrf
    @if ($category) @method('put') @endif
    <label>Name <input name="name" value="{{ old('name', $category?->name) }}" required class="block w-full border px-2 py-1"></label>
    <label>Slug <input name="slug" value="{{ old('slug', $category?->slug) }}" class="block w-full border px-2 py-1"></label>
    <label>Parent
        <select name="parent_id" class="block w-full border px-2 py-1">
            <option value="">—</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" @selected((int) old('parent_id', $category?->parent_id) === (int) $parent->id)>{{ $parent->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Sort <input type="number" name="sort_order" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" class="block w-full border px-2 py-1"></label>
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true))> Active</label>
    <button type="submit">{{ __('admin.actions.save') }}</button>
</form>