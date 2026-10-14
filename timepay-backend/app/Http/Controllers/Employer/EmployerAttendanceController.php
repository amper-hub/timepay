<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\View\View;

class EmployerAttendanceController extends Controller
{
    /**
     * Display the attendance logs for the company.
     */
    public function index(): View
    {
        $companyId = auth()->user()->company_id;

        $attendanceLogs = AttendanceLog::where('company_id', $companyId)
            ->with('user')
            ->latest('timestamp')
            ->paginate(15);
        $employees = User::query()
            ->where('company_id', $companyId)
            ->where('role', User::ROLE_EMPLOYEE)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('employer.attendance', [
            'attendanceLogs' => $attendanceLogs,
            'employees' => $employees,
        ]);
    }
}
