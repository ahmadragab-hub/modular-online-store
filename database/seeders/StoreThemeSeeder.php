<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreThemeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'light',
                'name' => 'Light',
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'code' => 'dark',
                'name' => 'Dark',
                'is_active' => true,
                'sort_order' => 1,
            ],
        ];

        $now = now();

        foreach ($rows as $row) {
            DB::table('store_themes')->updateOrInsert(
                ['code' => $row['code']],
                array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}
