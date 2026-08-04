<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'TimePay Capstone Corp'],
            [
                'latitude' => 14.59950000,
                'longitude' => 120.98420000,
                'geofence_radius_meters' => 100,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@timepay.com'],
            [
                'name' => 'Admin Employer',
                'password' => Hash::make('password123'),
                'company_id' => $company->id,
                'role' => User::ROLE_EMPLOYER,
                'hourly_rate' => 0.00,
                'payment_method' => 'manual_cash',
            ]
        );

        User::firstOrCreate(
            ['email' => 'superadmin@timepay.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'company_id' => null, // Super Admin does not belong to a specific company
                'role' => User::ROLE_SUPER_ADMIN,
                'hourly_rate' => 0.00,
                'payment_method' => 'manual_cash',
            ]
        );

        User::firstOrCreate(
            ['email' => 'john@example.com'],
            [
                'name' => 'John Doe',
                'password' => Hash::make('password123'),
                'company_id' => $company->id,
                'role' => User::ROLE_EMPLOYEE,
                'hourly_rate' => 15.00,
                'payment_method' => 'manual_cash',
            ]
        );
    }
}