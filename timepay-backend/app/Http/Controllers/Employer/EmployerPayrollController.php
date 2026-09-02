<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        $company = auth()->user()->company;
        abort_unless($company, 404);

        $companyId = $company->id;
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $employees = User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->orderBy('name')
            ->get();

        $logsByUser = AttendanceLog::where('company_id', $companyId)
            ->where('status', 'verified')
            ->where('is_paid', false)
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
            'company' => $company,
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
            'payment_method' => 'required|in:manual_cash,manual_bank_deposit,manual_cheque',
        ]);

        $user->update($validated);

        return redirect()->route('employer.payroll')
            ->with('success', "Employee {$user->name}'s payroll settings updated successfully.");
    }

    /**
     * Mark an employee's pending payroll as manually settled for a period.
     */
    public function markAsPaid(Request $request, User $user): RedirectResponse
    {
        if (
            $user->company_id !== $request->user()->company_id
            || strtolower((string) $user->role) !== 'employee'
        ) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $periodStart = Carbon::parse($validated['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($validated['period_end'])->endOfDay();

        $settledAmount = DB::transaction(function () use ($user, $periodStart, $periodEnd): float {
            $logs = AttendanceLog::where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->where('status', 'verified')
                ->where('is_paid', false)
                ->whereBetween('timestamp', [$periodStart, $periodEnd])
                ->orderBy('timestamp')
                ->lockForUpdate()
                ->get();

            $hoursWorked = $this->calculateVerifiedHours($logs);
            $pendingPay = round($hoursWorked * (float) $user->hourly_rate, 2);

            if ($pendingPay <= 0 || $logs->isEmpty()) {
                return 0.0;
            }

            AttendanceLog::whereIn('id', $logs->pluck('id'))->update([
                'is_paid' => true,
            ]);

            return $pendingPay;
        });

        if ($settledAmount <= 0) {
            return redirect()->route('employer.payroll')
                ->with('success', 'No pending payroll found for this employee.');
        }

        return redirect()->route('employer.payroll')
            ->with('success', 'Payroll settled manually.');
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
