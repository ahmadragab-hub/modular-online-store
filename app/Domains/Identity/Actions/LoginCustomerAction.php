<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\CustomerLoginException;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

final class LoginCustomerAction
{
    public function execute(string $email, string $password): User
    {
        $email = strtolower(trim($email));

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new CustomerLoginException;
        }

        if ($user->role !== User::ROLE_CUSTOMER || ! $user->isActive()) {
            throw new CustomerLoginException;
        }

        return $user;
    }
}
