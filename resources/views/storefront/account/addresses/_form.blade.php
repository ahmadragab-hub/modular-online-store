<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium" for="label">{{ __('storefront.account.addresses.fields.label') }}</label>
        <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="label" id="label" value="{{ old('label', $address->label ?? '') }}">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium" for="first_name">{{ __('storefront.auth.register.first_name') }}</label>
            <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="first_name" id="first_name" value="{{ old('first_name', $address->first_name ?? '') }}" required>
        </div>
        <div>
            <label class="block text-sm font-medium" for="last_name">{{ __('storefront.auth.register.last_name') }}</label>
            <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="last_name" id="last_name" value="{{ old('last_name', $address->last_name ?? '') }}" required>
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium" for="phone">{{ __('storefront.account.addresses.fields.phone') }}</label>
        <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="phone" id="phone" value="{{ old('phone', $address->phone ?? '') }}">
    </div>
    <div>
        <label class="block text-sm font-medium" for="line1">{{ __('storefront.account.addresses.fields.line1') }}</label>
        <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="line1" id="line1" value="{{ old('line1', $address->line1 ?? '') }}" required>
    </div>
    <div>
        <label class="block text-sm font-medium" for="line2">{{ __('storefront.account.addresses.fields.line2') }}</label>
        <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="line2" id="line2" value="{{ old('line2', $address->line2 ?? '') }}">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium" for="city">{{ __('storefront.account.addresses.fields.city') }}</label>
            <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="city" id="city" value="{{ old('city', $address->city ?? '') }}" required>
        </div>
        <div>
            <label class="block text-sm font-medium" for="region">{{ __('storefront.account.addresses.fields.region') }}</label>
            <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="region" id="region" value="{{ old('region', $address->region ?? '') }}">
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium" for="postal_code">{{ __('storefront.account.addresses.fields.postal_code') }}</label>
            <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2" type="text" name="postal_code" id="postal_code" value="{{ old('postal_code', $address->postal_code ?? '') }}" required>
        </div>
        <div>
            <label class="block text-sm font-medium" for="country_code">{{ __('storefront.account.addresses.fields.country_code') }}</label>
            <input class="mt-1 w-full rounded border border-[var(--sf-border)] bg-[var(--sf-surface)] px-3 py-2 uppercase" type="text" name="country_code" id="country_code" maxlength="2" value="{{ old('country_code', $address->country_code ?? '') }}" required>
        </div>
    </div>
</div>