<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class BootstrapAdmin extends Command
{
    protected $signature = 'iam:bootstrap-admin
        {email : Corporate email address}
        {--name= : Administrator full name}';

    protected $description = 'Create the first verified Super Admin without exposing a password in shell history';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) ($this->option('name') ?: $this->ask('Full name')));

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with that email already exists. No changes were made.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ])->save();
        $user->assignRole('Super Admin');

        activity('iam')
            ->performedOn($user)
            ->event('bootstrap_admin_created')
            ->log('Initial Super Admin provisioned from the secure console command.');

        $this->info('Super Admin created successfully.');

        return self::SUCCESS;
    }
}
