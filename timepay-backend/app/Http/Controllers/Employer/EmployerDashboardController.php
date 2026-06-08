<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\View\View;

class EmployerDashboardController extends Controller
{
    /**
     * Display employer overview analytics.
     */
    public function index(): View
    {
        $companyId = auth()->user()->company_id;

        $totalEmployees = User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->count();

        $verifiedPunchesToday = AttendanceLog::where('company_id', $companyId)
            ->where('status', 'verified')
            ->whereDate('timestamp', today())
            ->count();

        $rejectedViolationsToday = AttendanceLog::where('company_id', $companyId)
            ->where('status', 'rejected')
            ->whereDate('timestamp', today())
            ->count();

        $recentPunches = AttendanceLog::where('company_id', $companyId)
            ->with('user')
            ->latest('timestamp')
            ->limit(5)
            ->get();

        return view('employer.dashboard', [
            'totalEmployees' => $totalEmployees,
            'verifiedPunchesToday' => $verifiedPunchesToday,
            'rejectedViolationsToday' => $rejectedViolationsToday,
            'recentPunches' => $recentPunches,
        ]);
    }
}
