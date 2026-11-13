<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('order_number', 32)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_email')->nullable();
            $table->string('status', 32)->default('pending_payment');
            $table->char('base_currency_code', 3)->default('USD');
            $table->char('currency_code', 3);
            $table->char('content_locale', 5);
            $table->decimal('exchange_rate', 18, 8);
            $table->foreignId('currency_exchange_rate_id')->nullable()->constrained('currency_exchange_rates')->nullOnDelete();
            $table->timestamp('exchange_rate_effective_from')->nullable();
            $table->decimal('subtotal', 19, 4);
            $table->decimal('discount_total', 19, 4)->default('0.0000');
            $table->decimal('tax_total', 19, 4)->default('0.0000');
            $table->decimal('shipping_total', 19, 4)->default('0.0000');
            $table->decimal('grand_total', 19, 4);
            $table->json('shipping_address');
            $table->json('billing_address');
            $table->string('customer_note', 500)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index('guest_email');
            $table->index(['status', 'created_at']);

            $table->foreign('base_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->foreign('currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->foreign('content_locale')
                ->references('code')
                ->on('store_locales')
                ->restrictOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku', 64);
            $table->string('variant_label');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_base_usd', 12, 2);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('line_subtotal', 19, 4);
            $table->decimal('tax_amount', 19, 4)->default('0.0000');
            $table->timestamp('created_at')->nullable();

            $table->index('order_id');
            $table->index('sku');
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('comment', 500)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
