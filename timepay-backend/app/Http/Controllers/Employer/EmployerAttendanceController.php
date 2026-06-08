<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
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

        return view('employer.attendance', [
            'attendanceLogs' => $attendanceLogs,
        ]);
    }
}
