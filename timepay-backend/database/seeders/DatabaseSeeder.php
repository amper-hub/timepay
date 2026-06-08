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
        // 1. Create the default Company with your Phase 2 Geofence settings
        $company = Company::create([
            'name' => 'TimePay Capstone Corp',
            'latitude' => 9.79150000,
            'longitude' => 125.49160000,
            'geofence_radius_meters' => 100,
        ]);

        // 2. Create your Master Admin/Employer User (For the Web Portal)
        User::create([
            'name' => 'Admin Employer',
            'email' => 'admin@timepay.com',
            'password' => Hash::make('password123'), 
            'company_id' => $company->id,
            'hourly_rate' => 0.00,
            'payment_method' => 'manual_cash',
        ]);

        // 3. Create your Test Employee User (For the Mobile App)
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'), // Mobile app login password
            'company_id' => $company->id, // Linked to the exact same company!
            'hourly_rate' => 15.00, // Giving John a base rate for Phase 4 Payroll
            'payment_method' => 'manual_cash',
        ]);
    }
}