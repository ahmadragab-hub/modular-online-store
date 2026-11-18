@extends('layouts.admin')
@section('title', $category->name)
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ $category->name }}</h1>
@include('admin.catalog.categories._form', ['category' => $category])
@endsection