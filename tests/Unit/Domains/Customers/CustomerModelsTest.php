<?php

namespace Tests\Unit\Domains\Customers;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Identity\Models\User;
use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\StoreLocale;
use App\Domains\Store\Models\StoreTheme;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
        ]);
    }

    public function test_customer_persists_guest_and_registered(): void
    {
        $guest = Customer::query()->create([
            'email' => 'guest@example.test',
            'first_name' => 'Guest',
            'last_name' => 'Shopper',
        ]);
        $guest->refresh();

        $this->assertSame('customers', $guest->getTable());
        $this->assertNull($guest->user_id);
        $this->assertFalse($guest->marketing_opt_in);

        $user = User::factory()->create();
        $registered = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => 'member@example.test',
            'first_name' => 'Member',
            'last_name' => 'User',
            'marketing_opt_in' => true,
        ]);

        $this->assertTrue($registered->marketing_opt_in);
        $this->assertInstanceOf(User::class, $registered->user);
        $this->assertTrue($user->customer()->whereKey($registered->getKey())->exists());
    }

    public function test_customer_preference_relationships(): void
    {
        $customer = Customer::query()->create([
            'email' => 'prefs@example.test',
            'first_name' => 'Pref',
            'last_name' => 'Test',
            'preferred_display_currency_code' => 'EGP',
            'preferred_locale' => 'ar',
            'preferred_theme' => 'dark',
        ]);

        $this->assertInstanceOf(Currency::class, $customer->preferredDisplayCurrency);
        $this->assertSame('EGP', $customer->preferredDisplayCurrency->code);
        $this->assertInstanceOf(StoreLocale::class, $customer->preferredLocale);
        $this->assertSame('ar', $customer->preferredLocale->code);
        $this->assertInstanceOf(StoreTheme::class, $customer->preferredTheme);
        $this->assertSame('dark', $customer->preferredTheme->code);
    }

    public function test_customer_address_relationship_and_cascade(): void
    {
        $customer = Customer::query()->create([
            'email' => 'addr@example.test',
            'first_name' => 'Ada',
            'last_name' => 'Dress',
        ]);

        $address = CustomerAddress::query()->create([
            'customer_id' => $customer->getKey(),
            'first_name' => 'Ada',
            'last_name' => 'Dress',
            'line1' => '10 Main St',
            'city' => 'Cairo',
            'postal_code' => '11511',
            'country_code' => 'EG',
            'is_default_shipping' => true,
        ]);

        $this->assertSame('customer_addresses', $address->getTable());
        $this->assertTrue($address->is_default_shipping);
        $this->assertInstanceOf(Customer::class, $address->customer);
        $this->assertTrue($customer->addresses()->whereKey($address->getKey())->exists());

        $customer->delete();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->getKey()]);
    }

    public function test_unique_email_and_user_id_constraints(): void
    {
        Customer::query()->create([
            'email' => 'duplicate@example.test',
            'first_name' => 'One',
            'last_name' => 'User',
        ]);

        $this->expectException(QueryException::class);
        Customer::query()->create([
            'email' => 'duplicate@example.test',
            'first_name' => 'Two',
            'last_name' => 'User',
        ]);
    }

    public function test_unique_user_id_constraint(): void
    {
        $user = User::factory()->create();

        Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => 'linked@example.test',
            'first_name' => 'Linked',
            'last_name' => 'User',
        ]);

        $this->expectException(QueryException::class);
        Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => 'other@example.test',
            'first_name' => 'Other',
            'last_name' => 'User',
        ]);
    }

    public function test_guest_and_registered_scopes(): void
    {
        Customer::query()->create([
            'email' => 'guest-scope@example.test',
            'first_name' => 'G',
            'last_name' => 'S',
        ]);

        $user = User::factory()->create();
        Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => 'reg-scope@example.test',
            'first_name' => 'R',
            'last_name' => 'S',
        ]);

        $this->assertSame(1, Customer::query()->guestIdentity()->count());
        $this->assertSame(1, Customer::query()->registered()->count());
    }
}
