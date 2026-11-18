<?php

namespace Database\Seeders;

use App\Domains\Store\Models\CurrencyExchangeRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $settings = [
            'id' => 1,
            'store_name' => 'Fashion Store',
            'contact_email' => 'contact@example.local',
            'contact_phone' => null,
            'base_currency_code' => 'EGP',
            'default_display_currency_code' => 'EGP',
            'timezone' => 'UTC',
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => null,
            'tax_enabled' => false,
            'prices_include_tax' => false,
            'default_tax_rate' => null,
            'tax_configuration' => null,
            'shipping_enabled' => true,
            'flat_shipping_amount' => '0.00',
            'free_shipping_threshold' => null,
            'order_number_prefix' => 'ORD',
            'reservation_ttl_minutes' => 15,
            'guest_checkout_enabled' => true,
            'default_locale' => 'en',
            'default_theme' => 'light',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('store_settings')->updateOrInsert(
            ['id' => 1],
            $settings
        );

        if (! CurrencyExchangeRate::query()->where('quote_currency_code', 'USD')->exists()) {
            CurrencyExchangeRate::query()->create([
                'quote_currency_code' => 'USD',
                'rate' => '0.02000000',
                'effective_from' => now()->subYear(),
                'notes' => 'Seed USD per 1 EGP for development and tests.',
                'created_by_user_id' => null,
                'created_at' => $now,
            ]);
        }
    }
}
