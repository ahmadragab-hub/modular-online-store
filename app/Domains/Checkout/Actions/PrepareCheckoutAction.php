<?php

namespace App\Domains\Checkout\Actions;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\DTOs\PrepareCheckoutInput;
use App\Domains\Checkout\DTOs\PrepareCheckoutLineResult;
use App\Domains\Checkout\DTOs\PrepareCheckoutResult;
use App\Domains\Checkout\Exceptions\CheckoutPreparationException;
use App\Domains\Customers\Models\Customer;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\StoreSetting;
use App\Domains\Store\Queries\ResolveCurrentExchangeRateQuery;
use App\Domains\Store\Services\CheckoutAmountConverter;
use App\Shared\Support\MoneyMath;

final class PrepareCheckoutAction
{
    public function __construct(
        private readonly ResolveCurrentExchangeRateQuery $exchangeRateQuery,
        private readonly CheckoutAmountConverter $amountConverter,
    ) {}

    public function execute(PrepareCheckoutInput $input): PrepareCheckoutResult
    {
        $cart = Cart::query()->with(['items.productVariant.product', 'items.productVariant.size', 'items.productVariant.color'])->find($input->cartId);

        if ($cart === null) {
            throw new CheckoutPreparationException(CheckoutPreparationException::CART_NOT_FOUND, 'Cart not found.');
        }

        $this->assertCartActive($cart);
        $this->assertCartNotExpired($cart);
        $this->assertCartOwnership($cart, $input);
        $this->assertCartNotEmpty($cart);

        $settings = StoreSetting::singleton();
        if ($settings === null) {
            throw new CheckoutPreparationException(CheckoutPreparationException::STORE_SETTINGS_MISSING, 'Store settings are not configured.');
        }

        $transactionCurrency = Currency::query()->find($input->transactionCurrencyCode);
        if ($transactionCurrency === null) {
            throw new CheckoutPreparationException(CheckoutPreparationException::CURRENCY_UNSUPPORTED, 'Transaction currency is not supported.');
        }

        if (! $transactionCurrency->transaction_enabled) {
            throw new CheckoutPreparationException(
                CheckoutPreparationException::CURRENCY_NOT_TRANSACTION_ENABLED,
                'Transaction currency is not enabled for checkout.',
            );
        }

        $fx = $this->exchangeRateQuery->resolve($input->transactionCurrencyCode);
        if ($fx['rate'] === '') {
            throw new CheckoutPreparationException(
                CheckoutPreparationException::EXCHANGE_RATE_MISSING,
                'No exchange rate is configured for the selected transaction currency.',
            );
        }

        $baseCurrencyCode = $settings->base_currency_code;
        $minorUnits = $transactionCurrency->minor_units;

        $lines = [];
        $subtotalBaseUsd = '0.00';

        foreach ($cart->items as $item) {
            $variant = $item->productVariant;
            if ($variant === null) {
                throw new CheckoutPreparationException(
                    CheckoutPreparationException::VARIANT_UNAVAILABLE,
                    'A cart line references a missing product variant.',
                );
            }

            $this->assertVariantPurchasable($variant, $item->quantity);

            $unitPriceBaseUsd = (string) $variant->price;
            $unitPrice = $this->amountConverter->convertUsdAmount($unitPriceBaseUsd, $transactionCurrency, $fx['rate']);
            $lineSubtotal = MoneyMath::multiply($unitPrice, (string) $item->quantity, $minorUnits);
            $lineSubtotalUsd = MoneyMath::multiply($unitPriceBaseUsd, (string) $item->quantity, 2);
            $subtotalBaseUsd = MoneyMath::add($subtotalBaseUsd, $lineSubtotalUsd, 2);

            $lines[] = new PrepareCheckoutLineResult(
                cartItemId: $item->getKey(),
                productVariantId: $variant->getKey(),
                sku: $variant->sku,
                productName: $item->product_name,
                variantLabel: $item->variant_label,
                quantity: $item->quantity,
                unitPriceBaseUsd: $unitPriceBaseUsd,
                unitPrice: $unitPrice,
                lineSubtotal: $lineSubtotal,
            );
        }

        $subtotal = '0';
        foreach ($lines as $line) {
            $subtotal = MoneyMath::add($subtotal, $line->lineSubtotal, $minorUnits);
        }

        $shippingTotalUsd = $this->resolveShippingUsd($settings, $subtotalBaseUsd);
        $shippingTotal = $shippingTotalUsd === '0.00'
            ? MoneyMath::roundHalfUp('0', $minorUnits)
            : $this->amountConverter->convertUsdAmount($shippingTotalUsd, $transactionCurrency, $fx['rate']);

        $taxTotal = MoneyMath::roundHalfUp('0', $minorUnits);
        $discountTotal = MoneyMath::roundHalfUp('0', $minorUnits);

        if ($settings->tax_enabled) {
            // MVP: tax remains disabled in store settings; no tax engine.
            $taxTotal = MoneyMath::roundHalfUp('0', $minorUnits);
        }

        $grandTotal = MoneyMath::add($subtotal, $shippingTotal, $minorUnits);
        $grandTotal = MoneyMath::add($grandTotal, $taxTotal, $minorUnits);

        return new PrepareCheckoutResult(
            cartId: $cart->getKey(),
            transactionCurrencyCode: $input->transactionCurrencyCode,
            baseCurrencyCode: $baseCurrencyCode,
            exchangeRate: $fx['rate'],
            currencyExchangeRateId: $fx['currency_exchange_rate_id'],
            exchangeRateEffectiveFrom: $fx['effective_from']?->toIso8601String(),
            taxEnabled: (bool) $settings->tax_enabled,
            subtotal: $subtotal,
            discountTotal: $discountTotal,
            shippingTotal: $shippingTotal,
            taxTotal: $taxTotal,
            grandTotal: $grandTotal,
            subtotalBaseUsd: $subtotalBaseUsd,
            lines: $lines,
        );
    }

