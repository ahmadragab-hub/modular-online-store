@extends('layouts.admin')
@section('title', $variant->sku)
@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ $variant->sku }}</h1>
@include('admin.catalog.variants._form', ['product' => $product, 'variant' => $variant])
@endsection