<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\CreatePaymentRetryAttemptAction;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RetryPaymentRequest;
use Illuminate\Http\RedirectResponse;

final class OrderPaymentController extends Controller
{
    public function retry(
        RetryPaymentRequest $request,
        string $publicUuid,
        CreatePaymentRetryAttemptAction $createPaymentRetryAttemptAction,
        InitiatePaymentAction $initiatePaymentAction,
    ): RedirectResponse {
        /** @var Order|null $order */
        $order = Order::query()->where('public_uuid', $publicUuid)->first();

        if ($order === null) {
            abort(404);
        }

        try {
            $payment = $createPaymentRetryAttemptAction->execute(
                (int) $order->getKey(),
                (string) $request->validated('payment_idempotency_key'),
            );

            $initiation = $initiatePaymentAction->execute((int) $payment->getKey());

            return redirect()->away($initiation->redirectUrl);
        } catch (PaymentOperationException) {
            return redirect()
                ->route('storefront.checkout.success', ['publicUuid' => $publicUuid])
                ->withErrors(['payment' => __('storefront.checkout.errors.payment_retry')]);
        }
    }
}
