<?php

namespace Tests\Unit\Domains\Identity;

use App\Domains\Identity\Support\DetectUsersEmailUniqueViolation;
use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use Tests\TestCase;

class DetectUsersEmailUniqueViolationTest extends TestCase
{
    private function exceptionWithDriverMessage(string $driverMessage): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(
            'testing',
            'insert into users ...',
            [],
            new PDOException($driverMessage),
        );
    }

    public function test_matches_sqlite_users_email_message(): void
    {
        $exception = $this->exceptionWithDriverMessage('UNIQUE constraint failed: users.email');

        $this->assertTrue(DetectUsersEmailUniqueViolation::matches($exception));
    }

    public function test_matches_mysql_users_email_unique_index(): void
    {
        $exception = $this->exceptionWithDriverMessage(
            "Duplicate entry 'taken@example.test' for key 'users.users_email_unique'",
        );

        $this->assertTrue(DetectUsersEmailUniqueViolation::matches($exception));
    }

    public function test_does_not_match_unrelated_unique_constraint(): void
    {
        $exception = $this->exceptionWithDriverMessage('UNIQUE constraint failed: customers.email');

        $this->assertFalse(DetectUsersEmailUniqueViolation::matches($exception));
    }
}
