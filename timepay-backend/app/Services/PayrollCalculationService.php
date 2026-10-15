<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\PayrollAudit;
use App\Models\PayrollCompensationHistory;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\PayrollProfileItem;
use App\Models\PayrollStatutoryRule;
use App\Models\User;
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
            $lockedPeriod->entries()->delete();

            if ($lockedPeriod->frequency === 'yearly') {
                return $this->calculateThirteenthMonth($lockedPeriod, $actorId);
            }

            $employees = User::query()
                ->where('company_id', $lockedPeriod->company_id)
                ->whereRaw('LOWER(role) = ?', ['employee'])
                ->whereHas('payrollProfile', fn ($query) => $query->where('payroll_eligible', true)->where('pay_frequency', $lockedPeriod->frequency))
                ->with(['payrollProfile', 'company'])
                ->orderBy('id')
                ->get();

            $periodStart = Carbon::parse($lockedPeriod->period_start, config('app.timezone'))->startOfDay();
            $periodEnd = Carbon::parse($lockedPeriod->period_end, config('app.timezone'))->endOfDay();
            $logsByEmployee = AttendanceLog::query()
                ->where('company_id', $lockedPeriod->company_id)
                ->whereIn('user_id', $employees->pluck('id'))
                ->whereBetween('timestamp', [$periodStart->copy()->subDay(), $periodEnd->copy()->addDay()])
                ->orderBy('timestamp')->orderBy('id')->get()->groupBy('user_id');

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
                $intervals = $this->verifiedIntervals($sourceLogs);
                $flags = [];
                if (strtolower((string) $employee->status) !== 'active') {
                    $flags[] = 'employee_status_requires_review';
                }
                $intervalsForPeriod = [];

                foreach ($intervals as $interval) {
                    $overlapsPeriod = $interval['clock_in']->lte($periodEnd)
                        && $interval['clock_out']->gte($periodStart);
                    $crossesBoundary = $interval['clock_in']->lt($periodStart)
                        || $interval['clock_out']->gt($periodEnd);

                    if ($overlapsPeriod && $crossesBoundary) {
                        $flags[] = 'attendance_crosses_pay_period_boundary_requires_review';
                        continue;
                    }

                    if ($interval['clock_in']->lt($periodStart) || $interval['clock_in']->gt($periodEnd)) {
                        continue;
                    }
                    if (in_array($interval['clock_in_id'], $alreadyUsedLogIds, true)
                        || in_array($interval['clock_out_id'], $alreadyUsedLogIds, true)
                        || $interval['clock_in_paid'] || $interval['clock_out_paid']) {
                        $flags[] = 'attendance_already_used_or_paid';
                        continue;
                    }
                    $intervalsForPeriod[] = $interval;
                }

                if ($this->hasIncompleteVerifiedClockIn($sourceLogs, $periodStart, $periodEnd)) {
                    $flags[] = 'incomplete_attendance_requires_review';
                }

                $work = $this->calculateBaseEarnings($employee, $profile, $lockedPeriod, $intervalsForPeriod, $flags);
                if (in_array('missing_effective_pay_rate', $flags, true)) {
                    throw ValidationException::withMessages([
                        'compensation_history' => "Payroll cannot be calculated for {$employee->id}: no effective compensation rate covers part or all of the pay period. Correct the rate history and retry.",
                    ]);
                }
                $lineData = $work['lines'];
                $grossCents = $work['gross_cents'];
                $taxableCents = $work['taxable_cents'];

                foreach ($this->activeProfileItems((int) $employee->id, $lockedPeriod) as $item) {
                    // Profile items have no explicit proration policy. Do not silently
                    // treat a partial-period item as a full-period amount without review.
                    if ($item->effective_from->gt($lockedPeriod->period_start)
                        || ($item->effective_to && $item->effective_to->lt($lockedPeriod->period_end))) {
                        $flags[] = 'recurring_profile_item_partial_period_requires_review';
                    }

                    $amount = $this->toCents($item->amount);
                    if ($item->category === 'earning') {
                        $lineData[] = $this->line('earning', $item->code, $item->label, $amount, null, null, $item->taxable, ['source' => 'recurring_profile_item']);
                        $grossCents += $amount;
                        if ($item->taxable) {
                            $taxableCents += $amount;
                        }
                    } else {
                        $lineData[] = $this->line('employee_deduction', $item->code, $item->label, $amount, null, null, false, [
                            'source' => 'recurring_profile_item',
                            'authorization_reference' => $item->authorization_reference,
                        ]);
                    }
                }

                $statutory = $this->statutoryForPeriod($profile, $work['monthly_bases'], $lockedPeriod);
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
                    $taxableAfterStatutory = max(0, $taxableCents - $statutory['employee_statutory_cents']);
                    $withholdingCents = $this->withholdingTax($employee, $lockedPeriod, $taxableAfterStatutory, $lineData);
                    if ($withholdingCents < 0) {
                        // A year-end credit can increase net pay. Require an explicit
                        // payroll review rather than silently treating it as routine.
                        $flags[] = 'year_end_withholding_credit_requires_review';
                    }
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
                $sourceIds = collect($intervalsForPeriod)->flatMap(fn (array $interval): array => [$interval['clock_in_id'], $interval['clock_out_id']])->unique()->values();
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
            $locked = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->with('entries')->firstOrFail();
            if ($locked->status !== 'pending_review') {
                throw ValidationException::withMessages(['status' => 'Only payroll awaiting review can be approved.']);
            }
            if ($locked->entries->isEmpty()) {
                throw ValidationException::withMessages(['period' => 'Calculate payroll before approving it.']);
            }
            if ($locked->entries->contains(fn (PayrollEntry $entry): bool => $this->toCents($entry->net_pay) < 0)) {
                throw ValidationException::withMessages(['payroll' => 'Payroll has negative net pay. Resolve deductions before approval.']);
            }
            $flaggedEntries = $locked->entries->filter(fn (PayrollEntry $entry): bool => ! empty($entry->review_flags));
            if ($flaggedEntries->isNotEmpty() && mb_strlen(trim((string) $reviewNote)) < 10) {
                throw ValidationException::withMessages(['review_note' => 'Add a review note of at least 10 characters to approve payroll with calculation flags.']);
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
        if (now(config('app.timezone'))->toDateString() <= "{$year}-12-31") {
            throw ValidationException::withMessages([
                'period' => 'The 13th-month run cannot be calculated until the calendar year has ended and all eligible basic-pay periods can be finalized.',
            ]);
        }

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
            $taxableBenefit = max(0, $thirteenthCents - 9000000);
            $priorTaxableCents = $this->priorYearTaxableCents($employee, $year, (int) $period->id);
            $priorTaxCents = $this->priorYearTaxCents($employee, $year, (int) $period->id);
            $annualTaxCents = $this->taxFromRule(max(0, $priorTaxableCents + $taxableBenefit), $rule['configuration']['periods']['yearly']);
            $taxCents = $annualTaxCents - $priorTaxCents;

            $flags = ['13th_month_uses_approved_basic_pay_snapshots'];
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
        $end = $period->period_end->copy()->endOfDay();
        $rates = $this->ratesFor((int) $employee->id, $period);
        $lineData = [];
        $gross = 0;
        $taxable = 0;
        $minutes = 0;
        $days = [];
        $monthlyBases = [];

        // Overlapping effective dates make the compensation history ambiguous.
        // Keep the existing deterministic rate selection, but require explicit review.
        $matchingRates = $rates->where('basis', $profile->salary_type)->values();
        for ($i = 0; $i < $matchingRates->count(); $i++) {
            for ($j = $i + 1; $j < $matchingRates->count(); $j++) {
                $left = $matchingRates[$i];
                $right = $matchingRates[$j];
                $leftEnd = $left->effective_to ?? Carbon::parse('9999-12-31', config('app.timezone'));
                $rightEnd = $right->effective_to ?? Carbon::parse('9999-12-31', config('app.timezone'));
                if ($left->effective_from->lte($rightEnd) && $right->effective_from->lte($leftEnd)
                    && $left->effective_from->lte($end) && $right->effective_from->lte($end)
                    && $leftEnd->gte($start) && $rightEnd->gte($start)) {
                    $flags[] = 'compensation_history_overlap_requires_review';
                    break 2;
                }
            }
        }
        if ($matchingRates->isEmpty()) {
            $flags[] = 'missing_effective_pay_rate';
        } elseif ($matchingRates->contains(fn (PayrollCompensationHistory $rate): bool => $this->toCents($rate->rate) <= 0)) {
            $flags[] = 'zero_compensation_rate_requires_review';
        }

        if ($profile->salary_type === 'hourly') {
            foreach ($intervals as $interval) {
                $span = $interval['clock_in']->diffInMinutes($interval['clock_out']);
                $minutes += $span;
                $days[$interval['clock_in']->toDateString()] = true;
                if ($span > ((int) $profile->standard_hours_per_day * 60)) {
                    $flags[] = 'long_shift_overtime_requires_approval';
                }
                $gross += $this->hourlyIntervalCents($interval['clock_in'], $interval['clock_out'], $rates, $flags);
            }
            $taxable = $gross;
            if ($gross > 0) {
                $lineData[] = $this->line('earning', 'basic_pay', 'Verified regular hours', $gross, $minutes / 60, null, true,
                    ['overtime_not_split_without_approved_overtime_data' => true]);
            }
        } elseif ($profile->salary_type === 'daily') {
            foreach ($intervals as $interval) {
                $minutes += $interval['clock_in']->diffInMinutes($interval['clock_out']);
                $dateKey = $interval['clock_in']->toDateString();
                $days[$dateKey] = true;
            }
            foreach (array_keys($days) as $dateKey) {
                $rate = $this->rateAt($rates, Carbon::parse($dateKey, config('app.timezone')));
                if (! $rate || $rate->basis !== 'daily') {
                    $flags[] = 'missing_effective_pay_rate';
                    continue;
                }
                $gross += $this->toCents($rate->rate);
            }
            $taxable = $gross;
            if ($gross > 0) {
                $lineData[] = $this->line('earning', 'basic_pay', 'Verified payable days', $gross, count($days), null, true,
                    ['one_completed_verified_interval_per_local_start_date' => true]);
            }
        } else {
            $coveredDays = [];
            $month = $start->copy()->startOfMonth();
            while ($month->lte($end)) {
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();
                $coveredStart = $start->greaterThan($monthStart) ? $start->copy() : $monthStart;
                $coveredEnd = $end->lessThan($monthEnd) ? $end->copy() : $monthEnd;
                $monthDays = $month->daysInMonth;
                $rateSegments = [];
                for ($date = $coveredStart->copy()->startOfDay(); $date->lte($coveredEnd->copy()->startOfDay()); $date->addDay()) {
                    $coveredDays[$date->toDateString()] = true;
                    $rate = $this->rateAt($rates, $date);
                    if (! $rate || $rate->basis !== 'monthly') {
                        $flags[] = 'missing_effective_pay_rate';
                        continue;
                    }
                    $rateSegments[$rate->id] ??= ['rate_cents' => $this->toCents($rate->rate), 'days' => 0];
                    $rateSegments[$rate->id]['days']++;
                }
                foreach ($rateSegments as $segment) {
                    $gross += $this->roundRatio($segment['rate_cents'] * $segment['days'], $monthDays);
                }
                $month->addMonth();
            }
            $days = $coveredDays;
            $minutes = collect($intervals)->sum(fn (array $interval): int => $interval['clock_in']->diffInMinutes($interval['clock_out']));
            $taxable = $gross;
            if ($gross > 0) {
                $lineData[] = $this->line('earning', 'basic_pay', 'Monthly salary (calendar-day prorated)', $gross, count($coveredDays), null, true,
                    ['basis' => 'monthly rate divided by actual calendar days in each month']);
            }
        }

        $monthlyBases = $this->monthlyBasicBases($profile, $rates, $intervals, $start, $end);

        return ['lines' => $lineData, 'gross_cents' => $gross, 'taxable_cents' => $taxable, 'worked_minutes' => $minutes,
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
                if (! $rate || $rate->basis !== $profile->salary_type) {
                    continue;
                }
                $rateCents = $this->toCents($rate->rate);
                $monthlyRateCents = match ($profile->salary_type) {
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

        // BIR compensation tables are keyed to the actual payroll period, not
        // the employee's hourly/daily wage basis. There is no hourly tax table.
        $taxPeriod = match ($period->frequency) {
            'daily' => 'daily',
            'weekly' => 'weekly',
            'semimonthly' => 'semimonthly',
            'monthly' => 'monthly',
            'yearly' => 'yearly',
            default => throw ValidationException::withMessages([
                'frequency' => "The {$period->frequency} payroll frequency has no supported BIR withholding-tax table. Use an approved payroll-period frequency before calculating withholding tax.",
            ]),
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
        $effectiveRules = PayrollStatutoryRule::query()->where('code', $code)->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString()))
            ->orderByDesc('effective_from')->get();
        if ($effectiveRules->isEmpty()) {
            throw ValidationException::withMessages(['statutory_rules' => "No effective {$code} rule is configured for {$date->toDateString()}."]);
        }
        if ($effectiveRules->count() > 1) {
            throw ValidationException::withMessages(['statutory_rules' => "Multiple effective {$code} rules overlap on {$date->toDateString()}. Resolve the statutory rule dates before calculating payroll."]);
        }
        $rule = $effectiveRules->sole();
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
        return $rates->filter(fn (PayrollCompensationHistory $rate): bool => $rate->effective_from->lte($date)
            && (! $rate->effective_to || $rate->effective_to->gte($date)))->sortByDesc('effective_from')->first();
    }

    private function hourlyIntervalCents(Carbon $start, Carbon $end, Collection $rates, array &$flags): int
    {
        $total = 0;
        $cursor = $start->copy();
        while ($cursor->lt($end)) {
            $nextDay = $cursor->copy()->startOfDay()->addDay();
            $segmentEnd = $nextDay->lt($end) ? $nextDay : $end;
            $rate = $this->rateAt($rates, $cursor);
            if (! $rate || $rate->basis !== 'hourly') {
                $flags[] = 'missing_effective_hourly_rate';
            } else {
                $total += $this->roundRatio($this->toCents($rate->rate) * $cursor->diffInMinutes($segmentEnd), 60);
            }
            $cursor = $segmentEnd;
        }
        return $total;
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
                $open = $log;
                continue;
            }
            if ($log->type === 'clock_out' && $open instanceof AttendanceLog && $log->timestamp->gt($open->timestamp)) {
                $intervals[] = ['clock_in' => $open->timestamp->copy()->timezone(config('app.timezone')), 'clock_out' => $log->timestamp->copy()->timezone(config('app.timezone')),
                    'clock_in_id' => (int) $open->id, 'clock_out_id' => (int) $log->id, 'clock_in_paid' => (bool) $open->is_paid, 'clock_out_paid' => (bool) $log->is_paid];
                $open = null;
            }
        }
        return $intervals;
    }

    private function hasIncompleteVerifiedClockIn(Collection $logs, Carbon $start, Carbon $end): bool
    {
        $open = null;
        foreach ($logs as $log) {
            if ($log->status !== 'verified') {
                $open = null;
            } elseif ($log->type === 'clock_in') {
                $open = $log;
            } elseif ($log->type === 'clock_out') {
                $open = null;
            }
        }
        return $open instanceof AttendanceLog && $open->timestamp->between($start, $end);
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
