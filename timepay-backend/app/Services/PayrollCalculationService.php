<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\PayrollAudit;
use App\Models\PayrollAttendanceAllocation;
use App\Models\PayrollCompensationHistory;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\PayrollProfileItem;
use App\Models\PayrollStatutoryRule;
use App\Models\User;
use App\Support\PayrollReviewFlags;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollCalculationService
{
    private array $ruleCache = [];

    public function calculate(PayrollPeriod $period, int $actorId): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $actorId): PayrollPeriod {
            $lockedPeriod = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedPeriod->status, ['draft', 'pending_review'], true)) {
                throw ValidationException::withMessages(['status' => 'Only draft or pending-review payroll can be recalculated.']);
            }

            $this->assertNoFinalizedOverlap($lockedPeriod);

            // Pivot rows are derived from this period's replaceable entries.
            // MyISAM does not run the pivot FK cascade, and legacy rows may
            // point at an entry that no longer exists. Capture the period's
            // allocated source logs before deleting allocations so only
            // dangling links proven to belong to this period are removed.
            $periodEntryIds = $lockedPeriod->entries()->where('company_id', $lockedPeriod->company_id)
                ->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $periodAllocationLogIds = PayrollAttendanceAllocation::query()
                ->where('company_id', $lockedPeriod->company_id)
                ->where('payroll_period_id', $lockedPeriod->id)
                ->get(['clock_in_log_id', 'clock_out_log_id'])
                ->flatMap(fn (PayrollAttendanceAllocation $allocation): array => [
                    (int) $allocation->clock_in_log_id, (int) $allocation->clock_out_log_id,
                ])->unique()->values()->all();

            if ($periodEntryIds !== []) {
                DB::table('payroll_entry_attendance_logs')->whereIn('payroll_entry_id', $periodEntryIds)->delete();
            }
            if ($periodAllocationLogIds !== []) {
                $orphanedPeriodLinks = DB::table('payroll_entry_attendance_logs as links')
                    ->leftJoin('payroll_entries as entries', 'entries.id', '=', 'links.payroll_entry_id')
                    ->whereNull('entries.id')->whereIn('links.attendance_log_id', $periodAllocationLogIds)
                    ->get(['links.payroll_entry_id', 'links.attendance_log_id']);
                foreach ($orphanedPeriodLinks as $link) {
                    DB::table('payroll_entry_attendance_logs')
                        ->where('payroll_entry_id', $link->payroll_entry_id)
                        ->where('attendance_log_id', $link->attendance_log_id)
                        ->delete();
                }
            }

            PayrollAttendanceAllocation::query()
                ->where('company_id', $lockedPeriod->company_id)
                ->where('payroll_period_id', $lockedPeriod->id)
                ->delete();
            $lockedPeriod->entries()->delete();

            if ($lockedPeriod->frequency === 'yearly') {
                return $this->calculateThirteenthMonth($lockedPeriod, $actorId);
            }

            $employees = User::query()
                ->where('company_id', $lockedPeriod->company_id)
                ->whereRaw('LOWER(role) = ?', ['employee'])
                ->where('status', 'active')
                ->whereHas('payrollProfile', fn ($query) => $query->where('payroll_eligible', true)->where('pay_frequency', $lockedPeriod->frequency))
                ->with(['payrollProfile', 'company'])
                ->orderBy('id')
                ->get();

            $periodStart = Carbon::parse($lockedPeriod->period_start, config('app.timezone'))->startOfDay();
            // Payroll date ranges are inclusive local dates represented internally as
            // a half-open interval [start midnight, day-after-end midnight).
            $periodEndExclusive = Carbon::parse($lockedPeriod->period_end, config('app.timezone'))->startOfDay()->addDay();
            foreach ($employees as $employee) {
                $profile = $employee->payrollProfile;
                if ($profile->sss_enabled) $this->rule('sss', $lockedPeriod->period_end);
                if ($profile->philhealth_enabled) $this->rule('philhealth', $lockedPeriod->period_end);
                if ($profile->pagibig_enabled) $this->rule('pagibig', $lockedPeriod->period_end);
                if ($profile->withholding_tax_enabled) $this->rule('bir_compensation_tax', $lockedPeriod->period_end);
            }
            $logsByEmployee = AttendanceLog::query()
                ->where('company_id', $lockedPeriod->company_id)
                ->whereIn('user_id', $employees->pluck('id'))
                ->where('timestamp', '>=', $periodStart->copy()->subDay())
                ->where('timestamp', '<', $periodEndExclusive->copy()->addDay())
                ->orderBy('timestamp')->orderBy('id')->lockForUpdate()->get()->groupBy('user_id');

            $sourceLogIds = $logsByEmployee->flatten()->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $allocationHistory = $sourceLogIds === [] ? collect() : PayrollAttendanceAllocation::query()
                ->where('company_id', $lockedPeriod->company_id)
                ->where(fn ($query) => $query->whereIn('clock_in_log_id', $sourceLogIds)->orWhereIn('clock_out_log_id', $sourceLogIds))
                ->lockForUpdate()->get()
                ->groupBy(fn (PayrollAttendanceAllocation $allocation): string => $allocation->clock_in_log_id.'|'.$allocation->clock_out_log_id);

            $alreadyUsedLogIds = DB::table('payroll_entry_attendance_logs as sources')
                ->join('payroll_entries as entries', 'entries.id', '=', 'sources.payroll_entry_id')
                ->join('payroll_periods as periods', 'periods.id', '=', 'entries.payroll_period_id')
                ->where('entries.company_id', $lockedPeriod->company_id)
                ->where('periods.id', '!=', $lockedPeriod->id)
                ->pluck('sources.attendance_log_id')->map(fn ($id): int => (int) $id)->all();

            $totals = ['gross' => 0, 'deductions' => 0, 'employer' => 0, 'net' => 0];
            foreach ($employees as $employee) {
                /** @var PayrollProfile $profile */
                $profile = $employee->payrollProfile;
                $sourceLogs = $logsByEmployee->get($employee->id, collect());
                $flags = [];
                if ($sourceLogs->groupBy(fn (AttendanceLog $log): string => $log->type.'|'.$log->timestamp->format('Y-m-d H:i:s.u'))
                    ->contains(fn (Collection $duplicates): bool => $duplicates->count() > 1)) {
                    $flags[] = 'duplicate_attendance_requires_review';
                }
                $intervals = $this->verifiedIntervals($sourceLogs);
                if (collect($intervals)->contains(fn (array $interval): bool => (bool) ($interval['incomplete'] ?? false)
                    && $interval['clock_in']->greaterThanOrEqualTo($periodStart) && $interval['clock_in']->lt($periodEndExclusive))) {
                    $flags[] = 'incomplete_attendance_requires_review';
                }
                if ($sourceLogs->contains(fn (AttendanceLog $log): bool => $log->status === 'flagged' || $log->status === 'rejected' || $log->is_suspicious)) {
                    $flags[] = 'invalid_attendance_requires_review';
                }
                $previousEnd = null;
                foreach ($intervals as $interval) {
                    if ($interval['incomplete'] ?? false) {
                        continue;
                    }
                    if ($previousEnd && $interval['clock_in']->lt($previousEnd)) {
                        $flags[] = 'overlapping_attendance_requires_review';
                    }
                    if (! $previousEnd || $interval['clock_out']->gt($previousEnd)) {
                        $previousEnd = $interval['clock_out'];
                    }
                }
                if (strtolower((string) $employee->status) !== 'active') {
                    $flags[] = 'employee_status_requires_review';
                }
                $intervalsForPeriod = [];

                foreach ($intervals as $interval) {
                    if ($interval['incomplete'] ?? false) {
                        continue;
                    }
                    // Payroll periods are inclusive local calendar dates. Allocate only
                    // the part of a completed shift that overlaps this period.
                    $overlapStart = $interval['clock_in']->greaterThan($periodStart) ? $interval['clock_in']->copy() : $periodStart->copy();
                    $overlapEnd = $interval['clock_out']->lessThan($periodEndExclusive) ? $interval['clock_out']->copy() : $periodEndExclusive->copy();
                    if (! $overlapStart->lt($overlapEnd)) {
                        continue;
                    }

                    $shiftKey = $interval['clock_in_id'].'|'.$interval['clock_out_id'];
                    $history = $allocationHistory->get($shiftKey, collect());
                    $historyIsValid = $history->every(fn (PayrollAttendanceAllocation $allocation): bool =>
                        $this->allocationMatchesSourceInterval($allocation, $interval)
                    );
                    $hasPriorPunchOwnership = in_array($interval['clock_in_id'], $alreadyUsedLogIds, true)
                        || in_array($interval['clock_out_id'], $alreadyUsedLogIds, true);
                    if (! $historyIsValid || (($hasPriorPunchOwnership || $interval['clock_in_paid'] || $interval['clock_out_paid']) && $history->isEmpty())) {
                        $flags[] = 'attendance_allocation_history_requires_review';
                        continue;
                    }

                    $overlapsExistingAllocation = $history->contains(fn (PayrollAttendanceAllocation $allocation): bool =>
                        $allocation->segment_start_at->lt($overlapEnd) && $allocation->segment_end_at->gt($overlapStart)
                    );
                    if ($overlapsExistingAllocation) {
                        $flags[] = 'attendance_allocation_overlap_requires_review';
                        continue;
                    }
                    $intervalsForPeriod[] = [
                        ...$interval,
                        'clock_in' => $overlapStart,
                        'clock_out' => $overlapEnd,
                        'source_clock_in' => $interval['clock_in'],
                        'source_clock_out' => $interval['clock_out'],
                        'allocated_elapsed_seconds' => $overlapStart->diffInSeconds($overlapEnd),
                        'allocation_key' => $this->allocationKey(
                            (int) $lockedPeriod->company_id,
                            (int) $employee->id,
                            (int) $interval['clock_in_id'],
                            (int) $interval['clock_out_id'],
                            $overlapStart,
                            $overlapEnd,
                        ),
                    ];
                }

                if ($this->hasIncompleteVerifiedClockIn($sourceLogs, $periodStart, $periodEndExclusive)) {
                    $flags[] = 'incomplete_attendance_requires_review';
                }
                if ($this->hasInvalidAttendanceTimestamps($sourceLogs)) {
                    $flags[] = 'invalid_attendance_requires_review';
                }

                $work = $this->calculateBaseEarnings($employee, $profile, $lockedPeriod, $intervalsForPeriod, $flags);
                $lineData = $work['lines'];
                $grossCents = $work['gross_cents'];
                $taxableCents = $work['taxable_cents'];

                foreach ($this->activeProfileItems((int) $employee->id, $lockedPeriod) as $item) {
                    $amount = $this->toCents($item->amount);
                    if ($item->category === 'earning') {
                        $lineData[] = $this->line('earning', $item->code, $item->label, $amount, null, null, $item->taxable, [
                            'source' => 'recurring_profile_item', 'profile_item_id' => (int) $item->id,
                            'effective_from' => $item->effective_from->toDateString(), 'effective_to' => $item->effective_to?->toDateString(),
                        ]);
                        $grossCents += $amount;
                        if ($item->taxable) {
                            $taxableCents += $amount;
                        }
                    } else {
                        $lineData[] = $this->line('employee_deduction', $item->code, $item->label, $amount, null, null, false, [
                            'source' => 'recurring_profile_item',
                            'profile_item_id' => (int) $item->id,
                            'effective_from' => $item->effective_from->toDateString(),
                            'effective_to' => $item->effective_to?->toDateString(),
                            'authorization_reference' => $item->authorization_reference,
                        ]);
                    }
                }

                $statutory = $this->statutoryForPeriod($profile, $work['monthly_bases'], $lockedPeriod);
                $statutory['rules']['verification'] = [
                    'sss' => 'schedule source verified for effective 2025-01-01; monthly base and MPF allocation require payroll review',
                    'philhealth' => 'only CY 2025 advisory verified; 2026 schedule applicability is unresolved',
                    'pagibig' => 'current applicable 2026 circular/effective schedule not independently verified',
                    'bir_compensation_tax' => 'employee tax classifications and exclusions are incomplete',
                ];
                if ($profile->sss_enabled) $statutory['rules']['sss_verification'] = $statutory['rules']['verification']['sss'];
                if ($profile->philhealth_enabled) $statutory['rules']['philhealth_verification'] = $statutory['rules']['verification']['philhealth'];
                if ($profile->pagibig_enabled) $statutory['rules']['pagibig_verification'] = $statutory['rules']['verification']['pagibig'];
                if ($profile->sss_enabled) {
                    $rule = $this->rule('sss', $lockedPeriod->period_end);
                    $statutory['rules']['sss'] ??= ['name' => $rule['name'], 'effective_from' => $rule['effective_from'], 'configuration' => $rule['configuration']];
                }
                if ($profile->philhealth_enabled) {
                    $rule = $this->rule('philhealth', $lockedPeriod->period_end);
                    $statutory['rules']['philhealth'] ??= ['name' => $rule['name'], 'effective_from' => $rule['effective_from'], 'configuration' => $rule['configuration']];
                }
                if ($profile->pagibig_enabled) {
                    $rule = $this->rule('pagibig', $lockedPeriod->period_end);
                    $statutory['rules']['pagibig'] ??= ['name' => $rule['name'], 'effective_from' => $rule['effective_from'], 'configuration' => $rule['configuration']];
                }
                if ($profile->sss_enabled || $profile->philhealth_enabled || $profile->pagibig_enabled) {
                    $flags[] = 'unsupported_payroll_rule_requires_review';
                }
                if ($profile->withholding_tax_enabled && ! $this->taxInputsAreComplete($employee)) {
                    $flags[] = 'missing_statutory_input_requires_review';
                }
                foreach ($statutory['lines'] as $line) {
                    $lineData[] = $line;
                }

                $employeeDeductionsCents = $statutory['employee_cents'];
                foreach ($lineData as $line) {
                    if ($line['category'] === 'employee_deduction' && ($line['details']['source'] ?? null) === 'recurring_profile_item') {
                        $employeeDeductionsCents += $line['amount_cents'];
                    }
                }

                if ($profile->withholding_tax_enabled) {
                    $taxRule = $this->rule('bir_compensation_tax', $lockedPeriod->period_end);
                    $statutory['rules']['bir_compensation_tax'] = [
                        'name' => $taxRule['name'], 'effective_from' => $taxRule['effective_from'],
                        'configuration' => $taxRule['configuration'],
                    ];
                    $statutory['rules']['bir_compensation_tax_verification'] = 'Annex E schedule configured; employee tax classifications and exclusions are incomplete';
                    $taxableAfterStatutory = max(0, $taxableCents - $statutory['employee_statutory_cents']);
                    $withholdingCents = $this->withholdingTax($employee, $lockedPeriod, $taxableAfterStatutory, $lineData);
                    if ($withholdingCents !== 0) {
                        $lineData[] = $this->line('employee_deduction', 'bir_withholding_tax', 'Withholding tax', $withholdingCents, null, null, false, ['rule' => 'BIR Annex E / annualized year-end reconciliation']);
                        $employeeDeductionsCents += $withholdingCents;
                    }
                }

                $netCents = $grossCents - $employeeDeductionsCents;
                $employerCents = $statutory['employer_cents'];
                if ($netCents < 0) {
                    $flags[] = 'negative_net_pay_requires_review';
                }
                if ($profile->salary_type === 'monthly'
                    && (! $lockedPeriod->period_start->isSameDay($lockedPeriod->period_start->copy()->startOfMonth())
                        || ! $lockedPeriod->period_end->isSameDay($lockedPeriod->period_end->copy()->endOfMonth()))) {
                    $flags[] = 'monthly_salary_prorated_by_calendar_day';
                }
                if ($intervalsForPeriod !== [] && ! $profile->overtime_eligible) {
                    $flags[] = 'overtime_eligibility_disabled';
                }
                if ($this->periodHasApprovedLeave((int) $employee->id, $lockedPeriod)) {
                    $flags[] = 'approved_leave_policy_requires_review';
                }
                $flags = array_values(array_unique($flags));

                $entry = PayrollEntry::query()->create([
                    'company_id' => $lockedPeriod->company_id,
                    'payroll_period_id' => $lockedPeriod->id,
                    'user_id' => $employee->id,
                    'worked_minutes' => $work['worked_minutes'],
                    'payable_days' => $work['payable_days'],
                    'gross_pay' => $this->fromCents($grossCents),
                    'employee_deductions' => $this->fromCents($employeeDeductionsCents),
                    'employer_contributions' => $this->fromCents($employerCents),
                    'net_pay' => $this->fromCents($netCents),
                    'employer_cost' => $this->fromCents($grossCents + $employerCents),
                    'calculation_snapshot' => [
                        'salary_type' => $profile->salary_type,
                        'frequency' => $lockedPeriod->frequency,
                        'period_start' => $lockedPeriod->period_start->toDateString(),
                        'period_end' => $lockedPeriod->period_end->toDateString(),
                        'company_timezone' => config('app.timezone'),
                        'attendance_duration_policy' => 'Payroll local dates are inclusive and map to [start midnight, midnight after period end); elapsed seconds are summed and rounded to the nearest whole minute once per period, half up.',
                        'employee_snapshot' => ['id' => (int) $employee->id, 'name' => $employee->name],
                        'company_snapshot' => ['name' => $employee->company?->name, 'currency' => $employee->company?->currency],
                        'employee_status_snapshot' => $employee->status,
                        'payroll_profile_snapshot' => [
                            'salary_type' => $profile->salary_type,
                            'pay_frequency' => $profile->pay_frequency,
                            'overtime_eligible' => $profile->overtime_eligible,
                            'rank_and_file' => $profile->rank_and_file,
                            'standard_hours_per_day' => $profile->standard_hours_per_day,
                            'standard_work_days_per_month' => $profile->standard_work_days_per_month,
                            'sss_enabled' => $profile->sss_enabled,
                            'philhealth_enabled' => $profile->philhealth_enabled,
                            'pagibig_enabled' => $profile->pagibig_enabled,
                            'withholding_tax_enabled' => $profile->withholding_tax_enabled,
                        ],
                        'attendance_snapshot' => collect($intervalsForPeriod)->flatMap(function (array $interval) use ($periodStart, $periodEndExclusive): array {
                            $sources = [];
                            if ($interval['source_clock_in']->greaterThanOrEqualTo($periodStart) && $interval['source_clock_in']->lt($periodEndExclusive)) {
                                $sources[] = ['id' => $interval['clock_in_id'], 'timestamp' => $interval['source_clock_in']->toDateTimeString(), 'type' => 'clock_in'];
                            }
                            if ($interval['source_clock_out']->greaterThanOrEqualTo($periodStart) && $interval['source_clock_out']->lt($periodEndExclusive)) {
                                $sources[] = ['id' => $interval['clock_out_id'], 'timestamp' => $interval['source_clock_out']->toDateTimeString(), 'type' => 'clock_out'];
                            }

                            return $sources;
                        })->values()->all(),
                        'attendance_allocations' => collect($intervalsForPeriod)->map(fn (array $interval): array => [
                            'clock_in_log_id' => $interval['clock_in_id'],
                            'clock_out_log_id' => $interval['clock_out_id'],
                            'original_clock_in_at' => $interval['source_clock_in']->format('Y-m-d H:i:s.u'),
                            'original_clock_out_at' => $interval['source_clock_out']->format('Y-m-d H:i:s.u'),
                            'segment_start_at' => $interval['clock_in']->format('Y-m-d H:i:s.u'),
                            'segment_end_at' => $interval['clock_out']->format('Y-m-d H:i:s.u'),
                            'elapsed_seconds' => $interval['allocated_elapsed_seconds'],
                            'allocation_key' => $interval['allocation_key'],
                        ])->values()->all(),
                        'worked_minutes' => $work['worked_minutes'],
                        'payable_days' => $work['payable_days'],
                        'hourly_daily_monthly_rates' => $work['rates'],
                        'statutory_rules' => $statutory['rules'],
                        'arithmetic' => 'All currency calculations use integer centavo arithmetic; monetary lines are rounded to centavos.',
                    ],
                    'review_flags' => $flags,
                ]);

                foreach ($lineData as $line) {
                    $this->createLine($entry, $line);
                }
                foreach ($intervalsForPeriod as $interval) {
                    PayrollAttendanceAllocation::query()->create([
                        'company_id' => $lockedPeriod->company_id,
                        'user_id' => $employee->id,
                        'payroll_period_id' => $lockedPeriod->id,
                        'payroll_entry_id' => $entry->id,
                        'clock_in_log_id' => $interval['clock_in_id'],
                        'clock_out_log_id' => $interval['clock_out_id'],
                        'original_clock_in_at' => $interval['source_clock_in']->format('Y-m-d H:i:s.u'),
                        'original_clock_out_at' => $interval['source_clock_out']->format('Y-m-d H:i:s.u'),
                        'segment_start_at' => $interval['clock_in']->format('Y-m-d H:i:s.u'),
                        'segment_end_at' => $interval['clock_out']->format('Y-m-d H:i:s.u'),
                        'elapsed_seconds' => $interval['allocated_elapsed_seconds'],
                        'allocation_key' => $interval['allocation_key'],
                    ]);
                }
                $sourceIds = collect($intervalsForPeriod)->flatMap(function (array $interval) use ($periodStart, $periodEndExclusive): array {
                    $ids = [];
                    if ($interval['source_clock_in']->greaterThanOrEqualTo($periodStart) && $interval['source_clock_in']->lt($periodEndExclusive)) {
                        $ids[] = $interval['clock_in_id'];
                    }
                    if ($interval['source_clock_out']->greaterThanOrEqualTo($periodStart) && $interval['source_clock_out']->lt($periodEndExclusive)) {
                        $ids[] = $interval['clock_out_id'];
                    }

                    return $ids;
                })->unique()->values();
                if ($sourceIds->isNotEmpty()) {
                $entry->attendanceLogs()->sync($sourceIds->all());
                }

                $totals['gross'] += $grossCents;
                $totals['deductions'] += $employeeDeductionsCents;
                $totals['employer'] += $employerCents;
                $totals['net'] += $netCents;
            }

            $lockedPeriod->update([
                'status' => 'pending_review',
                'gross_total' => $this->fromCents($totals['gross']),
                'deduction_total' => $this->fromCents($totals['deductions']),
                'employer_contribution_total' => $this->fromCents($totals['employer']),
                'net_total' => $this->fromCents($totals['net']),
                'calculated_by' => $actorId,
                'calculated_at' => now(),
            ]);

            $this->audit($lockedPeriod, $actorId, 'calculated', 'payroll_period', (int) $lockedPeriod->id, null, ['employee_count' => $employees->count(), 'totals' => $totals]);

            return $lockedPeriod->fresh(['entries.employee', 'entries.lines']);
        });
    }

    public function approve(PayrollPeriod $period, User $actor, ?string $reviewNote = null): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $actor, $reviewNote): PayrollPeriod {
            $locked = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->with(['entries.employee', 'entries.lines'])->firstOrFail();
            if ($locked->status !== 'pending_review') {
                throw ValidationException::withMessages(['status' => 'Only payroll awaiting review can be approved.']);
            }
            if ($locked->entries->isEmpty()) {
                throw ValidationException::withMessages(['period' => 'Calculate payroll before approving it.']);
            }
            $flaggedEntries = $locked->entries->filter(fn (PayrollEntry $entry): bool => ! empty($entry->review_flags));
            $criticalFlags = PayrollReviewFlags::critical();
            $reviewableEntries = $flaggedEntries->filter(fn (PayrollEntry $entry): bool => array_diff($entry->review_flags ?? [], $criticalFlags) !== []);
            if ($reviewableEntries->isNotEmpty() && mb_strlen(trim((string) $reviewNote)) < 10) {
                throw ValidationException::withMessages(['review_note' => 'Add a review note of at least 10 characters for warning-level calculation flags.']);
            }
            $issues = [];
            $periodTotals = ['gross' => 0, 'deductions' => 0, 'employer' => 0, 'net' => 0];
            $linkedLogIds = [];
            $attendanceSnapshotsByEntry = [];
            $allocationSnapshotsByEntry = [];
            $allocationLogIds = [];

            foreach ($locked->entries as $entry) {
                $employeeName = $entry->employee?->name ?? 'Unknown employee';
                $employeeLabel = "{$employeeName} (ID {$entry->user_id})";
                $flags = $entry->review_flags ?? [];
                foreach (array_intersect($criticalFlags, $flags) as $flag) {
                    $issues[] = "{$employeeLabel}: critical calculation issue '{$flag}' must be corrected and recalculated before approval.";
                }

                if ((int) $entry->company_id !== (int) $locked->company_id || (int) $entry->payroll_period_id !== (int) $locked->id
                    || ! $entry->employee || (int) $entry->employee->company_id !== (int) $locked->company_id
                    || strtolower((string) $entry->employee->role) !== 'employee'
                    || (int) data_get($entry->calculation_snapshot, 'employee_snapshot.id', $entry->user_id) !== (int) $entry->user_id) {
                    $issues[] = "{$employeeLabel}: employee is missing or belongs to a different company.";
                    continue;
                }
                if (strtolower((string) $entry->employee->status) !== 'active') {
                    $issues[] = "{$employeeLabel}: employee is inactive; ordinary payroll cannot be approved for this account.";
                }

                $snapshot = $entry->calculation_snapshot ?? [];
                $allocationRows = PayrollAttendanceAllocation::query()->where('payroll_entry_id', $entry->id)
                    ->lockForUpdate()->get();
                $allocationSnapshot = collect(data_get($snapshot, 'attendance_allocations', []));
                if ($locked->frequency !== 'yearly') {
                    $expectedAllocations = $allocationSnapshot->keyBy('allocation_key');
                    $actualAllocations = $allocationRows->keyBy('allocation_key');
                    if ($allocationSnapshot->count() !== $expectedAllocations->count()
                        || $expectedAllocations->keys()->sort()->values()->all() !== $actualAllocations->keys()->sort()->values()->all()) {
                        $issues[] = "{$employeeLabel}: persisted attendance allocations do not match the calculation snapshot.";
                    }
                    $allocationSeconds = 0;
                    foreach ($allocationRows as $allocation) {
                        $allocationLogIds[] = (int) $allocation->clock_in_log_id;
                        $allocationLogIds[] = (int) $allocation->clock_out_log_id;
                        $expected = $expectedAllocations->get($allocation->allocation_key);
                        if (! $expected || $this->normalizeSnapshotForComparison($this->allocationSnapshotRow($allocation))
                            !== $this->normalizeSnapshotForComparison($expected)) {
                            $issues[] = "{$employeeLabel}: an attendance allocation changed after calculation; recalculate before approval.";
                        }
                        $interval = [
                            'clock_in_id' => (int) $allocation->clock_in_log_id,
                            'clock_out_id' => (int) $allocation->clock_out_log_id,
                            'clock_in' => $allocation->original_clock_in_at,
                            'clock_out' => $allocation->original_clock_out_at,
                        ];
                        if ((int) $allocation->company_id !== (int) $locked->company_id
                            || (int) $allocation->user_id !== (int) $entry->user_id
                            || (int) $allocation->payroll_period_id !== (int) $locked->id
                            || (int) $allocation->payroll_entry_id !== (int) $entry->id
                            || ! $this->allocationMatchesSourceInterval($allocation, $interval)) {
                            $issues[] = "{$employeeLabel}: attendance allocation bounds, tenant, or elapsed time are invalid.";
                        }
                        if ($allocation->segment_start_at->lessThan(Carbon::parse($locked->period_start, config('app.timezone'))->startOfDay())
                            || $allocation->segment_end_at->greaterThan(Carbon::parse($locked->period_end, config('app.timezone'))->startOfDay()->addDay())) {
                            $issues[] = "{$employeeLabel}: attendance allocation falls outside the payroll period.";
                        }
                        $allocationSeconds += (int) $allocation->elapsed_seconds;
                    }
                    if ((int) $entry->worked_minutes !== intdiv($allocationSeconds + 30, 60)) {
                        $issues[] = "{$employeeLabel}: worked minutes do not reconcile to the persisted elapsed-time allocations.";
                    }
                    $allocationSnapshotsByEntry[(int) $entry->id] = $allocationRows;
                } elseif ($allocationRows->isNotEmpty() || $allocationSnapshot->isNotEmpty()) {
                    $issues[] = "{$employeeLabel}: annual payroll must not contain attendance allocations.";
                }

                if ($locked->frequency !== 'yearly') {
                    $currentProfile = PayrollProfile::query()->where('company_id', $locked->company_id)
                        ->where('user_id', $entry->user_id)->first();
                    $profileSnapshot = data_get($snapshot, 'payroll_profile_snapshot', []);
                    if (! $currentProfile || ! $currentProfile->payroll_eligible
                        || $currentProfile->pay_frequency !== $locked->frequency) {
                        $issues[] = "{$employeeLabel}: current payroll eligibility or frequency no longer matches this snapshot.";
                    } elseif ($profileSnapshot !== []) {
                        foreach ($profileSnapshot as $field => $value) {
                            if ((string) $currentProfile->{$field} !== (string) $value) {
                                $issues[] = "{$employeeLabel}: payroll setting '{$field}' changed after calculation; recalculate before approval.";
                                break;
                            }
                        }
                    }

                    $currentRates = $this->ratesFor((int) $entry->user_id, $locked)->map(fn (PayrollCompensationHistory $rate): array => [
                        'basis' => $rate->basis, 'rate' => $rate->rate,
                        'effective_from' => $rate->effective_from->toDateString(), 'effective_to' => $rate->effective_to?->toDateString(),
                    ])->values()->all();
                    if ($this->normalizeSnapshotForComparison($currentRates)
                        !== $this->normalizeSnapshotForComparison(data_get($snapshot, 'hourly_daily_monthly_rates', []))) {
                        $issues[] = "{$employeeLabel}: compensation history changed after calculation; recalculate before approval.";
                    }

                    $recurringLines = $entry->lines->filter(fn (PayrollEntryLine $line): bool => data_get($line->details, 'source') === 'recurring_profile_item');
                    $activeItems = $this->activeProfileItems((int) $entry->user_id, $locked);
                    if ($activeItems->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all()
                        !== $recurringLines->map(fn (PayrollEntryLine $line): int => (int) data_get($line->details, 'profile_item_id'))->sort()->values()->all()) {
                        $issues[] = "{$employeeLabel}: recurring earnings or deductions changed after calculation; recalculate before approval.";
                    }
                    foreach ($recurringLines as $line) {
                        $item = PayrollProfileItem::query()->where('company_id', $locked->company_id)
                            ->where('user_id', $entry->user_id)->find(data_get($line->details, 'profile_item_id'));
                        $expectedCategory = $item?->category === 'earning' ? 'earning' : 'employee_deduction';
                        if (! $item || $item->code !== $line->code || $item->label !== $line->label
                            || $this->toCents($item->amount) !== $this->toCents($line->amount)
                            || $expectedCategory !== $line->category || (bool) $item->taxable !== (bool) $line->taxable
                            || $item->effective_from->toDateString() !== data_get($line->details, 'effective_from')
                            || $item->effective_to?->toDateString() !== data_get($line->details, 'effective_to')) {
                            $issues[] = "{$employeeLabel}: a recurring earning or deduction changed after calculation; recalculate before approval.";
                            break;
                        }
                    }

                    foreach (data_get($snapshot, 'statutory_rules', []) as $code => $ruleSnapshot) {
                        if (str_ends_with($code, '_verification') || $code === 'verification') {
                            continue;
                        }
                        $currentRule = PayrollStatutoryRule::query()->where('code', $code)
                            ->whereDate('effective_from', $ruleSnapshot['effective_from'])
                            ->whereDate('effective_from', '<=', $locked->period_end->toDateString())
                            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $locked->period_end->toDateString()))
                            ->first();
                        if (! $currentRule || $currentRule->configuration !== ($ruleSnapshot['configuration'] ?? null)) {
                            $issues[] = "{$employeeLabel}: statutory rule '{$code}' changed or is unavailable; recalculate before approval.";
                        }
                    }
                    if (($profileSnapshot['withholding_tax_enabled'] ?? false)
                        && ! array_key_exists('bir_compensation_tax', data_get($snapshot, 'statutory_rules', []))) {
                        $issues[] = "{$employeeLabel}: the applicable withholding-tax rule is missing from the calculation snapshot.";
                    }
                }

                $attendanceSnapshot = collect(data_get($snapshot, 'attendance_snapshot', []))
                    ->keyBy(fn (array $source): int => (int) $source['id']);
                $attendanceSnapshotsByEntry[(int) $entry->id] = $attendanceSnapshot;
                $entrySourceIds = DB::table('payroll_entry_attendance_logs')->where('payroll_entry_id', $entry->id)
                    ->pluck('attendance_log_id')->map(fn ($id): int => (int) $id)->sort()->values();
                if ($attendanceSnapshot->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all() !== $entrySourceIds->all()) {
                    $issues[] = "{$employeeLabel}: attendance sources no longer match the calculation snapshot.";
                }

                $lineSums = ['earning' => 0, 'employee_deduction' => 0, 'employer_contribution' => 0];
                foreach ($entry->lines as $line) {
                    if (array_key_exists($line->category, $lineSums)) {
                        $lineSums[$line->category] += $this->toCents($line->amount);
                    }
                }
                $grossCents = $this->toCents($entry->gross_pay);
                $deductionCents = $this->toCents($entry->employee_deductions);
                $employerCents = $this->toCents($entry->employer_contributions);
                $netCents = $this->toCents($entry->net_pay);
                $expectedNet = $grossCents - $deductionCents;
                if ($grossCents !== $lineSums['earning'] || $deductionCents !== $lineSums['employee_deduction']
                    || $employerCents !== $lineSums['employer_contribution'] || $netCents !== $expectedNet) {
                    $issues[] = "{$employeeLabel}: saved totals do not match the calculated register lines; recalculate before approval.";
                }
                if ($this->toCents($entry->employer_cost) !== $grossCents + $employerCents) {
                    $issues[] = "{$employeeLabel}: employer cost does not match gross pay plus employer contributions.";
                }
                if ($entry->payments()->exists() || $this->toCents($entry->amount_paid) !== 0) {
                    $issues[] = "{$employeeLabel}: payment records already exist before approval; review the payment audit history.";
                }
                if ($grossCents === 0 && ((int) $entry->worked_minutes > 0 || (int) $entry->payable_days > 0 || $lineSums['earning'] > 0)) {
                    $issues[] = "{$employeeLabel}: gross pay is zero despite payable attendance or earnings; verify rates and recalculate.";
                }
                if ($deductionCents > $grossCents || $netCents < 0) {
                    $issues[] = "{$employeeLabel}: deductions exceed gross pay; correct the calculation before approval.";
                }

                $periodTotals['gross'] += $grossCents;
                $periodTotals['deductions'] += $deductionCents;
                $periodTotals['employer'] += $employerCents;
                $periodTotals['net'] += $netCents;

                $sourceIds = DB::table('payroll_entry_attendance_logs')->where('payroll_entry_id', $entry->id)
                    ->pluck('attendance_log_id')->map(fn ($id): int => (int) $id)->all();
                foreach ($sourceIds as $sourceId) {
                    if (isset($linkedLogIds[$sourceId])) {
                        $issues[] = "{$employeeLabel}: attendance record {$sourceId} is duplicated in this payroll run.";
                    }
                    $linkedLogIds[$sourceId] = (int) $entry->id;
                }
            }

            if ($allocationLogIds !== []) {
                $allocationLogs = AttendanceLog::query()->whereIn('id', array_values(array_unique($allocationLogIds)))
                    ->lockForUpdate()->get()->keyBy('id');
                foreach ($allocationSnapshotsByEntry as $entryId => $allocations) {
                    $entry = $locked->entries->firstWhere('id', $entryId);
                    $employeeName = $entry?->employee?->name ?? 'Unknown employee';
                    foreach ($allocations as $allocation) {
                        $clockIn = $allocationLogs->get($allocation->clock_in_log_id);
                        $clockOut = $allocationLogs->get($allocation->clock_out_log_id);
                        if (! $clockIn || ! $clockOut
                            || (int) $clockIn->company_id !== (int) $locked->company_id
                            || (int) $clockOut->company_id !== (int) $locked->company_id
                            || (int) $clockIn->user_id !== (int) $entry?->user_id
                            || (int) $clockOut->user_id !== (int) $entry?->user_id
                            || $clockIn->status !== 'verified' || $clockOut->status !== 'verified'
                            || $clockIn->type !== 'clock_in' || $clockOut->type !== 'clock_out'
                            || $clockIn->timestamp->format('Y-m-d H:i:s.u') !== $allocation->original_clock_in_at->format('Y-m-d H:i:s.u')
                            || $clockOut->timestamp->format('Y-m-d H:i:s.u') !== $allocation->original_clock_out_at->format('Y-m-d H:i:s.u')) {
                            $issues[] = "{$employeeName} (ID {$entry?->user_id}): allocation source punches are missing, changed, unverified, or outside this employee/company.";
                            continue;
                        }

                        $overlappingAllocationExists = PayrollAttendanceAllocation::query()
                            ->where('clock_in_log_id', $allocation->clock_in_log_id)
                            ->where('clock_out_log_id', $allocation->clock_out_log_id)
                            ->where('id', '!=', $allocation->id)
                            ->where('segment_start_at', '<', $allocation->segment_end_at)
                            ->where('segment_end_at', '>', $allocation->segment_start_at)
                            ->exists();
                        if ($overlappingAllocationExists) {
                            $issues[] = "{$employeeName} (ID {$entry?->user_id}): this elapsed-time segment overlaps another persisted payroll allocation.";
                        }
                    }
                }
            }

            if ($linkedLogIds !== []) {
                $logs = \App\Models\AttendanceLog::query()->whereIn('id', array_keys($linkedLogIds))->lockForUpdate()->get()->keyBy('id');
                foreach ($linkedLogIds as $logId => $entryId) {
                    $entry = $locked->entries->firstWhere('id', $entryId);
                    $log = $logs->get($logId);
                    if (! $log || (int) $log->company_id !== (int) $locked->company_id
                        || (int) $log->user_id !== (int) $entry?->user_id || $log->status !== 'verified' || $log->is_paid) {
                        $employeeName = $entry?->employee?->name ?? 'Unknown employee';
                        $issues[] = "{$employeeName} (ID {$entry?->user_id}): linked attendance record {$logId} is missing, unverified, paid, or outside this employee/company.";
                        continue;
                    }
                    $sourceSnapshot = $attendanceSnapshotsByEntry[$entryId]?->get($logId);
                    if (! $sourceSnapshot || $log->type !== $sourceSnapshot['type']
                        || $log->timestamp->toDateTimeString() !== $sourceSnapshot['timestamp']) {
                        $employeeName = $entry?->employee?->name ?? 'Unknown employee';
                        $issues[] = "{$employeeName} (ID {$entry?->user_id}): attendance record {$logId} changed after calculation; recalculate before approval.";
                    }

                    $alreadyIncluded = DB::table('payroll_entry_attendance_logs as source')
                        ->join('payroll_entries as other_entry', 'other_entry.id', '=', 'source.payroll_entry_id')
                        ->join('payroll_periods as other_period', 'other_period.id', '=', 'other_entry.payroll_period_id')
                        ->where('source.attendance_log_id', $logId)->where('other_period.id', '!=', $locked->id)
                        ->where('other_period.status', '!=', 'voided')->exists();
                    if ($alreadyIncluded) {
                        $employeeName = $entry?->employee?->name ?? 'Unknown employee';
                        $issues[] = "{$employeeName} (ID {$entry?->user_id}): attendance record {$logId} is included in another non-voided payroll period.";
                    }
                }
            }

            if ($periodTotals['gross'] !== $this->toCents($locked->gross_total)
                || $periodTotals['deductions'] !== $this->toCents($locked->deduction_total)
                || $periodTotals['employer'] !== $this->toCents($locked->employer_contribution_total)
                || $periodTotals['net'] !== $this->toCents($locked->net_total)) {
                $issues[] = 'Payroll period totals do not match the employee register; recalculate before approval.';
            }

            if ($issues !== []) {
                throw ValidationException::withMessages(['payroll' => array_values(array_unique($issues))]);
            }

            $before = ['status' => $locked->status, 'net_total' => $locked->net_total];
            $locked->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit($locked, (int) $actor->id, 'approved', 'payroll_period', (int) $locked->id, $before, [
                'status' => $locked->status,
                'net_total' => $locked->net_total,
                'review_note' => filled($reviewNote) ? trim($reviewNote) : null,
                'flagged_entry_ids' => $flaggedEntries->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            ]);

            return $locked->fresh(['entries.lines', 'entries.employee']);
        });
    }

    private function calculateThirteenthMonth(PayrollPeriod $period, int $actorId): PayrollPeriod
    {
        if ($period->period_start->format('Y-m-d') !== $period->period_start->copy()->startOfYear()->format('Y-m-d')
            || $period->period_end->format('Y-m-d') !== $period->period_end->copy()->endOfYear()->format('Y-m-d')) {
            throw ValidationException::withMessages(['period' => 'The annual 13th-month run must cover January 1 through December 31.']);
        }

        $year = (int) $period->period_start->format('Y');
        $employees = User::query()->where('company_id', $period->company_id)
            ->whereRaw('LOWER(role) = ?', ['employee'])
            ->whereHas('payrollProfile', fn ($query) => $query->where('payroll_eligible', true)->where('rank_and_file', true))
            ->with('payrollProfile')->orderBy('id')->get();
        $grossTotal = $deductionTotal = $employerTotal = $netTotal = 0;
        $rule = $this->rule('bir_compensation_tax', Carbon::create($year, 12, 31, 23, 59, 59, config('app.timezone')));

        foreach ($employees as $employee) {
            $basicLines = PayrollEntryLine::query()
                ->where('code', 'basic_pay')
                ->whereHas('entry', fn ($query) => $query->where('company_id', $period->company_id)->where('user_id', $employee->id)
                    ->whereHas('period', fn ($periodQuery) => $periodQuery->whereIn('status', ['approved', 'partially_paid', 'paid'])
                        ->whereBetween('period_start', ["{$year}-01-01", "{$year}-12-31"])))
                ->get(['amount']);
            $basicCents = $basicLines->sum(fn (PayrollEntryLine $line): int => $this->toCents($line->amount));

            if ($basicCents <= 0) {
                continue;
            }

            $thirteenthCents = intdiv($basicCents + 6, 12);
            $supportedTaxableBenefitCents = PayrollEntryLine::query()->whereHas('entry', fn ($query) => $query
                ->where('company_id', $period->company_id)->where('user_id', $employee->id)
                ->whereHas('period', fn ($periodQuery) => $periodQuery->whereIn('status', ['approved', 'partially_paid', 'paid'])
                    ->whereBetween('period_start', ["{$year}-01-01", "{$year}-12-31"])))
                ->where('category', 'earning')->where('taxable', false)
                ->where('code', '!=', 'thirteenth_month_pay')->get(['amount'])
                ->sum(fn (PayrollEntryLine $line): int => $this->toCents($line->amount));
            $taxableBenefit = max(0, $thirteenthCents - 9000000);
            $priorTaxableCents = $this->priorYearTaxableCents($employee, $year, (int) $period->id);
            $priorTaxCents = $this->priorYearTaxCents($employee, $year, (int) $period->id);
            $annualTaxCents = $this->taxFromRule(max(0, $priorTaxableCents + $taxableBenefit), $rule['configuration']['periods']['yearly']);
            $taxCents = $annualTaxCents - $priorTaxCents;

            $flags = ['13th_month_uses_approved_basic_pay_snapshots'];
            // The current profile lacks cumulative non-taxable benefit data, so the
            // remaining 13th-month exemption and year-end tax cannot be validated.
            if ($supportedTaxableBenefitCents > 0 || $thirteenthCents > 0) {
                $flags[] = 'missing_statutory_input_requires_review';
            }
            if (strtolower((string) $employee->status) !== 'active') {
                $flags[] = 'inactive_employee_13th_month_requires_review';
            }
            if (now(config('app.timezone'))->toDateString() < "{$year}-12-31") {
                $flags[] = 'calendar_year_not_completed';
            }
            if ($thirteenthCents > 9000000) {
                $flags[] = 'taxable_benefit_over_90000_includes_only_this_13th_month_payment';
            }
            $entry = PayrollEntry::query()->create([
                'company_id' => $period->company_id, 'payroll_period_id' => $period->id, 'user_id' => $employee->id,
                'gross_pay' => $this->fromCents($thirteenthCents),
                'employee_deductions' => $this->fromCents($taxCents), 'employer_contributions' => '0.00',
                'net_pay' => $this->fromCents($thirteenthCents - $taxCents), 'employer_cost' => $this->fromCents($thirteenthCents),
                'calculation_snapshot' => ['salary_type' => $employee->payrollProfile?->salary_type, 'frequency' => 'yearly', 'year' => $year,
                    'employee_snapshot' => ['id' => (int) $employee->id, 'name' => $employee->name],
                    'employee_status_snapshot' => $employee->status,
                    'company_snapshot' => ['name' => $employee->company?->name, 'currency' => $employee->company?->currency],
                    'basic_salary_in_approved_runs' => $this->fromCents($basicCents), 'formula' => 'Approved calendar-year basic pay / 12; taxable part is amount above the remaining PHP 90,000 benefits exclusion.',
                    'tax_rule' => ['name' => $rule['name'], 'effective_from' => $rule['effective_from']]],
                'review_flags' => $flags,
            ]);
            $this->createLine($entry, $this->line('earning', 'thirteenth_month_pay', '13th-month pay', $thirteenthCents, null, null, false, ['basic_salary_cents' => $basicCents]));
            if ($taxCents !== 0) {
                $this->createLine($entry, $this->line('employee_deduction', 'bir_withholding_tax', 'Withholding tax adjustment', $taxCents, null, null, false, ['exempt_benefits_ceiling_cents' => 9000000]));
            }

            $grossTotal += $thirteenthCents;
            $deductionTotal += $taxCents;
            $netTotal += $thirteenthCents - $taxCents;
        }

        $period->update(['status' => 'pending_review', 'gross_total' => $this->fromCents($grossTotal), 'deduction_total' => $this->fromCents($deductionTotal),
            'employer_contribution_total' => $this->fromCents($employerTotal), 'net_total' => $this->fromCents($netTotal), 'calculated_by' => $actorId, 'calculated_at' => now()]);
        $this->audit($period, $actorId, 'calculated_13th_month', 'payroll_period', (int) $period->id, null, ['year' => $year, 'gross_cents' => $grossTotal]);

        return $period->fresh(['entries.employee', 'entries.lines']);
    }

    private function calculateBaseEarnings(User $employee, PayrollProfile $profile, PayrollPeriod $period, array $intervals, array &$flags): array
    {
        $start = $period->period_start->copy()->startOfDay();
        $end = $period->period_end->copy()->startOfDay()->addDay()->subSecond();
        $rates = $this->ratesFor((int) $employee->id, $period);
        $lineData = [];
        $gross = 0;
        $taxable = 0;
        $workedSeconds = 0;
        $days = [];
        $monthlyBases = [];

        $hourlySeconds = 0;
        $hourlyGross = 0;
        $dailyDates = [];
        $monthlyRateDays = [];
        $monthlySegments = [];
        $coveredDays = [];
        foreach ($intervals as $interval) {
            $spanSeconds = $interval['clock_in']->diffInSeconds($interval['clock_out']);
            $workedSeconds += $spanSeconds;
            $startDate = $interval['clock_in']->toDateString();
            $days[$startDate] = true;
            if ($spanSeconds > ((int) $profile->standard_hours_per_day * 3600)) {
                $flags[] = 'long_shift_overtime_requires_approval';
            }

            $cursor = $interval['clock_in']->copy();
            while ($cursor->lt($interval['clock_out'])) {
                $nextDay = $cursor->copy()->startOfDay()->addDay();
                $segmentEnd = $nextDay->lt($interval['clock_out']) ? $nextDay : $interval['clock_out'];
                $rate = $this->rateAt($rates, $cursor);
                if (! $rate) {
                    $flags[] = 'missing_effective_pay_rate';
                    if ($profile->salary_type === 'hourly') {
                        $flags[] = 'missing_effective_hourly_rate';
                    }
                } elseif ($this->toCents($rate->rate) <= 0) {
                    $flags[] = 'zero_compensation_rate_requires_review';
                } elseif ($rate->basis === 'hourly') {
                    $seconds = $cursor->diffInSeconds($segmentEnd);
                    $hourlySeconds += $seconds;
                    $segmentCents = $this->roundRatio($this->toCents($rate->rate) * $seconds, 3600);
                    $hourlyGross += $segmentCents;
                    $gross += $segmentCents;
                } elseif ($rate->basis === 'daily') {
                    if ($cursor->toDateString() === $startDate) {
                        $dailyDates[$startDate] = $rate;
                    }
                }
                $cursor = $segmentEnd;
            }
        }

        foreach ($dailyDates as $rate) {
            $gross += $this->toCents($rate->rate);
        }

        // Monthly rates accrue by covered local calendar date, independently of
        // whether attendance was recorded, matching the existing monthly policy.
        $month = $start->copy()->startOfMonth();
        while ($month->lte($end)) {
            $monthDays = $month->daysInMonth;
            $coveredStart = $start->greaterThan($month->copy()->startOfMonth()) ? $start->copy() : $month->copy()->startOfMonth();
            $coveredEnd = $end->lessThan($month->copy()->endOfMonth()) ? $end->copy() : $month->copy()->endOfMonth();
            for ($date = $coveredStart->copy()->startOfDay(); $date->lte($coveredEnd->copy()->startOfDay()); $date->addDay()) {
                $rate = $this->rateAt($rates, $date);
                if (! $rate && $profile->salary_type === 'monthly') {
                    $flags[] = 'missing_effective_pay_rate';
                }
                if ($rate?->basis !== 'monthly') {
                    continue;
                }
                if ($this->toCents($rate->rate) <= 0) {
                    $flags[] = 'zero_compensation_rate_requires_review';
                    continue;
                }
                $key = $date->toDateString();
                $monthlyRateDays[$key] = $rate;
                $coveredDays[$key] = true;
                $monthKey = $date->format('Y-m');
                $monthlySegments[$monthKey][$rate->id] ??= ['rate_cents' => $this->toCents($rate->rate), 'days' => 0, 'month_days' => $monthDays];
                $monthlySegments[$monthKey][$rate->id]['days']++;
            }
            $month->addMonth();
        }

        foreach ($monthlyRateDays as $dateKey => $rate) {
            $days[$dateKey] = true;
        }
        $monthlyGross = 0;
        foreach ($monthlySegments as $segments) {
            foreach ($segments as $segment) {
                $monthlyGross += $this->roundRatio($segment['rate_cents'] * $segment['days'], $segment['month_days']);
            }
        }
        $gross += $monthlyGross;

        $taxable = $gross;
        if ($hourlySeconds > 0) {
            $lineData[] = $this->line('earning', 'basic_pay', 'Verified regular hours', $hourlyGross, $hourlySeconds / 3600, null, true,
                ['overtime_not_split_without_approved_overtime_data' => true, 'duration_unit' => 'seconds', 'display_rounding' => 'nearest whole minute, half up']);
        }
        if ($dailyDates !== []) {
            $dailyCents = array_sum(array_map(fn (PayrollCompensationHistory $rate): int => $this->toCents($rate->rate), $dailyDates));
            $lineData[] = $this->line('earning', 'basic_pay', 'Verified payable days', $dailyCents, count($dailyDates), null, true,
                ['one_completed_verified_interval_per_local_start_date' => true]);
        }
        if ($monthlyRateDays !== []) {
            $lineData[] = $this->line('earning', 'basic_pay', 'Monthly salary (calendar-day prorated)', $monthlyGross, count($monthlyRateDays), null, true,
                ['basis' => 'monthly rate divided by actual calendar days in each month']);
        }
        if ($profile->salary_type === 'monthly' && $monthlyRateDays === [] && $intervals === []) {
            $flags[] = 'missing_effective_pay_rate';
        }
        $days = $profile->salary_type === 'monthly' && $monthlyRateDays !== [] ? array_fill_keys(array_keys($coveredDays), true) : $days;

        $monthlyBases = $this->monthlyBasicBases($profile, $rates, $intervals, $start, $end);

        $workedMinutes = intdiv($workedSeconds + 30, 60);

        return ['lines' => $lineData, 'gross_cents' => $gross, 'taxable_cents' => $taxable, 'worked_minutes' => $workedMinutes,
            'payable_days' => count($days), 'monthly_bases' => $monthlyBases,
            'rates' => $rates->map(fn (PayrollCompensationHistory $rate): array => ['basis' => $rate->basis, 'rate' => $rate->rate,
                'effective_from' => $rate->effective_from->toDateString(), 'effective_to' => $rate->effective_to?->toDateString()])->all()];
    }

    private function monthlyBasicBases(PayrollProfile $profile, Collection $rates, array $intervals, Carbon $start, Carbon $end): array
    {
        $bases = [];
        $month = $start->copy()->startOfMonth();
        while ($month->lte($end)) {
            $monthlyCents = 0;
            $daysInMonth = $month->daysInMonth;
            $rateSegments = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = $month->copy()->day($day);
                $rate = $this->rateAt($rates, $date);
                if (! $rate) {
                    continue;
                }
                $rateCents = $this->toCents($rate->rate);
                $monthlyRateCents = match ($rate->basis) {
                    'monthly' => $rateCents,
                    'daily' => $rateCents * (int) $profile->standard_work_days_per_month,
                    default => $rateCents * (int) $profile->standard_hours_per_day * (int) $profile->standard_work_days_per_month,
                };
                $rateSegments[$rate->id] ??= ['monthly_rate_cents' => $monthlyRateCents, 'days' => 0];
                $rateSegments[$rate->id]['days']++;
            }
            foreach ($rateSegments as $segment) {
                $monthlyCents += $this->roundRatio($segment['monthly_rate_cents'] * $segment['days'], $daysInMonth);
            }
            $bases[$month->format('Y-m')] = $monthlyCents;
            $month->addMonth();
        }

        return $bases;
    }

    private function statutoryForPeriod(PayrollProfile $profile, array $monthlyBases, PayrollPeriod $period): array
    {
        $lines = [];
        $employeeTotal = 0;
        $employerTotal = 0;
        $employeeStatutory = 0;
        $rules = [];

        foreach ($monthlyBases as $monthKey => $baseCents) {
            $monthStart = Carbon::createFromFormat('!Y-m-d', $monthKey.'-01', config('app.timezone'));
            $monthEnd = $monthStart->copy()->endOfMonth();
            $periodStart = $period->period_start->copy()->startOfDay();
            $periodEnd = $period->period_end->copy()->startOfDay();
            $coveredStart = $periodStart->greaterThan($monthStart) ? $periodStart : $monthStart;
            $coveredEnd = $periodEnd->lessThan($monthEnd) ? $periodEnd : $monthEnd;
            if ($coveredEnd->lt($coveredStart)) {
                continue;
            }
            $fractionDays = $coveredStart->diffInDays($coveredEnd) + 1;
            $monthDays = $monthStart->daysInMonth;
            $monthShares = [];
            $monthEmployee = $monthEmployer = 0;

            if ($profile->sss_enabled) {
                $rule = $this->rule('sss', $monthStart);
                $cfg = $rule['configuration'];
                $stepCents = (int) $cfg['msc_step'] * 100;
                $minimumCents = (int) $cfg['msc_min'] * 100;
                $maximumCents = (int) $cfg['msc_max'] * 100;
                $mscCents = min($maximumCents, max($minimumCents, intdiv($baseCents + (int) $cfg['msc_rounding_offset'] * 100, $stepCents) * $stepCents));
                $ee = $this->percent($mscCents, (int) $cfg['employee_rate_bps']);
                $er = $this->percent($mscCents, (int) $cfg['employer_rate_bps']);
                $ec = (intdiv($mscCents, 100) <= (int) $cfg['ec_threshold_msc'] ? (int) $cfg['ec_below_threshold'] : (int) $cfg['ec_at_or_above_threshold']) * 100;
                $monthShares['sss_employee'] = $ee;
                $monthShares['sss_employer'] = $er;
                $monthShares['sss_ec'] = $ec;
                $rules['sss'] = ['name' => $rule['name'], 'effective_from' => $rule['effective_from'], 'configuration' => $cfg];
            }

            if ($profile->philhealth_enabled) {
                $rule = $this->rule('philhealth', $monthStart);
                $cfg = $rule['configuration'];
                $salary = min((int) $cfg['monthly_basic_salary_ceiling'] * 100,
                    max((int) $cfg['monthly_basic_salary_floor'] * 100, $baseCents));
                $premium = $this->percent($salary, (int) $cfg['rate_bps']);
                $monthShares['philhealth_employee'] = $this->percent($premium, (int) $cfg['employee_share_bps']);
                $monthShares['philhealth_employer'] = $this->percent($premium, (int) $cfg['employer_share_bps']);
                $rules['philhealth'] = ['name' => $rule['name'], 'effective_from' => $rule['effective_from'], 'configuration' => $cfg];
            }

            if ($profile->pagibig_enabled) {
                $rule = $this->rule('pagibig', $monthStart);
                $cfg = $rule['configuration'];
                $fundSalary = min((int) $cfg['monthly_fund_salary_ceiling'] * 100, $baseCents);
                $employeeRateBps = intdiv($baseCents, 100) <= (int) $cfg['employee_threshold']
                    ? (int) $cfg['employee_rate_below_or_equal_bps'] : (int) $cfg['employee_rate_above_bps'];
                $monthShares['pagibig_employee'] = $this->percent($fundSalary, $employeeRateBps);
                $monthShares['pagibig_employer'] = $this->percent($fundSalary, (int) $cfg['employer_rate_bps']);
                $rules['pagibig'] = ['name' => $rule['name'], 'effective_from' => $rule['effective_from'], 'configuration' => $cfg];
            }

            $factor = fn (int $amount): int => $this->roundRatio($amount * $fractionDays, $monthDays);
            $sssEe = $factor($monthShares['sss_employee'] ?? 0);
            $phEe = $factor($monthShares['philhealth_employee'] ?? 0);
            $pagEe = $factor($monthShares['pagibig_employee'] ?? 0);
            $sssEr = $factor($monthShares['sss_employer'] ?? 0);
            $ecEr = $factor($monthShares['sss_ec'] ?? 0);
            $phEr = $factor($monthShares['philhealth_employer'] ?? 0);
            $pagEr = $factor($monthShares['pagibig_employer'] ?? 0);
            $monthEmployee = $sssEe + $phEe + $pagEe;
            $monthEmployer = $sssEr + $ecEr + $phEr + $pagEr;

            foreach ([
                ['employee_deduction', 'sss_employee', 'SSS employee share', $sssEe],
                ['employee_deduction', 'philhealth_employee', 'PhilHealth employee share', $phEe],
                ['employee_deduction', 'pagibig_employee', 'Pag-IBIG employee share', $pagEe],
                ['employer_contribution', 'sss_employer', 'SSS employer share', $sssEr],
                ['employer_contribution', 'sss_ec', 'SSS Employees’ Compensation', $ecEr],
                ['employer_contribution', 'philhealth_employer', 'PhilHealth employer share', $phEr],
                ['employer_contribution', 'pagibig_employer', 'Pag-IBIG employer share', $pagEr],
            ] as [$category, $code, $label, $amount]) {
                if ($amount > 0) {
                    $lines[] = $this->line($category, $code, $label, $amount, null, null, false,
                        ['contribution_month' => $monthKey, 'allocated_days' => $fractionDays, 'month_days' => $monthDays]);
                }
            }
            $employeeTotal += $monthEmployee;
            $employeeStatutory += $monthEmployee;
            $employerTotal += $monthEmployer;
        }

        return ['lines' => $lines, 'employee_cents' => $employeeTotal, 'employee_statutory_cents' => $employeeStatutory,
            'employer_cents' => $employerTotal, 'rules' => $rules];
    }

    private function withholdingTax(User $employee, PayrollPeriod $period, int $taxableCents, array $currentLines): int
    {
        $rule = $this->rule('bir_compensation_tax', $period->period_end);
        $periodEnd = $period->period_end->copy()->endOfDay();
        $yearStart = $periodEnd->copy()->startOfYear()->startOfDay();

        if ($periodEnd->month === 12 && $periodEnd->isSameDay($period->period_end->copy()->endOfYear())) {
            $ytdTaxable = $this->priorYearTaxableCents($employee, (int) $periodEnd->year, (int) $period->id) + $taxableCents;
            $priorWithholding = $this->priorYearTaxCents($employee, (int) $periodEnd->year, (int) $period->id);
            return $this->taxFromRule($ytdTaxable, $rule['configuration']['periods']['yearly']) - $priorWithholding;
        }

        $taxPeriod = match ($period->frequency) {
            'hourly', 'daily' => 'daily',
            'weekly' => 'weekly',
            'semimonthly' => 'semimonthly',
            'monthly' => 'monthly',
            'yearly' => 'yearly',
        };
        if ($taxPeriod === 'yearly') {
            return $this->taxFromRule($taxableCents, $rule['configuration']['periods']['yearly']);
        }

        return $this->taxFromRule($taxableCents, $rule['configuration']['periods'][$taxPeriod]);
    }

    private function priorYearTaxableCents(User $employee, int $year, int $currentPeriodId): int
    {
        $lines = PayrollEntryLine::query()->whereHas('entry', fn ($entry) => $entry->where('company_id', $employee->company_id)
            ->where('user_id', $employee->id)->whereHas('period', fn ($period) => $period->whereIn('status', ['approved', 'partially_paid', 'paid'])
                ->where('id', '!=', $currentPeriodId)->whereDate('period_start', '>=', "{$year}-01-01")->whereDate('period_end', '<=', "{$year}-12-31")))
            ->where(function ($query): void {
                $query->where('category', 'earning')->where('taxable', true)
                    ->orWhere(function ($q): void { $q->where('category', 'employee_deduction')->whereIn('code', ['sss_employee', 'philhealth_employee', 'pagibig_employee']); });
            })->get();

        $taxable = 0;
        foreach ($lines as $line) {
            $amount = $this->toCents($line->amount);
            $taxable += $line->category === 'employee_deduction' ? -$amount : $amount;
        }

        return max(0, $taxable);
    }

    private function priorYearTaxCents(User $employee, int $year, int $currentPeriodId): int
    {
        $lines = PayrollEntryLine::query()->where('category', 'employee_deduction')->where('code', 'bir_withholding_tax')
            ->whereHas('entry', fn ($entry) => $entry->where('company_id', $employee->company_id)->where('user_id', $employee->id)
                ->whereHas('period', fn ($period) => $period->whereIn('status', ['approved', 'partially_paid', 'paid'])->where('id', '!=', $currentPeriodId)
                    ->whereDate('period_start', '>=', "{$year}-01-01")->whereDate('period_end', '<=', "{$year}-12-31")))->get();

        return $lines->sum(fn (PayrollEntryLine $line): int => $this->toCents($line->amount));
    }

    private function taxFromRule(int $taxableCents, array $bands): int
    {
        $previousUpper = 0;
        foreach ($bands as [$upper, $baseTax, $rateBps]) {
            if ($upper === null || $taxableCents <= $upper) {
                return (int) $baseTax + $this->percent(max(0, $taxableCents - $previousUpper), (int) $rateBps);
            }
            $previousUpper = (int) $upper;
        }
        return 0;
    }

    private function rule(string $code, Carbon $date): array
    {
        $key = $code.'|'.$date->toDateString();
        if (isset($this->ruleCache[$key])) {
            return $this->ruleCache[$key];
        }
        $rule = PayrollStatutoryRule::query()->where('code', $code)->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString()))
            ->orderByDesc('effective_from')->first();
        if (! $rule) {
            throw ValidationException::withMessages(['statutory_rules' => "No effective {$code} rule is configured for {$date->toDateString()}."]);
        }
        return $this->ruleCache[$key] = ['name' => $rule->name, 'effective_from' => $rule->effective_from->toDateString(), 'configuration' => $rule->configuration];
    }

    private function ratesFor(int $userId, PayrollPeriod $period): Collection
    {
        return PayrollCompensationHistory::query()->where('company_id', $period->company_id)->where('user_id', $userId)
            ->whereDate('effective_from', '<=', $period->period_end->toDateString())
            ->orderBy('effective_from')->get();
    }

    private function rateAt(Collection $rates, Carbon $date): ?PayrollCompensationHistory
    {
        // Compensation history dates are inclusive calendar dates; compare dates
        // so an effective_to row remains valid through the whole final day.
        $dateKey = $date->toDateString();

        return $rates->filter(fn (PayrollCompensationHistory $rate): bool => $rate->effective_from->toDateString() <= $dateKey
            && (! $rate->effective_to || $rate->effective_to->toDateString() >= $dateKey))->sortByDesc('effective_from')->first();
    }

    private function activeProfileItems(int $userId, PayrollPeriod $period): EloquentCollection
    {
        return PayrollProfileItem::query()->where('company_id', $period->company_id)->where('user_id', $userId)
            ->whereDate('effective_from', '<=', $period->period_end->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->period_start->toDateString()))
            ->orderBy('id')->get();
    }

    private function periodHasApprovedLeave(int $userId, PayrollPeriod $period): bool
    {
        return DB::table('leave_requests')->where('company_id', $period->company_id)->where('user_id', $userId)->where('status', 'approved')
            ->whereDate('start_date', '<=', $period->period_end->toDateString())->whereDate('end_date', '>=', $period->period_start->toDateString())->exists();
    }

    private function allocationMatchesSourceInterval(PayrollAttendanceAllocation $allocation, array $interval): bool
    {
        return (int) $allocation->clock_in_log_id === (int) $interval['clock_in_id']
            && (int) $allocation->clock_out_log_id === (int) $interval['clock_out_id']
            && $allocation->original_clock_in_at->format('Y-m-d H:i:s.u') === $interval['clock_in']->format('Y-m-d H:i:s.u')
            && $allocation->original_clock_out_at->format('Y-m-d H:i:s.u') === $interval['clock_out']->format('Y-m-d H:i:s.u')
            && $allocation->segment_start_at->lt($allocation->segment_end_at)
            && $allocation->segment_start_at->greaterThanOrEqualTo($allocation->original_clock_in_at)
            && $allocation->segment_end_at->lessThanOrEqualTo($allocation->original_clock_out_at)
            && (int) $allocation->elapsed_seconds === (int) $allocation->segment_start_at->diffInSeconds($allocation->segment_end_at)
            && $allocation->allocation_key === $this->allocationKey(
                (int) $allocation->company_id,
                (int) $allocation->user_id,
                (int) $allocation->clock_in_log_id,
                (int) $allocation->clock_out_log_id,
                $allocation->segment_start_at,
                $allocation->segment_end_at,
            );
    }

    private function allocationSnapshotRow(PayrollAttendanceAllocation $allocation): array
    {
        return [
            'clock_in_log_id' => (int) $allocation->clock_in_log_id,
            'clock_out_log_id' => (int) $allocation->clock_out_log_id,
            'original_clock_in_at' => $allocation->original_clock_in_at->format('Y-m-d H:i:s.u'),
            'original_clock_out_at' => $allocation->original_clock_out_at->format('Y-m-d H:i:s.u'),
            'segment_start_at' => $allocation->segment_start_at->format('Y-m-d H:i:s.u'),
            'segment_end_at' => $allocation->segment_end_at->format('Y-m-d H:i:s.u'),
            'elapsed_seconds' => (int) $allocation->elapsed_seconds,
            'allocation_key' => $allocation->allocation_key,
        ];
    }

    /**
     * MySQL's native JSON type may reorder object keys on read. Compare JSON
     * objects independent of key order while keeping list order and data values
     * strict. Normalize only equivalent persisted representations: timestamp
     * strings with omitted trailing fractional zeroes and DECIMAL rate strings
     * with a different but centavo-equivalent scale.
     */
    private function normalizeSnapshotForComparison(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $childKey => $childValue) {
                $normalized[$childKey] = $this->normalizeSnapshotForComparison($childValue, (string) $childKey);
            }

            if (! array_is_list($normalized)) {
                ksort($normalized, SORT_STRING);
            }

            return $normalized;
        }

        if (is_string($value) && $key !== null && str_ends_with($key, '_at')
            && preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?$/', $value, $matches)) {
            return $matches[1].'.'.str_pad($matches[2] ?? '', 6, '0');
        }

        if ($key === 'rate' && (is_string($value) || is_int($value) || is_float($value))) {
            $decimal = is_float($value) ? json_encode($value, JSON_PRESERVE_ZERO_FRACTION) : (string) $value;
            if (is_string($decimal) && preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/', $decimal, $matches)) {
                $fraction = $matches[3] ?? '';
                if (strlen($fraction) <= 2 || trim(substr($fraction, 2), '0') === '') {
                    $whole = ltrim($matches[2], '0') ?: '0';
                    $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

                    return ($matches[1] === '-' && ($whole !== '0' || $fraction !== '00') ? '-' : '').$whole.'.'.$fraction;
                }
            }
        }

        return $value;
    }

    private function allocationKey(
        int $companyId,
        int $userId,
        int $clockInLogId,
        int $clockOutLogId,
        Carbon $segmentStart,
        Carbon $segmentEnd,
    ): string {
        return hash('sha256', implode('|', [
            $companyId,
            $userId,
            $clockInLogId,
            $clockOutLogId,
            $segmentStart->format('Y-m-d H:i:s.u'),
            $segmentEnd->format('Y-m-d H:i:s.u'),
        ]));
    }

    private function assertNoFinalizedOverlap(PayrollPeriod $period): void
    {
        $conflict = PayrollPeriod::query()->where('company_id', $period->company_id)->where('id', '!=', $period->id)
            ->where('frequency', '!=', 'yearly')->where('status', '!=', 'voided')
            ->whereDate('period_start', '<=', $period->period_end->toDateString())->whereDate('period_end', '>=', $period->period_start->toDateString())->exists();
        if ($conflict && $period->frequency !== 'yearly') {
            throw ValidationException::withMessages(['period' => 'This payroll overlaps another active payroll period. Void the other draft or use non-overlapping dates.']);
        }
    }

    private function verifiedIntervals(Collection $logs): array
    {
        $logs = $logs->groupBy(fn (AttendanceLog $log): string => $log->type.'|'.$log->timestamp->format('Y-m-d H:i:s.u'))
            ->map(fn (Collection $duplicates): AttendanceLog => $duplicates->sortByDesc(fn (AttendanceLog $log): int => match ($log->status) {
                'rejected' => 3, 'flagged' => 2, default => 1,
            })->first())->sortBy(fn (AttendanceLog $log): string => $log->timestamp->format('Y-m-d H:i:s.u').'|'.str_pad((string) $log->id, 12, '0', STR_PAD_LEFT))->values();

        $intervals = [];
        $open = null;
        foreach ($logs as $log) {
            if ($log->status !== 'verified') {
                $open = null;
                continue;
            }
            if ($log->type === 'clock_in') {
                // A second clock-in cannot close or overwrite the prior shift.
                // Preserve it for the incomplete-attendance review path.
                if ($open instanceof AttendanceLog) {
                    $intervals[] = ['incomplete' => true, 'clock_in' => $open->timestamp->copy()->timezone(config('app.timezone')),
                        'clock_out' => null, 'clock_in_id' => (int) $open->id, 'clock_out_id' => null,
                        'clock_in_paid' => (bool) $open->is_paid, 'clock_out_paid' => false];
                }
                $open = $log;
                continue;
            }
            if ($log->type === 'clock_out' && $open instanceof AttendanceLog && $log->timestamp->gt($open->timestamp)) {
                $intervals[] = ['incomplete' => false, 'clock_in' => $open->timestamp->copy()->timezone(config('app.timezone')), 'clock_out' => $log->timestamp->copy()->timezone(config('app.timezone')),
                    'clock_in_id' => (int) $open->id, 'clock_out_id' => (int) $log->id, 'clock_in_paid' => (bool) $open->is_paid, 'clock_out_paid' => (bool) $log->is_paid];
                $open = null;
            }
        }
        if ($open instanceof AttendanceLog) {
            $intervals[] = ['incomplete' => true, 'clock_in' => $open->timestamp->copy()->timezone(config('app.timezone')),
                'clock_out' => null, 'clock_in_id' => (int) $open->id, 'clock_out_id' => null,
                'clock_in_paid' => (bool) $open->is_paid, 'clock_out_paid' => false];
        }
        return $intervals;
    }

    private function hasIncompleteVerifiedClockIn(Collection $logs, Carbon $start, Carbon $endExclusive): bool
    {
        $open = null;
        foreach ($logs as $log) {
            if ($log->status !== 'verified') {
                $open = null;
            } elseif ($log->type === 'clock_in') {
                if ($open instanceof AttendanceLog && $open->timestamp->greaterThanOrEqualTo($start) && $open->timestamp->lt($endExclusive)) {
                    return true;
                }
                $open = $log;
            } elseif ($log->type === 'clock_out') {
                $open = null;
            }
        }

        return $open instanceof AttendanceLog && $open->timestamp->greaterThanOrEqualTo($start) && $open->timestamp->lt($endExclusive);
    }

    private function hasInvalidAttendanceTimestamps(Collection $logs): bool
    {
        $lastTimestamp = null;
        foreach ($logs as $log) {
            if (! $log->timestamp || ! in_array($log->type, ['clock_in', 'clock_out'], true)) {
                return true;
            }
            if ($lastTimestamp && $log->timestamp->lt($lastTimestamp)) {
                return true;
            }
            $lastTimestamp = $log->timestamp;
        }

        return false;
    }

    private function taxInputsAreComplete(User $employee): bool
    {
        // The current payroll profile has no employee tax classification, MWE,
        // de minimis, or cumulative 13th-month/benefit fields.
        return false;
    }

    private function createLine(PayrollEntry $entry, array $line): void
    {
        $entry->lines()->create([
            'category' => $line['category'], 'code' => $line['code'], 'label' => $line['label'],
            'amount' => $this->fromCents($line['amount_cents']), 'quantity' => $line['quantity'], 'rate' => $line['rate'],
            'taxable' => $line['taxable'], 'details' => $line['details'],
        ]);
    }

    private function line(string $category, string $code, string $label, int $amountCents, ?float $quantity, ?float $rate, bool $taxable, array $details = []): array
    {
        return compact('category', 'code', 'label', 'quantity', 'rate', 'taxable', 'details') + ['amount_cents' => $amountCents];
    }

    private function audit(PayrollPeriod $period, int $actorId, string $action, string $type, int $entityId, ?array $before, ?array $after): void
    {
        PayrollAudit::query()->create(['company_id' => $period->company_id, 'actor_id' => $actorId, 'action' => $action,
            'entity_type' => $type, 'entity_id' => $entityId, 'before_data' => $before, 'after_data' => $after]);
    }

    private function percent(int $amountCents, int $rateBasisPoints): int
    {
        return $this->roundRatio($amountCents * $rateBasisPoints, 10000);
    }

    private function roundRatio(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function toCents(int|float|string|null $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{0,}))?$/', $value, $matches)) {
            return 0;
        }
        $fraction = str_pad(substr($matches[3] ?? '', 0, 3), 3, '0');
        $cents = ((int) $matches[2] * 100) + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $cents++;
        }
        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);
        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
