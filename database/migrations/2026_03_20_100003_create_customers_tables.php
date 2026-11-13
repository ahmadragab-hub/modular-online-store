<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 32)->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->char('preferred_display_currency_code', 3)->nullable();
            $table->char('preferred_locale', 5)->nullable();
            $table->string('preferred_theme', 32)->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['last_name', 'first_name'], 'customers_name_idx');

            $table->foreign('preferred_display_currency_code')
                ->references('code')
                ->on('currencies')
                ->nullOnDelete();
            $table->foreign('preferred_locale')
                ->references('code')
                ->on('store_locales')
                ->nullOnDelete();
            $table->foreign('preferred_theme')
                ->references('code')
                ->on('store_themes')
                ->nullOnDelete();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 64)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 32)->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20);
            $table->char('country_code', 2);
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
