<?php

namespace Tests\Support;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;

trait CreatesCustomerAccountFixtures
{
    protected function createCustomerWithUser(
        string $email = 'customer@example.test',
        string $password = 'password123',
        string $firstName = 'Cust',
        string $lastName = 'Omer',
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'password' => $password,
            'name' => trim($firstName.' '.$lastName),
        ]);

        Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => strtolower($email),
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        return $user;
    }
}
