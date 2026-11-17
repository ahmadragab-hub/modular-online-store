<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Customers\Actions\CreateCustomerForUserAction;
use App\Domains\Customers\Actions\LinkGuestCustomerToUserAction;
use App\Domains\Customers\Exceptions\CustomerRegistrationException;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\DetectUsersEmailUniqueViolation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RegisterCustomerAction
{
    public function __construct(
        private readonly CreateCustomerForUserAction $createCustomerForUserAction,
        private readonly LinkGuestCustomerToUserAction $linkGuestCustomerToUserAction,
    ) {}

    public function execute(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
    ): User {
        $email = strtolower(trim($email));
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $name = trim($firstName.' '.$lastName);

        if (User::query()->where('email', $email)->exists()) {
            throw new CustomerRegistrationException(
                CustomerRegistrationException::EMAIL_TAKEN,
                'This email is already registered.',
            );
        }

        $existingCustomer = Customer::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingCustomer !== null && $existingCustomer->user_id !== null) {
            throw new CustomerRegistrationException(
                CustomerRegistrationException::CUSTOMER_ALREADY_REGISTERED,
                'This email is already registered.',
            );
        }

        try {
            return DB::transaction(function () use (
                $email,
                $password,
                $firstName,
                $lastName,
                $name,
                $existingCustomer,
            ): User {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'role' => User::ROLE_CUSTOMER,
                    'is_active' => true,
                ]);

                if ($existingCustomer !== null) {
                    $this->linkGuestCustomerToUserAction->execute(
                        $existingCustomer,
                        $user,
                        $firstName,
                        $lastName,
                    );
                } else {
                    $this->createCustomerForUserAction->execute(
                        $user,
                        $email,
                        $firstName,
                        $lastName,
                    );
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (DetectUsersEmailUniqueViolation::matches($exception)) {
                throw new CustomerRegistrationException(
                    CustomerRegistrationException::EMAIL_TAKEN,
                    'This email is already registered.',
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }
}
