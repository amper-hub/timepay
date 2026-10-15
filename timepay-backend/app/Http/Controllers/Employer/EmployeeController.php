<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\PayrollCompensationHistory;
use App\Models\PayrollEntry;
use App\Models\PayrollProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Create a new employee with a temporary password.
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor && in_array(strtoupper((string) $actor->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403);
        abort_unless($actor->company_id, 403, 'A company must be assigned to this account.');
        $companyId = (int) $actor->company_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'hourly_rate' => 'required_without_all:daily_rate,monthly_rate|nullable|numeric|min:0|max:999999.99',
            'salary_type' => 'sometimes|in:hourly,daily,monthly',
            'daily_rate' => 'required_if:salary_type,daily|nullable|numeric|min:0|max:99999999.99',
            'monthly_rate' => 'required_if:salary_type,monthly|nullable|numeric|min:0|max:99999999.99',
            'pay_frequency' => 'sometimes|in:hourly,daily,weekly,semimonthly,monthly',
            'payment_method' => 'nullable|in:manual_cash,manual_bank_deposit,manual_cheque',
        ]);

        $temporaryPassword = Str::password(8);
        $salaryType = $validated['salary_type'] ?? 'hourly';
        $rate = match ($salaryType) {
            'daily' => $validated['daily_rate'],
            'monthly' => $validated['monthly_rate'],
            default => $validated['hourly_rate'],
        };
        $employee = DB::transaction(function () use ($validated, $companyId, $actor, $temporaryPassword, $salaryType, $rate): User {
            $employee = User::create([
                'company_id' => $companyId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $temporaryPassword,
                'role' => 'employee',
                'hourly_rate' => $validated['hourly_rate'] ?? 0,
                'daily_rate' => $validated['daily_rate'] ?? 0,
                'payment_method' => $validated['payment_method'] ?? 'manual_cash',
                'requires_password_change' => true,
            ]);

            PayrollProfile::query()->create([
                'company_id' => $companyId,
                'user_id' => $employee->id,
                'salary_type' => $salaryType,
                'pay_frequency' => $validated['pay_frequency'] ?? 'monthly',
            ]);
            PayrollCompensationHistory::query()->create([
                'company_id' => $companyId,
                'user_id' => $employee->id,
                'basis' => $salaryType,
                'rate' => $rate,
                'effective_from' => Carbon::now(config('app.timezone'))->toDateString(),
                'changed_by' => $actor->id,
            ]);

            return $employee;
        });

        return response()->json([
            'message' => 'Employee created successfully.',
            'temporary_password' => $temporaryPassword,
            'user' => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => $employee->role,
                'salary_type' => $salaryType,
                'rate' => (string) $rate,
                'pay_frequency' => $validated['pay_frequency'] ?? 'monthly',
                'hourly_rate' => $employee->hourly_rate,
                'payment_method' => $employee->payment_method,
                'requires_password_change' => $employee->requires_password_change,
            ],
        ], 201);
    }

    /**
     * Remove an employee from the authenticated employer's company.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user() && in_array(strtoupper((string) $request->user()->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403);
        if (
            $user->company_id !== $request->user()->company_id
            || strtolower((string) $user->role) !== 'employee'
        ) {
            abort(403, 'Unauthorized');
        }

        if (PayrollEntry::query()->where('company_id', $user->company_id)->where('user_id', $user->id)->exists()) {
            abort(409, 'This employee has payroll history and cannot be deleted. Deactivate the account to preserve payroll records.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'Employee removed successfully.');
    }
}
