<?php

namespace App\Domains\Identity\Support;

use Illuminate\Database\UniqueConstraintViolationException;

final class DetectUsersEmailUniqueViolation
{
    public static function matches(UniqueConstraintViolationException $exception): bool
    {
        $message = strtolower(self::driverMessage($exception));

        return str_contains($message, 'users.email')
            || str_contains($message, 'users_email_unique')
            || str_contains($message, 'users.users_email_unique');
    }

    private static function driverMessage(UniqueConstraintViolationException $exception): string
    {
        $previous = $exception->getPrevious();

        return $previous !== null ? $previous->getMessage() : $exception->getMessage();
    }
}
