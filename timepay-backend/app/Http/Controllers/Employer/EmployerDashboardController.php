<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\View\View;

class EmployerDashboardController extends Controller
{
    /**
     * Display company-scoped employer overview information.
     */
    public function index(): View
    {
        $company = auth()->user()->company;
        abort_unless($company, 404);

        $companyId = (int) $company->id;
        $employeeIds = User::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(role) = ?', ['employee'])
            ->where('status', 'active')
            ->pluck('id');

        $verifiedPunchesToday = AttendanceLog::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $employeeIds)
            ->where('status', 'verified')
            ->whereDate('timestamp', today())
            ->count();

        $rejectedPunchesToday = AttendanceLog::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $employeeIds)
            ->whereIn('status', ['rejected', 'flagged'])
            ->whereDate('timestamp', today())
            ->count();

        $pendingLeaveRequests = LeaveRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();

        $latestPayroll = PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->whereNotNull('calculated_at')
            ->orderByDesc('period_start')
            ->first();

        $recentPunches = AttendanceLog::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $employeeIds)
            ->with('user')
            ->latest('timestamp')
            ->limit(5)
            ->get();

        return view('employer.dashboard', [
            'company' => $company,
            'activeEmployeeCount' => $employeeIds->count(),
            'verifiedPunchesToday' => $verifiedPunchesToday,
            'attentionPunchesToday' => $rejectedPunchesToday,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'latestPayroll' => $latestPayroll,
            'recentPunches' => $recentPunches,
        ]);
    }
}
