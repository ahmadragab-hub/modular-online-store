@php
    $merge = session(\App\Shared\Support\CartMergeFlash::SESSION_KEY);
@endphp

@if (is_array($merge))
    @php $status = $merge['status'] ?? null; @endphp
    <div
        class="mx-auto max-w-5xl px-4 pt-4 sm:px-6"
        role="status"
        @if ($status === \App\Shared\Support\CartMergeFlash::STATUS_FAILED) aria-live="assertive" @endif
    >
        @if ($status === \App\Shared\Support\CartMergeFlash::STATUS_SUCCESS)
            <div class="rounded-lg border border-[var(--sf-border)] bg-[var(--sf-surface)] p-4 text-sm text-[var(--sf-text)]">
                <p class="font-medium">{{ __('storefront.cart.merge.success_title') }}</p>
                <ul class="mt-2 list-disc ps-5 text-[var(--sf-muted)]">
                    @foreach ($merge['lines'] ?? [] as $line)
                        <li>
                            @switch($line['status'] ?? '')
                                @case(\App\Domains\Cart\DTOs\AddCartItemMergeLineResult::STATUS_CAPPED)
                                    {{ __('storefront.cart.merge.line_capped', [
                                        'product' => $line['product_name'],
                                        'guest' => $line['guest_quantity_requested'],
                                        'added' => $line['quantity_added'],
                                        'final' => $line['final_quantity'],
                                    ]) }}
                                    @break
                                @case(\App\Domains\Cart\DTOs\AddCartItemMergeLineResult::STATUS_NO_STOCK)
                                    {{ __('storefront.cart.merge.line_no_stock', ['product' => $line['product_name']]) }}
                                    @break
                                @case(\App\Domains\Cart\DTOs\AddCartItemMergeLineResult::STATUS_UNAVAILABLE)
                                    {{ __('storefront.cart.merge.line_unavailable', ['product' => $line['product_name'] ?? __('storefront.cart.merge.unknown_product')]) }}
                                    @break
                                @default
                                    {{ __('storefront.cart.merge.line_merged', [
                                        'product' => $line['product_name'],
                                        'quantity' => $line['quantity_added'],
                                    ]) }}
                            @endswitch
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($status === \App\Shared\Support\CartMergeFlash::STATUS_NO_QUANTITY_APPLIED)
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                <p class="font-medium">{{ __('storefront.cart.merge.no_quantity_title') }}</p>
                <ul class="mt-2 list-disc ps-5">
                    @foreach ($merge['lines'] ?? [] as $line)
                        <li>
                            @switch($line['status'] ?? '')
                                @case(\App\Domains\Cart\DTOs\AddCartItemMergeLineResult::STATUS_NO_STOCK)
                                    {{ __('storefront.cart.merge.line_no_stock', ['product' => $line['product_name']]) }}
                                    @break
                                @case(\App\Domains\Cart\DTOs\AddCartItemMergeLineResult::STATUS_UNAVAILABLE)
                                    {{ __('storefront.cart.merge.line_unavailable', ['product' => $line['product_name'] ?? __('storefront.cart.merge.unknown_product')]) }}
                                    @break
                                @default
                                    {{ __('storefront.cart.merge.line_no_quantity', ['product' => $line['product_name']]) }}
                            @endswitch
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($status === \App\Shared\Support\CartMergeFlash::STATUS_FAILED)
            <p class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                {{ __('storefront.cart.merge.failed') }}
            </p>
        @elseif ($status === \App\Shared\Support\CartMergeFlash::STATUS_MULTIPLE_CARTS)
            <p class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                {{ __('storefront.cart.merge.multiple_carts') }}
            </p>
        @endif
    </div>
@endif