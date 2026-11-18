@extends('layouts.admin')
@section('title', __('admin.nav.colors'))
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ __('admin.nav.colors') }}</h1>
<form method="post" action="{{ route('admin.colors.store') }}" class="mb-4 flex gap-2">
    @csrf
    <input name="name" placeholder="Name" required>
    <input name="slug" placeholder="Slug">
    <button type="submit">{{ __('admin.actions.create') }}</button>
</form>
<ul class="text-sm">@foreach ($colors as $color)<li>{{ $color->name }} ({{ $color->slug }})</li>@endforeach</ul>
{{ $colors->links() }}
@endsection