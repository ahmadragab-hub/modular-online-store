<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\AdminLoginException;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

final class LoginAdminAction
{
    public function execute(string $email, string $password): User
    {
        $email = strtolower(trim($email));

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new AdminLoginException;
        }

        if ($user->role !== User::ROLE_ADMIN || ! $user->isActive()) {
            throw new AdminLoginException;
        }

        return $user;
    }
}
