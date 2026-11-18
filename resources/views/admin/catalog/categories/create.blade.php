@extends('layouts.admin')
@section('title', 'Create category')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Create category</h1>
@include('admin.catalog.categories._form', ['category' => null])
@endsection