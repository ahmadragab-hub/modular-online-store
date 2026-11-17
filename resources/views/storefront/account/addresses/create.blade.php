@extends('layouts.storefront')

@section('title', __('storefront.account.addresses.create_title'))

@section('content')
    <main class="storefront-main mx-auto max-w-xl px-4 py-10">
        <h1 class="text-2xl font-semibold">{{ __('storefront.account.addresses.create_title') }}</h1>
        @include('storefront.partials.account-nav')

        <form method="post" action="{{ route('storefront.account.addresses.store') }}" class="mt-6 space-y-6">
            @csrf
            @include('storefront.account.addresses._form', ['address' => null])
            <button type="submit" class="rounded-lg bg-[var(--sf-accent)] px-4 py-2.5 text-sm font-medium text-[var(--sf-accent-text)]">
                {{ __('storefront.account.addresses.save') }}
            </button>
        </form>
    </main>
@endsection