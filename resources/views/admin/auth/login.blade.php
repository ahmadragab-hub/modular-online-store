@extends('layouts.admin')

@section('title', __('admin.auth.login.title'))

@section('content')
    <div class="mx-auto max-w-md rounded border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <h1 class="mb-4 text-xl font-semibold">{{ __('admin.auth.login.title') }}</h1>
        <form method="post" action="{{ route('admin.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email">{{ __('admin.auth.login.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border px-3 py-2">
            </div>
            <div>
                <label for="password">{{ __('admin.auth.login.password') }}</label>
                <input id="password" name="password" type="password" required class="mt-1 w-full rounded border px-3 py-2">
            </div>
            <button type="submit" class="rounded bg-zinc-900 px-4 py-2 text-white">{{ __('admin.auth.login.submit') }}</button>
        </form>
    </div>
@endsection