<?php

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\DTOs\GuestCartResult;
use App\Domains\Cart\Models\Cart;
use App\Domains\Store\Models\Currency;
use App\Shared\Support\GuestCartSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class GetOrCreateCartAction
{
    public function execute(string $sessionToken, string $displayCurrencyCode): GuestCartResult
    {
        $sessionToken = trim($sessionToken);
        $displayCurrencyCode = strtoupper(trim($displayCurrencyCode));

        if ($sessionToken === '') {
            $sessionToken = GuestCartSession::generateToken();

            return DB::transaction(
                fn (): GuestCartResult => $this->insertCart($sessionToken, $displayCurrencyCode, rotated: true),
            );
        }

        return DB::transaction(function () use ($sessionToken, $displayCurrencyCode): GuestCartResult {
            /** @var Cart|null $cart */
            $cart = Cart::query()
                ->where('session_token', $sessionToken)
                ->where('status', Cart::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($cart !== null && $this->isUsable($cart)) {
                $this->syncDisplayCurrency($cart, $displayCurrencyCode);

                return new GuestCartResult($cart->fresh(), $sessionToken, false);
            }

            if ($cart !== null && $this->isExpired($cart)) {
                $cart->update([
                    'status' => Cart::STATUS_ABANDONED,
                    'session_token' => null,
                ]);

                $newToken = GuestCartSession::generateToken();

                return $this->insertCart($newToken, $displayCurrencyCode, rotated: true);
            }

            try {
                return $this->insertCart($sessionToken, $displayCurrencyCode, rotated: false);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isSessionTokenUniqueViolation($exception)) {
                    throw $exception;
                }

                /** @var Cart|null $existing */
                $existing = Cart::query()
                    ->where('session_token', $sessionToken)
                    ->where('status', Cart::STATUS_ACTIVE)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && $this->isUsable($existing)) {
                    $this->syncDisplayCurrency($existing, $displayCurrencyCode);

                    return new GuestCartResult($existing->fresh(), $sessionToken, false);
                }

                throw $exception;
            }
        });
    }

    private function insertCart(string $sessionToken, string $displayCurrencyCode, bool $rotated): GuestCartResult
    {
        $baseCurrencyCode = Currency::query()->where('is_base', true)->value('code') ?? 'USD';

        $cart = Cart::query()->create([
            'session_token' => $sessionToken,
            'display_currency_code' => $displayCurrencyCode,
            'base_currency_code' => $baseCurrencyCode,
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        return new GuestCartResult($cart, $sessionToken, $rotated);
    }

    private function isUsable(Cart $cart): bool
    {
        return $cart->status === Cart::STATUS_ACTIVE && ! $this->isExpired($cart);
    }

    private function isExpired(Cart $cart): bool
    {
        return $cart->expires_at !== null && $cart->expires_at->isPast();
    }

    private function syncDisplayCurrency(Cart $cart, string $displayCurrencyCode): void
    {
        if ($cart->display_currency_code !== $displayCurrencyCode) {
            $cart->update(['display_currency_code' => $displayCurrencyCode]);
        }
    }

    private function isSessionTokenUniqueViolation(UniqueConstraintViolationException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'session_token')
            || str_contains($message, 'carts.session_token');
    }
}
