<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->char('code', 3)->primary();
            $table->string('name', 64);
            $table->string('symbol', 16);
            $table->unsignedTinyInteger('minor_units')->default(2);
            $table->boolean('is_base')->default(false);
            $table->boolean('display_enabled')->default(true);
            $table->boolean('transaction_enabled')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_locales', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->char('code', 5)->primary();
            $table->string('name', 64);
            $table->string('native_name', 64);
            $table->string('direction', 3);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_themes', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->string('code', 32)->primary();
            $table->string('name', 64);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_settings', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->unsignedBigInteger('id')->primary();
            $table->string('store_name');
            $table->string('contact_email');
            $table->string('contact_phone', 32)->nullable();
            $table->char('base_currency_code', 3)->default('USD');
            $table->char('default_display_currency_code', 3)->default('USD');
            $table->string('timezone', 64)->default('UTC');
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->boolean('tax_enabled')->default(false);
            $table->boolean('prices_include_tax')->default(false);
            $table->decimal('default_tax_rate', 5, 4)->nullable();
            $table->json('tax_configuration')->nullable();
            $table->boolean('shipping_enabled')->default(true);
            $table->decimal('flat_shipping_amount', 12, 2)->nullable();
            $table->decimal('free_shipping_threshold', 12, 2)->nullable();
            $table->string('order_number_prefix', 16)->default('ORD');
            $table->unsignedSmallInteger('reservation_ttl_minutes')->default(15);
            $table->boolean('guest_checkout_enabled')->default(true);
            $table->char('default_locale', 5)->default('en');
            $table->string('default_theme', 32)->default('light');
            $table->timestamps();

            $table->foreign('base_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->foreign('default_display_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->foreign('default_locale')
                ->references('code')
                ->on('store_locales')
                ->restrictOnDelete();
            $table->foreign('default_theme')
                ->references('code')
                ->on('store_themes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
        Schema::dropIfExists('store_themes');
        Schema::dropIfExists('store_locales');
        Schema::dropIfExists('currencies');
    }
};
