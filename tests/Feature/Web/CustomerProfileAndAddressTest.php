<?php

namespace Tests\Feature\Web;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Identity\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCustomerAccountFixtures;
use Tests\TestCase;

class CustomerProfileAndAddressTest extends TestCase
{
    use CreatesCustomerAccountFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
    }

    public function test_customer_can_view_and_update_profile(): void
    {
        $user = $this->createCustomerWithUser();

        $this->actingAs($user)
            ->get(route('storefront.account.profile.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->put(route('storefront.account.profile.update'), [
                'first_name' => 'New',
                'last_name' => 'Name',
            ])
            ->assertRedirect(route('storefront.account.index'));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('New', Customer::query()->where('user_id', $user->getKey())->value('first_name'));
    }

    public function test_admin_cannot_access_customer_profile_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('storefront.account.profile.edit'))
            ->assertForbidden();
    }

    public function test_first_address_becomes_default_shipping_and_billing(): void
    {
        $user = $this->createCustomerWithUser();

        $this->actingAs($user)
            ->post(route('storefront.account.addresses.store'), $this->validAddress())
            ->assertRedirect(route('storefront.account.addresses.index'));

        $address = CustomerAddress::query()->first();
        $this->assertTrue($address->is_default_shipping);
        $this->assertTrue($address->is_default_billing);
    }

    public function test_shipping_and_billing_defaults_are_independent(): void
    {
        $user = $this->createCustomerWithUser();

        $this->actingAs($user)->post(route('storefront.account.addresses.store'), $this->validAddress(['line1' => 'One']));
        $this->actingAs($user)->post(route('storefront.account.addresses.store'), $this->validAddress(['line1' => 'Two']));

        $second = CustomerAddress::query()->where('line1', 'Two')->firstOrFail();

        $this->actingAs($user)
            ->post(route('storefront.account.addresses.default-billing', $second))
            ->assertRedirect();

        $first = CustomerAddress::query()->where('line1', 'One')->firstOrFail();
        $second->refresh();

        $this->assertTrue($first->is_default_shipping);
        $this->assertFalse($first->is_default_billing);
        $this->assertFalse($second->is_default_shipping);
        $this->assertTrue($second->is_default_billing);
        $this->assertSame(1, CustomerAddress::query()->where('is_default_shipping', true)->count());
        $this->assertSame(1, CustomerAddress::query()->where('is_default_billing', true)->count());
    }

    public function test_customer_cannot_access_another_customers_address(): void
    {
        $owner = $this->createCustomerWithUser('owner-addr@example.test');
        $other = $this->createCustomerWithUser('other-addr@example.test');

        $ownerCustomer = Customer::query()->where('user_id', $owner->getKey())->firstOrFail();
        $address = CustomerAddress::query()->create([
            'customer_id' => $ownerCustomer->getKey(),
            ...$this->validAddress(),
        ]);

        $this->actingAs($other)
            ->get(route('storefront.account.addresses.edit', $address))
            ->assertNotFound();
    }

    public function test_invalid_profile_input_is_rejected_without_changes(): void
    {
        $user = $this->createCustomerWithUser();
        $customer = Customer::query()->where('user_id', $user->getKey())->firstOrFail();
        $originalName = $user->name;

        $this->actingAs($user)
            ->from(route('storefront.account.profile.edit'))
            ->put(route('storefront.account.profile.update'), [
                'first_name' => '',
                'last_name' => 'Name',
            ])
            ->assertRedirect(route('storefront.account.profile.edit'))
            ->assertSessionHasErrors('first_name');

        $user->refresh();
        $customer->refresh();
        $this->assertSame($originalName, $user->name);
        $this->assertSame('Cust', $customer->first_name);
    }

    public function test_deleting_default_address_promotes_remaining_default(): void
    {
        $user = $this->createCustomerWithUser();

        $this->actingAs($user)->post(route('storefront.account.addresses.store'), $this->validAddress(['line1' => 'One']));
        $this->actingAs($user)->post(route('storefront.account.addresses.store'), $this->validAddress(['line1' => 'Two']));

        $first = CustomerAddress::query()->where('line1', 'One')->firstOrFail();
        $second = CustomerAddress::query()->where('line1', 'Two')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('storefront.account.addresses.destroy', $first))
            ->assertRedirect(route('storefront.account.addresses.index'));

        $second->refresh();
        $this->assertTrue($second->is_default_shipping);
        $this->assertTrue($second->is_default_billing);
        $this->assertSame(1, CustomerAddress::query()->where('is_default_shipping', true)->count());
        $this->assertSame(1, CustomerAddress::query()->where('is_default_billing', true)->count());
    }

    public function test_customer_cannot_update_or_delete_another_customers_address(): void
    {
        $owner = $this->createCustomerWithUser('owner-upd@example.test');
        $other = $this->createCustomerWithUser('other-upd@example.test');
        $ownerCustomer = Customer::query()->where('user_id', $owner->getKey())->firstOrFail();
        $address = CustomerAddress::query()->create([
            'customer_id' => $ownerCustomer->getKey(),
            ...$this->validAddress(),
        ]);

        $this->actingAs($other)
            ->put(route('storefront.account.addresses.update', $address), $this->validAddress(['line1' => 'Hacked']))
            ->assertNotFound();

        $this->actingAs($other)
            ->delete(route('storefront.account.addresses.destroy', $address))
            ->assertNotFound();

        $this->assertSame('1 Main St', $address->fresh()->line1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAddress(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'line1' => '1 Main St',
            'city' => 'Cairo',
            'postal_code' => '11511',
            'country_code' => 'EG',
        ], $overrides);
    }
}
