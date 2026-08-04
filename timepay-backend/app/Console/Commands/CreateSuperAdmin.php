<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {email?} {--name=} {--password=}';

    protected $description = 'Create the initial super admin account for TimePay.';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Super admin email');
        $name = $this->option('name') ?? $this->ask('Super admin name');
        $password = $this->option('password') ?? $this->secret('Super admin password');

        if (! $email || ! $name || ! $password) {
            $this->error('Email, name, and password are all required.');
            return self::FAILURE;
        }

        $company = Company::query()->first();

        if (! $company) {
            $company = Company::create([
                'name' => 'Default Company',
                'latitude' => 0,
                'longitude' => 0,
                'geofence_radius_meters' => 100,
                'pay_metric' => 'hourly',
            ]);
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'company_id' => $company->id,
                'name' => $name,
                'password' => bcrypt($password),
                'role' => User::ROLE_SUPER_ADMIN,
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->info("Super admin created successfully: {$user->email}");
        } else {
            $this->info("Super admin already exists: {$user->email}");
        }

        return self::SUCCESS;
    }
}
