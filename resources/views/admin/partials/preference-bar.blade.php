<form method="post" action="{{ route('admin.locale') }}" class="inline">
    @csrf
    <label class="sr-only" for="admin-locale">{{ __('admin.preferences.language') }}</label>
    <select id="admin-locale" name="locale" onchange="this.form.submit()">
        @foreach ($adminLocales as $code => $meta)
            <option value="{{ $code }}" @selected($adminLocale === $code)>{{ $meta['label'] ?? $code }}</option>
        @endforeach
    </select>
</form>
<form method="post" action="{{ route('admin.theme') }}" class="inline">
    @csrf
    <label class="sr-only" for="admin-theme">{{ __('admin.preferences.theme') }}</label>
    <select id="admin-theme" name="theme" onchange="this.form.submit()">
        @foreach ($adminThemes as $theme)
            <option value="{{ $theme }}" @selected($adminTheme === $theme)>
                {{ __('admin.preferences.theme_'.$theme) }}
            </option>
        @endforeach
    </select>
</form>