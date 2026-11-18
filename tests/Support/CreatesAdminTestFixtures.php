<?php

namespace Tests\Support;

use App\Domains\Identity\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;

trait CreatesAdminTestFixtures
{
    protected function seedAdminBaselines(): void
    {
        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);
    }

    protected function createActiveAdmin(array $overrides = []): User
    {
        return User::factory()->admin()->create($overrides);
    }
}
