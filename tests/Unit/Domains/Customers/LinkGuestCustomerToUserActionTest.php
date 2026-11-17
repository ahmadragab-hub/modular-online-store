<?php

namespace Tests\Unit\Domains\Customers;

use App\Domains\Customers\Actions\LinkGuestCustomerToUserAction;
use App\Domains\Customers\Exceptions\CustomerRegistrationException;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkGuestCustomerToUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_guest_customer_and_updates_names(): void
    {
        $user = User::factory()->create();
        $guest = Customer::query()->create([
            'email' => 'guest@example.test',
            'first_name' => 'Old',
            'last_name' => 'Guest',
        ]);

        $action = new LinkGuestCustomerToUserAction;
        $linked = $action->execute($guest, $user, 'New', 'Name');

        $this->assertSame($guest->getKey(), $linked->getKey());
        $this->assertSame($user->getKey(), $linked->user_id);
        $this->assertSame('New', $linked->first_name);
        $this->assertSame('Name', $linked->last_name);
        $this->assertSame(strtolower($user->email), $linked->email);
    }

    public function test_normalizes_customer_email_when_guest_stored_different_casing(): void
    {
        $user = User::factory()->create(['email' => 'Buyer@Example.test']);
        $guest = Customer::query()->create([
            'email' => 'BUYER@EXAMPLE.TEST',
            'first_name' => 'Old',
            'last_name' => 'Guest',
        ]);

        $linked = app(LinkGuestCustomerToUserAction::class)->execute($guest, $user, 'New', 'Name');

        $this->assertSame('buyer@example.test', $linked->email);
    }

    public function test_rejects_already_linked_customer(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $owner->getKey(),
            'email' => 'linked@example.test',
            'first_name' => 'L',
            'last_name' => 'U',
        ]);

        $action = new LinkGuestCustomerToUserAction;

        $this->expectException(CustomerRegistrationException::class);
        $action->execute($customer, $other, 'X', 'Y');
    }
}
