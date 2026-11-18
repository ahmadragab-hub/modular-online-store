<?php

namespace App\Console\Commands;

use App\Domains\Identity\Actions\CreateAdminUserAction;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class CreateAdminUserCommand extends Command
{
    protected $signature = 'admin:create-user
                            {--name= : Display name}
                            {--email= : Login email}
                            {--password= : Password (min 12 characters)}';

    protected $description = 'Create an active admin user (ADMIN-001)';

    public function handle(CreateAdminUserAction $createAdminUserAction): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $email = (string) ($this->option('email') ?: $this->ask('Email'));
        $password = (string) ($this->option('password') ?: $this->secret('Password (min 12 chars)'));

        try {
            $user = $createAdminUserAction->execute($name, $email, $password);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->error(sprintf('%s: %s', $field, $message));
                }
            }

            return self::FAILURE;
        }

        $this->info(sprintf('Admin user created: %s (%s)', $user->name, $user->email));

        return self::SUCCESS;
    }
}
