<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Payments\Actions\ProcessMockPaymentSimulationAction;
use App\Domains\Payments\Exceptions\PaymentGatewayException;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Domains\Payments\Gateways\MockPaymentGateway;
use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MockPaymentController extends Controller
{
    public function show(Request $request, Payment $payment): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        try {
            app(MockPaymentGateway::class)->assertOperational();
        } catch (PaymentGatewayException) {
            abort(404);
        }

        if ($payment->provider !== 'mock') {
            abort(404);
        }

        $payment->load('order');

        return view('storefront.payments.mock', [
            'payment' => $payment,
            'order' => $payment->order,
        ]);
    }

    public function success(
        Request $request,
        Payment $payment,
        ProcessMockPaymentSimulationAction $simulationAction,
    ): RedirectResponse {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        try {
            $simulationAction->executeSuccess((int) $payment->getKey());
        } catch (PaymentGatewayException) {
            abort(404);
        } catch (PaymentOperationException) {
            return $this->redirectToOrderOutcome($payment, withError: true);
        }

        return $this->redirectToOrderOutcome($payment);
    }

    public function failure(
        Request $request,
        Payment $payment,
        ProcessMockPaymentSimulationAction $simulationAction,
    ): RedirectResponse {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        try {
            $simulationAction->executeFailure((int) $payment->getKey());
        } catch (PaymentGatewayException) {
            abort(404);
        } catch (PaymentOperationException) {
            return $this->redirectToOrderOutcome($payment, withError: true);
        }

        return $this->redirectToOrderOutcome($payment);
    }

    private function redirectToOrderOutcome(Payment $payment, bool $withError = false): RedirectResponse
    {
        $payment->load('order');
        $order = $payment->order;

        $redirect = redirect()->route('storefront.checkout.success', [
            'publicUuid' => $order->public_uuid,
        ]);

        if ($withError) {
            $redirect = $redirect->withErrors(['payment' => __('storefront.checkout.errors.payment_generic')]);
        }

        return $redirect;
    }
}
