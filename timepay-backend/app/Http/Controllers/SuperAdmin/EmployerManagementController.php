<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployerManagementController extends Controller
{
    public function index(Request $request): View
    {
        $hasStatusColumn = Schema::hasColumn('users', 'status');

        $query = User::query()->where('role', User::ROLE_EMPLOYER)
            ->with('company');

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where(function ($q) use ($request) {
                $search = $request->string('search');

                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%{$search}%"));
            });
        }

        if ($hasStatusColumn && $request->filled('status')) {
            $query->where('status', $request->status);
        }

        $employers = $query->latest()->paginate(10)->withQueryString();

        return view('super-admin.employers.index', compact('employers', 'hasStatusColumn'));
    }

    public function create(): View
    {
        $hasStatusColumn = Schema::hasColumn('users', 'status');

        return view('super-admin.employers.create', compact('hasStatusColumn'));
    }

    public function store(Request $request): RedirectResponse
    {
        $hasStatusColumn = Schema::hasColumn('users', 'status');

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'pending'])],
        ]);

        DB::transaction(function () use ($validated, $hasStatusColumn): void {
            $company = Company::create([
                'name' => $validated['company_name'],
                'latitude' => 0,
                'longitude' => 0,
                'geofence_radius_meters' => 100,
                'pay_metric' => 'hourly',
            ]);

            $attributes = [
                'company_id' => $company->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_EMPLOYER,
            ];

            if ($hasStatusColumn) {
                $attributes['status'] = $validated['status'];
            }

            User::create($attributes);
        });

        return redirect()->route('super-admin.employers.index')->with('success', 'Employer created successfully!');
    }

    public function show(User $employer): View
    {
        abort_unless($employer->isEmployer(), 404);

        return view('super-admin.employers.show', compact('employer'));
    }

    public function edit(User $employer): View
    {
        abort_unless($employer->isEmployer(), 404);

        $hasStatusColumn = Schema::hasColumn('users', 'status');

        return view('super-admin.employers.edit', compact('employer', 'hasStatusColumn'));
    }

    public function update(Request $request, User $employer): RedirectResponse
    {
        abort_unless($employer->isEmployer(), 404);

        $hasStatusColumn = Schema::hasColumn('users', 'status');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employer)],
            'status' => [$hasStatusColumn ? 'required' : 'nullable', Rule::in(['pending', 'active', 'suspended', 'rejected'])],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if ($hasStatusColumn) {
            $attributes['status'] = $validated['status'];
        }

        $employer->update($attributes);

        return redirect()->route('super-admin.employers.index')->with('success', 'Employer updated.');
    }

    public function approve(User $employer): RedirectResponse
    {
        abort_unless($employer->isEmployer(), 404);
        abort_unless(Schema::hasColumn('users', 'status'), 400, 'The users.status column is required for approvals.');

        $employer->update(['status' => 'active']);

        return back()->with('success', 'Employer approved.');
    }

    public function suspend(User $employer): RedirectResponse
    {
        abort_unless($employer->isEmployer(), 404);
        abort_unless(Schema::hasColumn('users', 'status'), 400, 'The users.status column is required for suspensions.');

        $employer->update(['status' => 'suspended']);

        return back()->with('success', 'Employer suspended.');
    }

    public function destroy(User $employer): RedirectResponse
    {
        abort_unless($employer->isEmployer(), 404);

        $employer->delete();

        return redirect()->route('super-admin.employers.index')->with('success', 'Employer account deleted.');
    }
}
