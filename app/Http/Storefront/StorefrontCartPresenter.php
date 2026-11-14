<?php

namespace App\Http\Storefront;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Support\ProductImageUrlResolver;
use App\Domains\Store\Models\Currency;
use App\Domains\Store\Queries\ResolveCurrentExchangeRateQuery;
use App\Domains\Store\Services\CheckoutAmountConverter;
use App\Shared\Support\MoneyMath;

final class StorefrontCartPresenter
{
    public function __construct(
        private readonly ProductImageUrlResolver $productImageUrlResolver,
        private readonly ResolveCurrentExchangeRateQuery $exchangeRateQuery,
        private readonly CheckoutAmountConverter $amountConverter,
    ) {}

    /**
     * @return array{
     *     lines: list<array{
     *         id: int,
     *         product_name: string,
     *         variant_label: string,
     *         sku: string,
     *         quantity: int,
     *         image_url: ?string,
     *         image_alt: string,
     *         unit_price_base: string,
     *         unit_price_display: string,
     *         line_subtotal_display: string,
     *     }>,
     *     display_currency_code: string,
     *     display_currency_symbol: string,
     *     subtotal_display: string,
     *     subtotal_base_usd: string,
     *     amounts_are_indicative: bool,
     *     fx_available: bool,
     * }
     */
    public function present(Cart $cart): array
    {
        $displayCode = $cart->display_currency_code;
        $currency = Currency::query()->find($displayCode);
        $minorUnits = $currency?->minor_units ?? 2;
        $symbol = $currency?->symbol ?? '$';

        $fx = $this->exchangeRateQuery->resolve($displayCode);
        $fxAvailable = $fx['rate'] !== '' || $displayCode === 'USD';
        $amountsAreIndicative = true;

        $lines = [];
        $subtotalUsd = '0.00';
        $subtotalDisplay = '0';

        foreach ($cart->items as $item) {
            $line = $this->presentLine($item, $displayCode, $currency, $fx['rate'], $minorUnits, $fxAvailable);
            $lines[] = $line;

            $lineUsd = MoneyMath::multiply((string) $item->unit_price_base, (string) $item->quantity, 2);
            $subtotalUsd = MoneyMath::add($subtotalUsd, $lineUsd, 2);

            if ($fxAvailable && $currency !== null) {
                $subtotalDisplay = MoneyMath::add($subtotalDisplay, $line['line_subtotal_amount'], $minorUnits);
            }
        }

        if ($fxAvailable && $currency !== null && $lines !== []) {
            $subtotalDisplay = MoneyMath::roundHalfUp($subtotalDisplay, $minorUnits);
        } elseif (! $fxAvailable) {
            $subtotalDisplay = $subtotalUsd;
            $symbol = '$';
        }

        return [
            'lines' => $lines,
            'display_currency_code' => $fxAvailable ? $displayCode : 'USD',
            'display_currency_symbol' => $symbol,
            'subtotal_display' => $this->formatMoney($subtotalDisplay, $minorUnits),
            'subtotal_base_usd' => $this->formatMoney($subtotalUsd, 2),
            'amounts_are_indicative' => $amountsAreIndicative,
            'fx_available' => $fxAvailable,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     product_name: string,
     *     variant_label: string,
     *     sku: string,
     *     quantity: int,
     *     image_url: ?string,
     *     image_alt: string,
     *     unit_price_base: string,
     *     unit_price_display: string,
     *     line_subtotal_display: string,
     *     line_subtotal_amount: string,
     * }
     */
    private function presentLine(
        CartItem $item,
        string $displayCode,
        ?Currency $currency,
        string $exchangeRate,
        int $minorUnits,
        bool $fxAvailable,
    ): array {
        $product = $item->productVariant?->product;
        $imageUrl = $product !== null ? $this->productImageUrlResolver->resolvePrimaryUrl($product) : null;
        $imageAlt = $item->product_name;

        $unitUsd = (string) $item->unit_price_base;

        if ($fxAvailable && $currency !== null) {
            $unitDisplay = $displayCode === 'USD'
                ? $unitUsd
                : $this->amountConverter->convertUsdAmount($unitUsd, $currency, $exchangeRate);
            $lineDisplay = MoneyMath::multiply($unitDisplay, (string) $item->quantity, $minorUnits);
        } else {
            $unitDisplay = $unitUsd;
            $lineDisplay = MoneyMath::multiply($unitUsd, (string) $item->quantity, 2);
        }

        return [
            'id' => $item->getKey(),
            'product_name' => $item->product_name,
            'variant_label' => $item->variant_label,
            'sku' => $item->sku,
            'quantity' => $item->quantity,
            'image_url' => $imageUrl,
            'image_alt' => $imageAlt,
            'unit_price_base' => $this->formatMoney($unitUsd, 2),
            'unit_price_display' => $this->formatMoney($unitDisplay, $fxAvailable && $currency !== null ? $minorUnits : 2),
            'line_subtotal_display' => $this->formatMoney($lineDisplay, $fxAvailable && $currency !== null ? $minorUnits : 2),
            'line_subtotal_amount' => $lineDisplay,
        ];
    }

    private function formatMoney(string $amount, int $minorUnits): string
    {
        return StorefrontMoneyFormatter::format($amount, $minorUnits);
    }
}
