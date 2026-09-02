<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Create a new employee with a temporary password.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'hourly_rate' => 'required|numeric|min:0|max:999999.99',
            'payment_method' => 'nullable|in:manual_cash,digital_payout',
        ]);

        $temporaryPassword = Str::password(8);

        $employee = User::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $temporaryPassword,
            'role' => 'employee',
            'hourly_rate' => $validated['hourly_rate'],
            'payment_method' => $validated['payment_method'] ?? 'manual_cash',
            'requires_password_change' => true,
        ]);

        return response()->json([
            'message' => 'Employee created successfully.',
            'temporary_password' => $temporaryPassword,
            'user' => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => $employee->role,
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
        if (
            $user->company_id !== $request->user()->company_id
            || strtolower((string) $user->role) !== 'employee'
        ) {
            abort(403, 'Unauthorized');
        }

        $user->delete();

        return redirect()->back()->with('success', 'Employee removed successfully.');
    }
}
