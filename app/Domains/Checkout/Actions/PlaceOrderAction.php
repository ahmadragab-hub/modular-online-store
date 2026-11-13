<?php

namespace App\Domains\Checkout\Actions;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\DTOs\PlaceOrderInput;
use App\Domains\Checkout\DTOs\PlaceOrderResult;
use App\Domains\Checkout\DTOs\PrepareCheckoutInput;
use App\Domains\Checkout\DTOs\PrepareCheckoutLineResult;
use App\Domains\Checkout\Exceptions\PlaceOrderException;
use App\Domains\Checkout\Exceptions\PlaceOrderIdempotencyConflictException;
use App\Domains\Checkout\Support\PlaceOrderPayloadFingerprint;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Actions\ReserveStockForCartAction;
use App\Domains\Inventory\DTOs\ReserveStockForCartInput;
use App\Domains\Inventory\DTOs\ReserveStockForCartLineInput;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Payments\Models\Payment;
use App\Domains\Store\Models\StoreLocale;
use App\Domains\Store\Models\StoreSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlaceOrderAction
{
    public function __construct(
        private readonly PrepareCheckoutAction $prepareCheckoutAction,
        private readonly ReserveStockForCartAction $reserveStockForCartAction,
        private readonly PlaceOrderPayloadFingerprint $payloadFingerprint,
    ) {}

    public function execute(PlaceOrderInput $input): PlaceOrderResult
    {
        $this->assertPreconditions($input);

        return DB::transaction(function () use ($input): PlaceOrderResult {
            $existingOrder = Order::query()
                ->where('idempotency_key', $input->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingOrder !== null) {
                return $this->buildIdempotentReplay($existingOrder, $input);
            }

            try {
                return $this->createNewOrder($input);
            } catch (QueryException $exception) {
                if (! $this->isIdempotencyKeyDuplicate($exception)) {
                    throw $exception;
                }

                $existingOrder = Order::query()
                    ->where('idempotency_key', $input->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder === null) {
                    throw $exception;
                }

                return $this->buildIdempotentReplay($existingOrder, $input);
            }
        });
    }

    private function assertPreconditions(PlaceOrderInput $input): void
    {
        if ($input->idempotencyKey === '') {
            throw new PlaceOrderException(
                PlaceOrderException::IDEMPOTENCY_KEY_REQUIRED,
                'Idempotency key is required.',
            );
        }

        if ($input->userId === null) {
            $settings = StoreSetting::singleton();
            if ($settings !== null && ! $settings->guest_checkout_enabled) {
                throw new PlaceOrderException(
                    PlaceOrderException::GUEST_CHECKOUT_DISABLED,
                    'Guest checkout is not enabled.',
                );
            }

            if ($input->guestEmail === null || trim($input->guestEmail) === '') {
                throw new PlaceOrderException(
                    PlaceOrderException::GUEST_EMAIL_REQUIRED,
                    'Guest email is required for checkout.',
                );
            }
        }

        $localeActive = StoreLocale::query()
            ->where('code', $input->contentLocale)
            ->where('is_active', true)
            ->exists();

        if (! $localeActive) {
            throw new PlaceOrderException(
                PlaceOrderException::INVALID_CONTENT_LOCALE,
                'Content locale is not valid or active.',
            );
        }
    }

    private function createNewOrder(PlaceOrderInput $input): PlaceOrderResult
    {
        $prepared = $this->prepareCheckoutAction->execute(new PrepareCheckoutInput(
            cartId: $input->cartId,
            transactionCurrencyCode: $input->transactionCurrencyCode,
            userId: $input->userId,
            sessionToken: $input->sessionToken,
        ));

        $settings = StoreSetting::singleton();
        if ($settings === null) {
            throw new PlaceOrderException(
                PlaceOrderException::INVALID_CONTENT_LOCALE,
                'Store settings are not configured.',
            );
        }

        $expiresAt = now()->addMinutes(max(1, (int) $settings->reservation_ttl_minutes));

        $reserveLines = array_map(
            static fn (PrepareCheckoutLineResult $line): ReserveStockForCartLineInput => new ReserveStockForCartLineInput(
                productVariantId: $line->productVariantId,
                quantity: $line->quantity,
            ),
            $prepared->lines,
        );

        $reserveResult = $this->reserveStockForCartAction->execute(new ReserveStockForCartInput(
            cartId: $input->cartId,
            lines: $reserveLines,
            expiresAt: $expiresAt,
        ));

        $customer = $this->resolveCustomer($input);

        $orderNumber = $this->generateOrderNumber($settings->order_number_prefix);
        $publicUuid = (string) Str::uuid();

        $effectiveFrom = $prepared->exchangeRateEffectiveFrom !== null
            ? Carbon::parse($prepared->exchangeRateEffectiveFrom)
            : null;

        $order = Order::query()->create([
            'public_uuid' => $publicUuid,
            'order_number' => $orderNumber,
            'customer_id' => $customer->getKey(),
            'user_id' => $input->userId,
            'guest_email' => $input->userId === null ? strtolower(trim((string) $input->guestEmail)) : null,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'base_currency_code' => $prepared->baseCurrencyCode,
            'currency_code' => $prepared->transactionCurrencyCode,
            'content_locale' => $input->contentLocale,
            'exchange_rate' => $prepared->exchangeRate,
            'currency_exchange_rate_id' => $prepared->currencyExchangeRateId,
            'exchange_rate_effective_from' => $effectiveFrom,
            'subtotal' => $prepared->subtotal,
            'discount_total' => $prepared->discountTotal,
            'tax_total' => $prepared->taxTotal,
            'shipping_total' => $prepared->shippingTotal,
            'grand_total' => $prepared->grandTotal,
            'shipping_address' => $input->shippingAddress,
            'billing_address' => $input->billingAddress,
            'customer_note' => $input->customerNote,
            'idempotency_key' => $input->idempotencyKey,
            'placed_at' => now(),
        ]);

        foreach ($prepared->lines as $line) {
            $variant = ProductVariant::query()->whereKey($line->productVariantId)->first();

            OrderItem::query()->create([
                'order_id' => $order->getKey(),
                'product_variant_id' => $line->productVariantId,
                'product_id' => $variant?->product_id,
                'product_name' => $line->productName,
                'sku' => $line->sku,
                'variant_label' => $line->variantLabel,
                'quantity' => $line->quantity,
                'unit_price_base_usd' => $line->unitPriceBaseUsd,
                'unit_price' => $line->unitPrice,
                'line_subtotal' => $line->lineSubtotal,
                'tax_amount' => '0.0000',
                'created_at' => now(),
            ]);
        }

        OrderStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => null,
            'to_status' => Order::STATUS_PENDING_PAYMENT,
            'comment' => 'Order placed.',
            'actor_user_id' => $input->userId,
            'created_at' => now(),
        ]);

        foreach ($reserveResult->lines as $reservedLine) {
            InventoryReservation::query()
                ->whereKey($reservedLine->inventoryReservationId)
                ->update(['order_id' => $order->getKey()]);
        }

        $paymentIdempotencyKey = $this->firstPaymentIdempotencyKey($input->idempotencyKey);

        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => $prepared->grandTotal,
            'currency_code' => $prepared->transactionCurrencyCode,
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => $paymentIdempotencyKey,
        ]);

        return new PlaceOrderResult(
            orderId: $order->getKey(),
            publicUuid: $order->public_uuid,
            orderNumber: $order->order_number,
            paymentId: $payment->getKey(),
            grandTotal: (string) $order->grand_total,
            currencyCode: $order->currency_code,
            idempotentReplay: false,
        );
    }

    private function buildIdempotentReplay(Order $order, PlaceOrderInput $input): PlaceOrderResult
    {
        if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED], true)) {
            throw new PlaceOrderException(
                PlaceOrderException::ORDER_NOT_REPLAYABLE,
                'An order with this idempotency key can no longer be replayed.',
            );
        }

        $cartId = $this->resolveCartIdForOrder($order);
        $orderLines = $order->items()
            ->orderBy('product_variant_id')
            ->get()
            ->map(static fn (OrderItem $item): array => [
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => (int) $item->quantity,
            ])
            ->all();

        $cartLines = CartItem::query()
            ->where('cart_id', $input->cartId)
            ->orderBy('product_variant_id')
            ->get()
            ->map(static fn (CartItem $item): array => [
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => (int) $item->quantity,
            ])
            ->all();

        $incomingFingerprint = $this->payloadFingerprint->fromPlaceOrderInput($input, $cartLines);
        $storedFingerprint = $this->payloadFingerprint->fromOrder($order, $cartId, $orderLines);

        if (! hash_equals($storedFingerprint, $incomingFingerprint)) {
            throw new PlaceOrderIdempotencyConflictException($input->idempotencyKey);
        }

        $payment = $order->payments()->where('attempt_number', 1)->first();

        if ($payment === null) {
            throw new PlaceOrderException(
                PlaceOrderException::ORDER_NOT_REPLAYABLE,
                'Order is missing the initial payment attempt.',
            );
        }

        return new PlaceOrderResult(
            orderId: $order->getKey(),
            publicUuid: $order->public_uuid,
            orderNumber: $order->order_number,
            paymentId: $payment->getKey(),
            grandTotal: (string) $order->grand_total,
            currencyCode: $order->currency_code,
            idempotentReplay: true,
        );
    }

    private function resolveCartIdForOrder(Order $order): int
    {
        $cartId = InventoryReservation::query()
            ->where('order_id', $order->getKey())
            ->whereNotNull('cart_id')
            ->value('cart_id');

        if ($cartId === null) {
            throw new PlaceOrderException(
                PlaceOrderException::ORDER_NOT_REPLAYABLE,
                'Order is not linked to a cart reservation.',
            );
        }

        return (int) $cartId;
    }

    private function resolveCustomer(PlaceOrderInput $input): Customer
    {
        if ($input->userId !== null) {
            $user = User::query()->whereKey($input->userId)->first();
            if ($user === null) {
                throw new PlaceOrderException(
                    PlaceOrderException::GUEST_EMAIL_CONFLICT,
                    'Authenticated user was not found.',
                );
            }

            $customer = Customer::query()->where('user_id', $input->userId)->first();

            if ($customer === null) {
                $customer = Customer::query()->create([
                    'user_id' => $input->userId,
                    'email' => $user->email,
                    'first_name' => (string) ($input->shippingAddress['first_name'] ?? 'Customer'),
                    'last_name' => (string) ($input->shippingAddress['last_name'] ?? 'User'),
                ]);
            }

            return $customer;
        }

        $email = strtolower(trim((string) $input->guestEmail));

        $registeredConflict = Customer::query()
            ->where('email', $email)
            ->whereNotNull('user_id')
            ->exists();

        if ($registeredConflict) {
            throw new PlaceOrderException(
                PlaceOrderException::GUEST_EMAIL_CONFLICT,
                'Guest checkout cannot use an email that belongs to a registered account.',
            );
        }

        $customer = Customer::query()->where('email', $email)->whereNull('user_id')->first();

        if ($customer === null) {
            $customer = Customer::query()->create([
                'user_id' => null,
                'email' => $email,
                'first_name' => (string) ($input->shippingAddress['first_name'] ?? 'Guest'),
                'last_name' => (string) ($input->shippingAddress['last_name'] ?? 'Customer'),
            ]);
        }

        $cart = Cart::query()->whereKey($input->cartId)->first();
        if ($cart !== null && $cart->customer_id === null) {
            $cart->customer_id = $customer->getKey();
            $cart->save();
        }

        return $customer;
    }

    private function generateOrderNumber(string $prefix): string
    {
        $sanitizedPrefix = $prefix !== '' ? $prefix : 'ORD';

        return $sanitizedPrefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }

    private function firstPaymentIdempotencyKey(string $orderIdempotencyKey): string
    {
        return hash('sha256', 'payment:'.$orderIdempotencyKey.':attempt:1');
    }

    private function isIdempotencyKeyDuplicate(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'idempotency_key')
            || str_contains($message, 'orders.idempotency_key')
            || str_contains($message, 'unique constraint failed: orders.idempotency_key');
    }
}
