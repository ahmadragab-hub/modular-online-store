<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreLocaleSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'code' => 'ar',
                'name' => 'Arabic',
                'native_name' => 'العربية',
                'direction' => 'rtl',
                'is_active' => true,
                'sort_order' => 1,
            ],
        ];

        $now = now();

        foreach ($rows as $row) {
            DB::table('store_locales')->updateOrInsert(
                ['code' => $row['code']],
                array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}
