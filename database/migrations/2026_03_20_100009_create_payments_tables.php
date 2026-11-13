<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->decimal('amount', 19, 4);
            $table->char('currency_code', 3);
            $table->string('status', 32);
            $table->string('provider', 32)->nullable();
            $table->string('provider_payment_id', 128)->nullable();
            $table->string('provider_checkout_url', 1024)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'attempt_number'], 'payments_order_attempt_unique');
            $table->unique(['provider', 'provider_payment_id'], 'payments_provider_payment_unique');

            $table->foreign('currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
        });

        Schema::create('payment_provider_events', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_event_id', 128);
            $table->string('event_type', 64);
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_result', 32)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['provider', 'provider_event_id'], 'payment_provider_events_provider_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_events');
        Schema::dropIfExists('payments');
    }
};
