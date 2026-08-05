<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class EmployerRegistrationController extends Controller
{
    /**
     * Register a new tenant company and its first employer account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'employer_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $company = Company::create([
                'name' => $validated['company_name'],
                'latitude' => null,
                'longitude' => null,
                'geofence_radius_meters' => config('timepay.default_geofence_radius', 100),
                'pay_metric' => 'hourly',
            ]);

            return User::create([
                'company_id' => $company->id,
                'name' => $validated['employer_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_EMPLOYER,
                'status' => 'active',
            ]);
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->put('user_role', $user->role);

        return redirect()->route('employer.dashboard')->with('success', 'Welcome to TimePay. Finish your geofence setup to start tracking attendance.');
    }
}
