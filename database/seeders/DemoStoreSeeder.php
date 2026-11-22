<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent local demo dataset (MySQL application DB only).
 *
 * php artisan db:seed --class=DemoStoreSeeder
 */
class DemoStoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);

        DB::table('store_settings')->where('id', 1)->update([
            'store_name' => 'Maison Demo Boutique',
            'contact_email' => 'hello@demo.local',
            'contact_phone' => '+20 2 0000 0000',
            'city' => 'Cairo',
            'country_code' => 'EG',
            'updated_at' => now(),
        ]);

        $this->call([
            DemoCatalogSeeder::class,
            DemoUsersSeeder::class,
            DemoOrdersSeeder::class,
        ]);
    }
}
