<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EmployerDashboardController extends Controller
{
    /**
     * Display employer overview analytics.
     */
    public function index(): View
    {
        $company = auth()->user()->company;
        abort_unless($company, 404);

        $companyId = $company->id;
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $employees = User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->get();

        $totalEmployees = $employees->count();

        $logsByUser = AttendanceLog::where('company_id', $companyId)
            ->where('status', 'verified')
            ->whereBetween('timestamp', [$periodStart, $periodEnd])
            ->whereIn('user_id', $employees->pluck('id'))
            ->orderBy('timestamp')
            ->get()
            ->groupBy('user_id');

        $pendingPayroll = $employees->sum(function (User $employee) use ($logsByUser) {
            $hoursWorked = $this->calculateVerifiedHours($logsByUser->get($employee->id, collect()));

            return $hoursWorked * (float) $employee->hourly_rate;
        });

        $averageHourlyRate = $employees
            ->filter(fn (User $employee) => $employee->hourly_rate !== null)
            ->avg(fn (User $employee) => (float) $employee->hourly_rate) ?? 0;

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
            'company' => $company,
            'totalEmployees' => $totalEmployees,
            'averageHourlyRate' => $averageHourlyRate,
            'pendingPayroll' => $pendingPayroll,
            'verifiedPunchesToday' => $verifiedPunchesToday,
            'rejectedViolationsToday' => $rejectedViolationsToday,
            'recentPunches' => $recentPunches,
        ]);
    }

    /**
     * Pair verified clock-in and clock-out logs in chronological order.
     *
     * @param Collection<int, AttendanceLog> $logs
     */
    private function calculateVerifiedHours(Collection $logs): float
    {
        $openClockIn = null;
        $minutes = 0;

        foreach ($logs as $log) {
            if ($log->type === 'clock_in') {
                $openClockIn = $log->timestamp;
                continue;
            }

            if ($log->type === 'clock_out' && $openClockIn instanceof Carbon && $log->timestamp->greaterThan($openClockIn)) {
                $minutes += $openClockIn->diffInMinutes($log->timestamp);
                $openClockIn = null;
            }
        }

        return round($minutes / 60, 2);
    }
}
