<?php

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\DTOs\CartMergeResult;
use App\Domains\Cart\Exceptions\CartMergeException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Store\Models\Currency;
use Illuminate\Support\Facades\DB;

final class MergeGuestCartIntoCustomerCartAction
{
    public function __construct(
        private readonly GetOrCreateCustomerCartAction $getOrCreateCustomerCartAction,
        private readonly AddCartItemAction $addCartItemAction,
    ) {}

    public function execute(
        string $guestSessionToken,
        Customer $customer,
        User $user,
        string $displayCurrencyCode,
        string $contentLocale,
    ): CartMergeResult {
        $guestSessionToken = trim($guestSessionToken);

        if ($guestSessionToken === '') {
            $customerCart = $this->getOrCreateCustomerCartAction->execute($customer, $user, $displayCurrencyCode);

            return new CartMergeResult(false, []);
        }

        return DB::transaction(function () use (
            $guestSessionToken,
            $customer,
            $user,
            $displayCurrencyCode,
            $contentLocale,
        ): CartMergeResult {
            /** @var Cart|null $guestCart */
            $guestCart = Cart::query()
                ->where('session_token', $guestSessionToken)
                ->where('status', Cart::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($guestCart === null || $guestCart->items()->count() === 0) {
                $this->getOrCreateCustomerCartAction->lockAndResolveWithinExistingTransaction(
                    $customer,
                    $user,
                    $displayCurrencyCode,
                );

                return new CartMergeResult(false, []);
            }

            if ($guestCart->customer_id !== null && $guestCart->customer_id !== $customer->getKey()) {
                throw new CartMergeException(
                    CartMergeException::FAILED,
                    'Guest cart is already linked to another customer.',
                );
            }

            $customerCart = $this->getOrCreateCustomerCartAction->lockAndResolveWithinExistingTransaction(
                $customer,
                $user,
                $guestCart->display_currency_code ?: $displayCurrencyCode,
            );

            $guestCart->load('items');
            $lineResults = [];

            foreach ($guestCart->items as $guestItem) {
                $lineResults[] = $this->addCartItemAction->executeMergeLine(
                    cart: $customerCart,
                    productVariantId: (int) $guestItem->product_variant_id,
                    quantityDelta: (int) $guestItem->quantity,
                    contentLocale: $contentLocale,
                );
            }

            $appliedAnyQuantity = false;
            foreach ($lineResults as $lineResult) {
                if ($lineResult->appliedAnyQuantity()) {
                    $appliedAnyQuantity = true;
                    break;
                }
            }

            if ($appliedAnyQuantity) {
                $guestCart->update([
                    'status' => Cart::STATUS_ABANDONED,
                    'session_token' => null,
                ]);

                if ($customerCart->base_currency_code === null || $customerCart->base_currency_code === '') {
                    $base = Currency::query()->where('is_base', true)->value('code') ?? 'USD';
                    $customerCart->update(['base_currency_code' => $base]);
                }
            }

            return new CartMergeResult($appliedAnyQuantity, $lineResults);
        });
    }
}
