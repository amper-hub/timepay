<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PayrollController extends Controller
{
    /**
     * Return the authenticated employee's accumulated unpaid earnings.
     */
    public function pendingPay(Request $request): JsonResponse
    {
        $employee = $request->user()->loadMissing('company');

        abort_unless(strtoupper((string) $employee->role) === User::ROLE_EMPLOYEE, 403, 'Pending pay is only available to employees.');
        abort_unless($employee->company, 404, 'Employee company record not found.');

        $logs = AttendanceLog::query()
            ->where('user_id', $employee->id)
            ->where('company_id', $employee->company_id)
            ->where('status', 'verified')
            ->where('is_paid', false)
            ->orderBy('timestamp')
            ->get();

        $unpaidHours = $this->calculateVerifiedHours($logs);
        $hourlyRate = (float) $employee->hourly_rate;
        $pendingAmount = round($unpaidHours * $hourlyRate, 2);

        return response()->json([
            'pending_amount' => $pendingAmount,
            'currency_symbol' => $employee->company->currencySymbol(),
            'unpaid_hours' => $unpaidHours,
        ]);
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
