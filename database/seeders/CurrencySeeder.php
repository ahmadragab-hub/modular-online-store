<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'minor_units' => 2,
                'is_base' => true,
                'display_enabled' => true,
                'transaction_enabled' => true,
                'sort_order' => 0,
            ],
            [
                'code' => 'EGP',
                'name' => 'Egyptian Pound',
                'symbol' => 'E£',
                'minor_units' => 2,
                'is_base' => false,
                'display_enabled' => true,
                'transaction_enabled' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'KWD',
                'name' => 'Kuwaiti Dinar',
                'symbol' => 'د.ك',
                'minor_units' => 3,
                'is_base' => false,
                'display_enabled' => true,
                'transaction_enabled' => true,
                'sort_order' => 2,
            ],
        ];

        $now = now();

        foreach ($rows as $row) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $row['code']],
                array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}
