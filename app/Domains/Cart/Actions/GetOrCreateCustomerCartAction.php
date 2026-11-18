<?php

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\Exceptions\MultipleActiveCustomerCartsException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Store\Models\Currency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class GetOrCreateCustomerCartAction
{
    public function execute(Customer $customer, User $user, string $displayCurrencyCode): Cart
    {
        return DB::transaction(fn (): Cart => $this->lockAndResolve($customer, $user, $displayCurrencyCode));
    }

    public function lockAndResolveWithinExistingTransaction(
        Customer $customer,
        User $user,
        string $displayCurrencyCode,
    ): Cart {
        return $this->lockAndResolve($customer, $user, $displayCurrencyCode);
    }

    private function lockAndResolve(Customer $customer, User $user, string $displayCurrencyCode): Cart
    {
        $displayCurrencyCode = strtoupper(trim($displayCurrencyCode));

        Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();

        /** @var Collection<int, Cart> $activeCarts */
        $activeCarts = Cart::query()
            ->where('customer_id', $customer->getKey())
            ->where('status', Cart::STATUS_ACTIVE)
            ->lockForUpdate()
            ->get();

        if ($activeCarts->count() > 1) {
            throw new MultipleActiveCustomerCartsException(
                $activeCarts->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            );
        }

        $cart = $activeCarts->first();

        if ($cart !== null) {
            $this->syncOwnershipAndCurrency($cart, $customer, $user, $displayCurrencyCode);

            return $cart->fresh();
        }

        $baseCurrencyCode = Currency::query()->where('is_base', true)->value('code') ?? 'EGP';

        try {
            return Cart::query()->create([
                'customer_id' => $customer->getKey(),
                'user_id' => $user->getKey(),
                'session_token' => null,
                'display_currency_code' => $displayCurrencyCode,
                'base_currency_code' => $baseCurrencyCode,
                'status' => Cart::STATUS_ACTIVE,
                'expires_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            /** @var Cart|null $existing */
            $existing = Cart::query()
                ->where('customer_id', $customer->getKey())
                ->where('status', Cart::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                throw new MultipleActiveCustomerCartsException([]);
            }

            $this->syncOwnershipAndCurrency($existing, $customer, $user, $displayCurrencyCode);

            return $existing->fresh();
        }
    }

    private function syncOwnershipAndCurrency(
        Cart $cart,
        Customer $customer,
        User $user,
        string $displayCurrencyCode,
    ): void {
        $updates = [];

        if ($cart->customer_id !== $customer->getKey()) {
            $updates['customer_id'] = $customer->getKey();
        }

        if ($cart->user_id !== $user->getKey()) {
            $updates['user_id'] = $user->getKey();
        }

        if ($cart->display_currency_code !== $displayCurrencyCode) {
            $updates['display_currency_code'] = $displayCurrencyCode;
        }

        if ($updates !== []) {
            $cart->update($updates);
        }
    }
}
