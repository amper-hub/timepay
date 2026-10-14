<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DtrReportService
{
    /**
     * Build a DTR report for one employee. The report date is the local date of
     * clock-in; an unmatched clock-out uses its own local date.
     *
     * @return array<string, mixed>
     */
    public function forEmployee(User $employee, string $startDate, string $endDate): array
    {
        $start = $this->date($startDate);
        $end = $this->date($endDate);
        $logs = $this->logsFor($employee->company_id, collect([$employee->id]), $start, $end)
            ->where('user_id', $employee->id);

        return $this->makeReport($employee, $start, $end, $logs);
    }

    /**
     * Build reports for all employees in one company with a bounded query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCompany(int $companyId, string $startDate, string $endDate): array
    {
        $start = $this->date($startDate);
        $end = $this->date($endDate);
        $employees = User::query()
            ->where('company_id', $companyId)
            ->where('role', User::ROLE_EMPLOYEE)
            ->with('company')
            ->orderBy('name')
            ->get();

        if ($employees->isEmpty()) {
            return [];
        }

        $logsByEmployee = $this->logsFor($companyId, $employees->pluck('id'), $start, $end)
            ->groupBy('user_id');

        return $employees
            ->map(fn (User $employee): array => $this->makeReport(
                $employee,
                $start,
                $end,
                $logsByEmployee->get($employee->id, collect())
            ))
            ->all();
    }

    private function date(string $value): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $value, config('app.timezone'));
    }

    /**
     * Include the day before the range to recognize a shift crossing midnight
     * into the period, and one day after it to complete overnight shifts that
     * started on the last selected date.
     *
     * @param  Collection<int, int>  $employeeIds
     * @return Collection<int, AttendanceLog>
     */
    private function logsFor(int $companyId, Collection $employeeIds, Carbon $start, Carbon $end): Collection
    {
        return AttendanceLog::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $employeeIds)
            ->whereBetween('timestamp', [
                $start->copy()->subDay()->startOfDay(),
                $end->copy()->addDay()->endOfDay(),
            ])
            ->orderBy('timestamp')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array<string, mixed>
     */
    private function makeReport(User $employee, Carbon $start, Carbon $end, Collection $logs): array
    {
        $employee->loadMissing('company');
        $timezone = config('app.timezone');
        $intervals = $this->intervals($logs);
        $daily = collect($intervals)
            ->filter(fn (array $interval): bool => $interval['date'] >= $start->toDateString()
                && $interval['date'] <= $end->toDateString())
            ->groupBy('date')
            ->sortKeys();

        $minutes = 0;
        $records = $daily->map(function (Collection $dayIntervals, string $date) use (&$minutes, $employee): array {
            $validMinutes = (float) $dayIntervals->sum('minutes');
            $minutes += $validMinutes;
            $hasCompletedInterval = $dayIntervals->contains(fn (array $interval): bool => $interval['status'] === 'Completed');
            $statuses = $dayIntervals->pluck('status')->unique();
            $status = $statuses->contains('Invalidated')
                ? 'Invalidated'
                : ($statuses->contains('Flagged review')
                    ? 'Flagged review'
                    : ($statuses->contains('Incomplete') ? 'Incomplete' : 'Completed'));
            $company = $employee->company;

            return [
                'date' => $date,
                'scheduled_shift' => $this->scheduledShift($company, $date),
                'clock_in' => $dayIntervals->pluck('clock_in')->filter()->implode(' / '),
                'clock_out' => $dayIntervals->pluck('clock_out')->filter()->implode(' / '),
                'break_duration_minutes' => null,
                'net_hours' => $hasCompletedInterval ? round($validMinutes / 60, 2) : null,
                'status' => $status,
                'remarks' => $dayIntervals->pluck('remarks')->filter()->unique()->implode(' '),
            ];
        })->values()->all();

        $company = $employee->company;

        return [
            'company' => [
                'id' => $company?->id,
                'name' => $company?->name,
            ],
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_number' => null,
            ],
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'period' => $start->format('M d, Y').' - '.$end->format('M d, Y'),
            'timezone' => $timezone,
            'total_hours' => round($minutes / 60, 2),
            'generated_at' => Carbon::now($timezone)->format('M d, Y g:i A T'),
            'records' => $records,
            'limitations' => [
                'Break times are not stored in attendance logs, so no break deduction is applied.',
                'The employee number, assigned shifts, late minutes, and undertime are not stored per employee.',
            ],
        ];
    }

    /**
     * Pair only chronological verified clock-in/clock-out events. Every other
     * event remains visible as flagged, invalidated, or incomplete and adds no
     * worked time to the report total.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array<int, array{date: string, clock_in: ?string, clock_out: ?string, minutes: float, status: string, remarks: string}>
     */
    private function intervals(Collection $logs): array
    {
        // Collapse repeated copies of the same punch. If their review states
        // differ, keep the most restrictive state so a rejected punch cannot
        // become payable because of a duplicate verified row.
        $logs = $logs
            ->groupBy(fn (AttendanceLog $log): string => $log->type.'|'.$log->timestamp->format('Y-m-d H:i:s.u'))
            ->map(fn (Collection $duplicates): AttendanceLog => $duplicates
                ->sortByDesc(fn (AttendanceLog $log): int => match ($log->status) {
                    'rejected' => 3,
                    'flagged' => 2,
                    default => 1,
                })
                ->first())
            ->values();

        $intervals = [];
        $openClockIn = null;
        $timezone = config('app.timezone');

        foreach ($logs as $log) {
            if ($log->status !== 'verified') {
                if ($openClockIn instanceof AttendanceLog) {
                    $intervals[] = $this->incompleteClockIn($openClockIn, $timezone);
                    $openClockIn = null;
                }

                $localTime = $log->timestamp->copy()->timezone($timezone);
                $isRejected = $log->status === 'rejected';
                $intervals[] = [
                    'date' => $localTime->toDateString(),
                    'clock_in' => $log->type === 'clock_in' ? $localTime->format('g:i A') : null,
                    'clock_out' => $log->type === 'clock_out' ? $localTime->format('g:i A') : null,
                    'minutes' => 0,
                    'status' => $isRejected ? 'Invalidated' : 'Flagged review',
                    'remarks' => $isRejected ? 'Punch rejected or shift invalidated.' : 'Punch requires employer review.',
                ];

                continue;
            }

            if ($log->type === 'clock_in') {
                if ($openClockIn instanceof AttendanceLog) {
                    $intervals[] = $this->incompleteClockIn($openClockIn, $timezone);
                }

                $openClockIn = $log;

                continue;
            }

            if ($openClockIn instanceof AttendanceLog && $log->timestamp->greaterThan($openClockIn->timestamp)) {
                $clockIn = $openClockIn->timestamp->copy()->timezone($timezone);
                $clockOut = $log->timestamp->copy()->timezone($timezone);
                $intervals[] = [
                    'date' => $clockIn->toDateString(),
                    'clock_in' => $clockIn->format('g:i A'),
                    'clock_out' => $clockOut->format('g:i A'),
                    'minutes' => $clockIn->diffInMinutes($clockOut),
                    'status' => 'Completed',
                    'remarks' => '',
                ];
                $openClockIn = null;

                continue;
            }

            $localTime = $log->timestamp->copy()->timezone($timezone);
            $intervals[] = [
                'date' => $localTime->toDateString(),
                'clock_in' => null,
                'clock_out' => $localTime->format('g:i A'),
                'minutes' => 0,
                'status' => 'Incomplete',
                'remarks' => 'No matching verified clock-in.',
            ];
        }

        if ($openClockIn instanceof AttendanceLog) {
            $intervals[] = $this->incompleteClockIn($openClockIn, $timezone);
        }

        return $intervals;
    }

    /** @return array{date: string, clock_in: string, clock_out: null, minutes: float, status: string, remarks: string} */
    private function incompleteClockIn(AttendanceLog $log, string $timezone): array
    {
        $localTime = $log->timestamp->copy()->timezone($timezone);

        return [
            'date' => $localTime->toDateString(),
            'clock_in' => $localTime->format('g:i A'),
            'clock_out' => null,
            'minutes' => 0,
            'status' => 'Incomplete',
            'remarks' => 'No matching verified clock-out.',
        ];
    }

    private function scheduledShift(?Company $company, string $date): ?string
    {
        if (! $company?->work_start_time || ! $company?->work_end_time) {
            return null;
        }

        $workingDays = $company->working_days;
        if (is_array($workingDays) && ! in_array(Carbon::parse($date)->format('l'), $workingDays, true)) {
            return null;
        }

        return Carbon::parse($company->work_start_time)->format('g:i A')
            .' - '
            .Carbon::parse($company->work_end_time)->format('g:i A');
    }
}