    private function assertCartActive(Cart $cart): void
    {
        if ($cart->status !== Cart::STATUS_ACTIVE) {
            throw new CheckoutPreparationException(CheckoutPreparationException::CART_NOT_ACTIVE, 'Cart is not active.');
        }
    }

    private function assertCartNotExpired(Cart $cart): void
    {
        if ($cart->expires_at !== null && $cart->expires_at->isPast()) {
            throw new CheckoutPreparationException(CheckoutPreparationException::CART_EXPIRED, 'Cart has expired.');
        }
    }

    private function assertCartNotEmpty(Cart $cart): void
    {
        if ($cart->items->isEmpty()) {
            throw new CheckoutPreparationException(CheckoutPreparationException::CART_EMPTY, 'Cart is empty.');
        }
    }

    private function assertCartOwnership(Cart $cart, PrepareCheckoutInput $input): void
    {
        if ($input->userId !== null) {
            if ($cart->user_id !== null && $cart->user_id !== $input->userId) {
                throw new CheckoutPreparationException(CheckoutPreparationException::CART_OWNERSHIP, 'Cart does not belong to this customer.');
            }

            if ($cart->customer_id !== null) {
                $customer = Customer::query()->where('user_id', $input->userId)->first();
                if ($customer === null || $customer->getKey() !== $cart->customer_id) {
                    throw new CheckoutPreparationException(CheckoutPreparationException::CART_OWNERSHIP, 'Cart does not belong to this customer.');
                }
            }

            return;
        }

        if ($input->sessionToken === null || $cart->session_token !== $input->sessionToken) {
            throw new CheckoutPreparationException(CheckoutPreparationException::CART_OWNERSHIP, 'Cart does not belong to this session.');
        }
    }

    private function assertVariantPurchasable(ProductVariant $variant, int $requestedQuantity): void
    {
        if ($variant->status !== ProductVariant::STATUS_ACTIVE) {
            throw new CheckoutPreparationException(CheckoutPreparationException::VARIANT_UNAVAILABLE, 'Product variant is not available.');
        }

        $product = $variant->product;
        if ($product === null || $product->status !== Product::STATUS_PUBLISHED) {
            throw new CheckoutPreparationException(CheckoutPreparationException::VARIANT_UNAVAILABLE, 'Product is not available for purchase.');
        }

        $hasEn = $product->translations()->where('locale', Product::LOCALE_EN)->exists();
        $hasAr = $product->translations()->where('locale', Product::LOCALE_AR)->exists();
        if (! $hasEn || ! $hasAr) {
            throw new CheckoutPreparationException(CheckoutPreparationException::VARIANT_UNAVAILABLE, 'Product is missing required translations.');
        }

        $level = InventoryLevel::query()->where('product_variant_id', $variant->getKey())->first();
        $available = $level === null
            ? 0
            : $level->quantity_on_hand - $level->quantity_reserved;

        if ($available < $requestedQuantity) {
            throw new CheckoutPreparationException(CheckoutPreparationException::INSUFFICIENT_STOCK, 'Insufficient stock for a cart line.');
        }
    }

    private function resolveShippingUsd(StoreSetting $settings, string $subtotalBaseUsd): string
    {
        if (! $settings->shipping_enabled) {
            return '0.00';
        }

        if ($settings->flat_shipping_amount === null) {
            throw new CheckoutPreparationException(
                CheckoutPreparationException::SHIPPING_NOT_CONFIGURED,
                'Flat shipping amount is not configured while shipping is enabled.',
            );
        }

        $flat = $settings->flat_shipping_amount;

        $flatUsd = (string) $flat;
        $threshold = $settings->free_shipping_threshold;
        if ($threshold !== null && MoneyMath::compare($subtotalBaseUsd, (string) $threshold, 2) >= 0) {
            return '0.00';
        }

        return $flatUsd;
    }
}
