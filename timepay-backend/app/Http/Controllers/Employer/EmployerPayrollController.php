<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EmployerPayrollController extends Controller
{
    /**
     * Display the payroll management dashboard.
     */
    public function index(): View
    {
        $companyId = auth()->user()->company_id;
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $employees = User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->orderBy('name')
            ->get();

        $logsByUser = AttendanceLog::where('company_id', $companyId)
            ->where('status', 'verified')
            ->whereBetween('timestamp', [$periodStart, $periodEnd])
            ->whereIn('user_id', $employees->pluck('id'))
            ->orderBy('timestamp')
            ->get()
            ->groupBy('user_id');

        $employeePayroll = $employees->map(function (User $employee) use ($logsByUser) {
            $hoursWorked = $this->calculateVerifiedHours($logsByUser->get($employee->id, collect()));
            $hourlyRate = (float) $employee->hourly_rate;

            $pendingPay = round($hoursWorked * $hourlyRate, 2);

            return [
                'employee' => $employee,
                'hours_worked' => $hoursWorked,
                'hourly_rate' => $hourlyRate,
                'payment_method' => $employee->payment_method,
                'pending_pay' => $pendingPay,
            ];
        });

        return view('employer.payroll', [
            'employeePayroll' => $employeePayroll,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ]);
    }

    /**
     * Update an employee's hourly rate and payment method.
     */
    public function updateEmployeeRate(Request $request, User $user): RedirectResponse
    {
        if ($user->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'hourly_rate' => 'required|numeric|min:0|max:999999.99',
            'payment_method' => 'required|in:manual_cash,digital_payout',
        ]);

        $user->update($validated);

        return redirect()->route('employer.payroll')
            ->with('success', "Employee {$user->name}'s payroll settings updated successfully.");
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
