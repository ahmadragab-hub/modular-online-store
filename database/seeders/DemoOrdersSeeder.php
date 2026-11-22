<?php

namespace Database\Seeders;

use App\Domains\Cart\Actions\AddCartItemAction;
use App\Domains\Cart\Actions\GetOrCreateCustomerCartAction;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\Actions\PlaceOrderAction;
use App\Domains\Checkout\DTOs\PlaceOrderInput;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Orders\Actions\TransitionOrderStatusAction;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\CompleteVerifiedPaymentSuccessAction;
use App\Domains\Payments\Actions\RecordPaymentFailureAction;
use App\Domains\Payments\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Representative order scenarios via domain Actions (ADMIN-006 / CHECKOUT-009).
 */
class DemoOrdersSeeder extends Seeder
{
    private const IDEM_PENDING = 'demo-scenario-pending-payment-v1';

    private const IDEM_FAILED = 'demo-scenario-payment-failed-v1';

    private const IDEM_PROCESSING = 'demo-scenario-processing-v1';

    private const IDEM_SHIPPED = 'demo-scenario-shipped-v1';

    private const IDEM_DELIVERED = 'demo-scenario-delivered-v1';

    public function run(): void
    {
        $admin = User::query()->where('email', DemoUsersSeeder::ADMIN_EMAIL)->first();

        if ($admin === null) {
            return;
        }

        $variant = ProductVariant::query()->where('sku', 'DEMO-OXF-M-WHT')->first();

        if ($variant === null) {
            return;
        }

        $this->seedScenario(
            customerEmail: 'demo.customer1@example.local',
            idempotencyKey: self::IDEM_PENDING,
            currency: 'EGP',
            afterPlace: null,
        );

        $this->seedScenario(
            customerEmail: 'demo.customer2@example.local',
            idempotencyKey: self::IDEM_FAILED,
            currency: 'EGP',
            afterPlace: function (Order $order, Payment $payment): void {
                app(RecordPaymentFailureAction::class)->execute(
                    (int) $payment->getKey(),
                    'demo_declined',
                    'Demo payment failure scenario.',
                );
            },
        );

        $this->seedScenario(
            customerEmail: 'demo.customer3@example.local',
            idempotencyKey: self::IDEM_PROCESSING,
            currency: 'USD',
            afterPlace: function (Order $order, Payment $payment): void {
                app(CompleteVerifiedPaymentSuccessAction::class)->execute(
                    (int) $payment->getKey(),
                    'demo-provider-processing',
                );
            },
        );

        $this->seedScenario(
            customerEmail: 'demo.customer1@example.local',
            idempotencyKey: self::IDEM_SHIPPED,
            currency: 'EGP',
            afterPlace: function (Order $order, Payment $payment) use ($admin): void {
                app(CompleteVerifiedPaymentSuccessAction::class)->execute(
                    (int) $payment->getKey(),
                    'demo-provider-shipped',
                );
                app(TransitionOrderStatusAction::class)->execute(
                    (int) $order->getKey(),
                    Order::STATUS_SHIPPED,
                    (int) $admin->getKey(),
                    'Demo fulfillment: shipped.',
                );
            },
        );

        $this->seedScenario(
            customerEmail: 'demo.customer2@example.local',
            idempotencyKey: self::IDEM_DELIVERED,
            currency: 'EGP',
            afterPlace: function (Order $order, Payment $payment) use ($admin): void {
                app(CompleteVerifiedPaymentSuccessAction::class)->execute(
                    (int) $payment->getKey(),
                    'demo-provider-delivered',
                );
                $transition = app(TransitionOrderStatusAction::class);
                $transition->execute(
                    (int) $order->getKey(),
                    Order::STATUS_SHIPPED,
                    (int) $admin->getKey(),
                    'Demo fulfillment: shipped.',
                );
                $transition->execute(
                    (int) $order->getKey(),
                    Order::STATUS_DELIVERED,
                    (int) $admin->getKey(),
                    'Demo fulfillment: delivered.',
                );
            },
        );
    }

    /**
     * @param  callable(Order, Payment): void|null  $afterPlace
     */
    private function seedScenario(
        string $customerEmail,
        string $idempotencyKey,
        string $currency,
        ?callable $afterPlace,
    ): void {
        if (Order::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $user = User::query()->where('email', strtolower($customerEmail))->first();
        $customer = $user !== null
            ? Customer::query()->where('user_id', $user->getKey())->first()
            : null;

        if ($user === null || $customer === null) {
            return;
        }

        $variant = ProductVariant::query()->where('sku', 'DEMO-OXF-M-WHT')->firstOrFail();

        DB::transaction(function () use ($user, $customer, $idempotencyKey, $currency, $afterPlace, $variant): void {
            $cart = app(GetOrCreateCustomerCartAction::class)->execute(
                $customer,
                $user,
                $currency,
            );

            app(AddCartItemAction::class)->execute(
                $cart,
                (int) $variant->getKey(),
                1,
                'en',
            );

            $addresses = $this->demoAddresses($customer);

            $result = app(PlaceOrderAction::class)->execute(new PlaceOrderInput(
                cartId: (int) $cart->fresh()->getKey(),
                transactionCurrencyCode: $currency,
                contentLocale: 'en',
                idempotencyKey: $idempotencyKey,
                shippingAddress: $addresses,
                billingAddress: $addresses,
                guestEmail: null,
                customerNote: 'Demo scenario order.',
                userId: (int) $user->getKey(),
                sessionToken: null,
            ));

            $order = Order::query()->whereKey($result->orderId)->firstOrFail();
            $payment = Payment::query()->whereKey($result->paymentId)->firstOrFail();

            if ($afterPlace !== null) {
                $afterPlace($order, $payment);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function demoAddresses(Customer $customer): array
    {
        return [
            'first_name' => $customer->first_name ?? 'Demo',
            'last_name' => $customer->last_name ?? 'Customer',
            'line1' => '12 Nile Corniche',
            'city' => 'Cairo',
            'country_code' => 'EG',
        ];
    }
}
