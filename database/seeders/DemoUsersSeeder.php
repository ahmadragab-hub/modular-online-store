<?php

namespace Database\Seeders;

use App\Domains\Identity\Actions\CreateAdminUserAction;
use App\Domains\Identity\Actions\RegisterCustomerAction;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo admin and customers (stable emails). Does not change passwords on re-run.
 */
class DemoUsersSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'demo-admin@example.local';

    public const ADMIN_PASSWORD = 'DemoAdminPass12!';

    public const CUSTOMER_PASSWORD = 'DemoCustPass12!';

    public const PRIMARY_CUSTOMER_EMAIL = 'demo.customer1@example.local';

    /**
     * @return list<array{email: string, first: string, last: string}>
     */
    public static function customerDefinitions(): array
    {
        return [
            ['email' => self::PRIMARY_CUSTOMER_EMAIL, 'first' => 'Layla', 'last' => 'Hassan'],
            ['email' => 'demo.customer2@example.local', 'first' => 'Omar', 'last' => 'Farid'],
            ['email' => 'demo.customer3@example.local', 'first' => 'Nadia', 'last' => 'Saleh'],
        ];
    }

    public function run(): void
    {
        if (! User::query()->where('email', self::ADMIN_EMAIL)->exists()) {
            app(CreateAdminUserAction::class)->execute(
                'Demo Admin',
                self::ADMIN_EMAIL,
                self::ADMIN_PASSWORD,
            );
        }

        $register = app(RegisterCustomerAction::class);

        foreach (self::customerDefinitions() as $row) {
            $email = strtolower($row['email']);

            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            $register->execute(
                $email,
                self::CUSTOMER_PASSWORD,
                $row['first'],
                $row['last'],
            );
        }

        $this->ensureDocumentedDemoAccountsRemainValid();
    }

    private function ensureDocumentedDemoAccountsRemainValid(): void
    {
        User::query()
            ->where('email', self::ADMIN_EMAIL)
            ->update([
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]);

        User::query()
            ->where('email', self::PRIMARY_CUSTOMER_EMAIL)
            ->update([
                'role' => User::ROLE_CUSTOMER,
                'is_active' => true,
            ]);
    }
}
