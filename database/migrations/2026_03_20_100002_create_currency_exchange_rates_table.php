<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_exchange_rates', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->char('quote_currency_code', 3);
            $table->decimal('rate', 18, 8);
            $table->timestamp('effective_from');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->foreign('quote_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();

            $table->index(['quote_currency_code', 'effective_from'], 'currency_exchange_rates_quote_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
    }
};
