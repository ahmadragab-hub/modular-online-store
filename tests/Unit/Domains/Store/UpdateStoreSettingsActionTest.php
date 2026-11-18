<?php

namespace Tests\Unit\Domains\Store;

use App\Domains\Store\Actions\UpdateStoreSettingsAction;
use App\Domains\Store\Models\StoreSetting;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateStoreSettingsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_singleton_settings(): void
    {
        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);
        $action = $this->app->make(UpdateStoreSettingsAction::class);

        $result = $action->execute([
            'store_name' => 'Updated Store',
            'reservation_ttl_minutes' => 30,
        ]);

        $this->assertSame('Updated Store', $result->store_name);
        $this->assertSame(30, StoreSetting::singleton()?->reservation_ttl_minutes);
    }
}
