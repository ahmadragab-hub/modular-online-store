<?php

namespace Tests\Unit\Domains\Store;

use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\CurrencyExchangeRate;
use App\Domains\Store\Models\StoreLocale;
use App\Domains\Store\Models\StoreSetting;
use App\Domains\Store\Models\StoreTheme;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);
    }

    public function test_currency_uses_code_primary_key_and_table(): void
    {
        $currency = Currency::query()->find('USD');

        $this->assertNotNull($currency);
        $this->assertSame('currencies', $currency->getTable());
        $this->assertSame('code', $currency->getKeyName());
        $this->assertFalse($currency->getIncrementing());
        $this->assertSame('string', $currency->getKeyType());
        $this->assertSame(2, $currency->minor_units);
        $this->assertTrue($currency->is_base);
    }

    public function test_store_locale_and_theme_use_string_primary_keys(): void
    {
        $locale = StoreLocale::query()->find('ar');
        $theme = StoreTheme::query()->find('dark');

        $this->assertNotNull($locale);
        $this->assertNotNull($theme);
        $this->assertSame('store_locales', $locale->getTable());
        $this->assertSame('store_themes', $theme->getTable());
        $this->assertTrue($locale->is_active);
        $this->assertSame('rtl', $locale->direction);
        $this->assertTrue($theme->is_active);
    }

    public function test_store_settings_singleton_and_relationships(): void
    {
        $settings = StoreSetting::singleton();

        $this->assertNotNull($settings);
        $this->assertSame(StoreSetting::SINGLETON_ID, (int) $settings->getKey());
        $this->assertSame('store_settings', $settings->getTable());
        $this->assertFalse($settings->tax_enabled);
        $this->assertSame('USD', $settings->base_currency_code);
        $this->assertSame('en', $settings->default_locale);
        $this->assertSame('light', $settings->default_theme);

        $this->assertInstanceOf(Currency::class, $settings->baseCurrency);
        $this->assertSame('USD', $settings->baseCurrency->code);
        $this->assertInstanceOf(Currency::class, $settings->defaultDisplayCurrency);
        $this->assertInstanceOf(StoreLocale::class, $settings->defaultLocale);
        $this->assertInstanceOf(StoreTheme::class, $settings->defaultTheme);
    }

    public function test_store_settings_json_cast(): void
    {
        $settings = StoreSetting::singleton();
        $settings->tax_configuration = ['zones' => ['default' => ['rate' => 0]]];
        $settings->save();
        $settings->refresh();

        $this->assertIsArray($settings->tax_configuration);
        $this->assertSame(['rate' => 0], $settings->tax_configuration['zones']['default']);
    }

    public function test_currency_exchange_rate_decimal_precision_and_relationships(): void
    {
        $rate = CurrencyExchangeRate::query()->create([
            'quote_currency_code' => 'EGP',
            'rate' => '48.12345678',
            'effective_from' => now(),
            'created_at' => now(),
        ]);

        $rate->refresh();

        $this->assertSame('currency_exchange_rates', $rate->getTable());
        $this->assertNull($rate->updated_at);
        $this->assertSame('48.12345678', (string) $rate->rate);
        $this->assertInstanceOf(Currency::class, $rate->quoteCurrency);
        $this->assertSame('EGP', $rate->quoteCurrency->code);

        $egp = Currency::query()->find('EGP');
        $this->assertNotNull($egp);
        $this->assertTrue($egp->exchangeRates()->whereKey($rate->getKey())->exists());
    }

    public function test_kwd_minor_units_from_seed(): void
    {
        $kwd = Currency::query()->find('KWD');

        $this->assertNotNull($kwd);
        $this->assertSame(3, $kwd->minor_units);
    }
}
