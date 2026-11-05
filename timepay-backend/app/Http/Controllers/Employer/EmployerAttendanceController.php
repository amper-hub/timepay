<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployerAttendanceController extends Controller
{
    /**
     * Display the attendance logs for the company.
     */
    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->whereRaw('LOWER(role) = ?', ['employee']))],
            'status' => ['nullable', Rule::in(['verified', 'flagged', 'rejected'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        if (! empty($validated['from']) && ! empty($validated['to']) && $validated['to'] < $validated['from']) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        $attendanceLogs = AttendanceLog::query()->where('company_id', $companyId)
            ->when($request->filled('employee_id'), fn ($query) => $query->where('user_id', $validated['employee_id']))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $validated['status']))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('timestamp', '>=', $validated['from']))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('timestamp', '<=', $validated['to']))
            ->with('user')
            ->latest('timestamp')
            ->paginate(15)
            ->withQueryString();
        $employees = User::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(role) = ?', ['employee'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('employer.attendance', [
            'attendanceLogs' => $attendanceLogs,
            'employees' => $employees,
            'filters' => [
                'employee_id' => $validated['employee_id'] ?? '',
                'status' => $validated['status'] ?? '',
                'from' => $validated['from'] ?? '',
                'to' => $validated['to'] ?? '',
            ],
        ]);
    }
}
