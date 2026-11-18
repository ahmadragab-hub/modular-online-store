@extends('layouts.admin')
@section('title', __('admin.nav.sizes'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.nav.sizes') }}</h1>
<form method="post" action="{{ route('admin.sizes.store') }}" class="mb-4 flex gap-2">
    @csrf
    <input name="label" placeholder="Label" required>
    <input name="code" placeholder="Code">
    <button type="submit">{{ __('admin.actions.create') }}</button>
</form>
<ul class="text-sm">@foreach ($sizes as $size)<li>{{ $size->code }} — {{ $size->label }}</li>@endforeach</ul>
{{ $sizes->links() }}
@endsection