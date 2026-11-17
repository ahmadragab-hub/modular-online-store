<?php

namespace Tests\Unit\Domains\Customers;

use App\Domains\Customers\Actions\CreateCustomerForUserAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCustomerForUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_customer_for_user(): void
    {
        $user = User::factory()->create(['email' => 'new@example.test']);

        $customer = (new CreateCustomerForUserAction)->execute(
            $user,
            'new@example.test',
            'Ada',
            'Lovelace',
        );

        $this->assertSame($user->getKey(), $customer->user_id);
        $this->assertSame('new@example.test', $customer->email);
        $this->assertSame('Ada', $customer->first_name);
    }
}
