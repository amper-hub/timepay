<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PayrollPayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $this->authenticatedEmployee($request);

        $logsByMonth = AttendanceLog::query()
            ->where('user_id', $employee->id)
            ->where('company_id', $employee->company_id)
            ->where('status', 'verified')
            ->where('timestamp', '>=', now()->subMonths(12)->startOfMonth())
            ->orderByDesc('timestamp')
            ->get()
            ->groupBy(fn (AttendanceLog $log): string => $log->timestamp->format('Y-m'));

        $payslips = $logsByMonth
            ->map(fn (Collection $logs, string $periodId): array => $this->buildPayslipData($employee, $periodId, $logs))
            ->values();

        return response()->json([
            'data' => $payslips,
        ]);
    }

    public function download(Request $request, string $id)
    {
        $employee = $this->authenticatedEmployee($request);
        $periodStart = $this->periodStart($id);
        $periodEnd = $periodStart->copy()->endOfMonth();

        $logs = AttendanceLog::query()
            ->where('user_id', $employee->id)
            ->where('company_id', $employee->company_id)
            ->where('status', 'verified')
            ->whereBetween('timestamp', [$periodStart, $periodEnd])
            ->orderBy('timestamp')
            ->get();

        abort_if($logs->isEmpty(), 404, 'No payroll records found for this pay period.');

        $payslip = $this->buildPayslipData($employee, $periodStart->format('Y-m'), $logs);
        $logoPath = resource_path('img/timepay-logo.png');

        $pdf = Pdf::loadView('pdfs.payslip', [
            'company' => $employee->company,
            'employee' => $employee,
            'payslip' => $payslip,
            'logoPath' => is_file($logoPath) ? $logoPath : null,
        ])->setPaper('a4');

        $safeName = Str::slug($employee->name);
        $filename = "timepay-payslip-{$safeName}-{$payslip['id']}.pdf";

        return $pdf->stream($filename);
    }

    private function authenticatedEmployee(Request $request): User
    {
        $user = $request->user()->loadMissing('company');

        abort_unless(strtoupper((string) $user->role) === User::ROLE_EMPLOYEE, 403, 'Payslips are only available to employees.');
        abort_unless($user->company, 404, 'Employee company record not found.');

        return $user;
    }

    private function periodStart(string $id): Carbon
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $id) === 1, 404);

        return Carbon::createFromFormat('Y-m-d', "{$id}-01")->startOfMonth();
    }

    /**
     * @param Collection<int, AttendanceLog> $logs
     */
    private function buildPayslipData(User $employee, string $periodId, Collection $logs): array
    {
        $periodStart = $this->periodStart($periodId);
        $periodEnd = $periodStart->copy()->endOfMonth();
        $regularHours = $this->calculateVerifiedHours($logs->sortBy('timestamp')->values());
        $hourlyRate = (float) $employee->hourly_rate;
        $grossPay = round($regularHours * $hourlyRate, 2);
        $deductions = 0.00;
        $netPay = round($grossPay - $deductions, 2);
        $company = $employee->company;

        return [
            'id' => $periodId,
            'pay_period' => "{$periodStart->format('M d, Y')} - {$periodEnd->format('M d, Y')}",
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'regular_hours' => $regularHours,
            'hourly_rate' => $hourlyRate,
            'gross_pay' => $grossPay,
            'deductions' => $deductions,
            'net_pay' => $netPay,
            'formatted_hourly_rate' => $company->formatMoney($hourlyRate),
            'formatted_gross_pay' => $company->formatMoney($grossPay),
            'formatted_deductions' => $company->formatMoney($deductions),
            'formatted_net_pay' => $company->formatMoney($netPay),
        ];
    }

    /**
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
