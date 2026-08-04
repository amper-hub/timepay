<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

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

        $user = User::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_EMPLOYER,
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->put('user_role', $user->role);

        if ($user->isEmployer()) {
            return redirect(route('employer.dashboard', absolute: false));
        }

        return redirect(route('dashboard', absolute: false));
    }
}
