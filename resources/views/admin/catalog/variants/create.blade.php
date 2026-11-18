@extends('layouts.admin')
@section('title', 'New variant')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Variant for {{ $product->name }}</h1>
@include('admin.catalog.variants._form', ['product' => $product, 'variant' => null])
@endsection