<?php

namespace App\Http\Controllers\Employer;

use App\Exports\PayrollRegisterExport;
use App\Http\Controllers\Controller;
use App\Models\PayrollAudit;
use App\Models\PayrollCompensationHistory;
use App\Models\PayrollEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\PayrollProfileItem;
use App\Models\User;
use App\Services\PayrollCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployerPayrollController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $this->companyId($request);
        $filters = $request->validate([
            'period_id' => 'nullable|integer', 'period_status' => 'nullable|in:draft,pending_review,approved,partially_paid,paid,voided',
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'search' => 'nullable|string|max:100',
            'employee_status' => 'nullable|string|in:active,pending,suspended,inactive',
        ]);

        $periodQuery = PayrollPeriod::query()->where('company_id', $companyId)->withCount('entries')->withSum('payments', 'amount');
        if (! empty($filters['period_status'])) {
            $periodQuery->where('status', $filters['period_status']);
        }
        if (! empty($filters['from'])) {
            $periodQuery->whereDate('period_start', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $periodQuery->whereDate('period_end', '<=', $filters['to']);
        }

        $periods = $periodQuery->orderByDesc('period_start')->paginate(10)->withQueryString();
        $selectedPeriod = null;
        $selectedPeriodId = $filters['period_id'] ?? $request->session()->get('selected_period_id');
        if (! empty($selectedPeriodId)) {
            $selectedPeriod = PayrollPeriod::query()->where('company_id', $companyId)->whereKey($selectedPeriodId)
                ->withCount('entries')->withCount('payments')->firstOrFail();
        }

        $entries = null;
        if ($selectedPeriod) {
            $entryQuery = $selectedPeriod->entries()->with(['employee', 'lines', 'payments'])->orderBy('user_id');
            if (! empty($filters['search'])) {
                $needle = $filters['search'];
                $entryQuery->whereHas('employee', fn ($query) => $query->where('name', 'like', '%'.$needle.'%')->orWhere('email', 'like', '%'.$needle.'%'));
            }
            if (! empty($filters['employee_status'])) {
                $entryQuery->whereHas('employee', fn ($query) => $query->where('status', $filters['employee_status']));
            }
            $entries = $entryQuery->paginate(25, ['*'], 'entries_page')->withQueryString();
            $entries->getCollection()->each(function (PayrollEntry $entry): void {
                $entry->setAttribute('remaining_balance', $this->fromCents(max(0, $this->toCents($entry->net_pay) - $this->toCents($entry->amount_paid))));
                $entry->setAttribute('payment_status', $this->paymentStatus($entry));
            });
            $selectedPeriod->setAttribute('employer_cost_total', $this->fromCents(
                $this->toCents($selectedPeriod->gross_total) + $this->toCents($selectedPeriod->employer_contribution_total),
            ));
        }
        $hasReviewFlags = $selectedPeriod?->entries()->whereNotNull('review_flags')->where('review_flags', '!=', '[]')->exists() ?? false;

        $statusCounts = PayrollPeriod::query()->where('company_id', $companyId)->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status');
        $deadlines = PayrollPeriod::query()->where('company_id', $companyId)->whereIn('status', ['approved', 'partially_paid'])
            ->whereDate('pay_date', '>=', now()->toDateString())->orderBy('pay_date')->limit(5)->get();

        return view('employer.payroll', [
            'company' => $request->user()->company,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'entries' => $entries,
            'hasReviewFlags' => $hasReviewFlags,
            'employees' => User::query()->where('company_id', $companyId)->whereRaw('LOWER(role) = ?', ['employee'])->orderBy('name')->get(['id', 'name', 'status']),
            'statusCounts' => $statusCounts,
            'deadlines' => $deadlines,
            'filters' => $filters,
        ]);
    }

    public function dashboardApi(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $period = null;
        if ($request->filled('period_id')) {
            $period = PayrollPeriod::query()->where('company_id', $companyId)->withCount('entries')->findOrFail($request->integer('period_id'));
        } else {
            $period = PayrollPeriod::query()->where('company_id', $companyId)->orderByDesc('period_start')->first();
        }
        $counts = PayrollPeriod::query()->where('company_id', $companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        $deadlines = PayrollPeriod::query()->where('company_id', $companyId)->whereIn('status', ['approved', 'partially_paid'])
            ->whereDate('pay_date', '>=', now()->toDateString())->orderBy('pay_date')->limit(5)->get();

        return response()->json([
            'selected_period' => $period ? $this->periodSummary($period) : null,
            'statuses' => $counts,
            'upcoming_deadlines' => $deadlines->map(fn (PayrollPeriod $item): array => [
                'id' => $item->id, 'period_start' => $item->period_start->toDateString(), 'period_end' => $item->period_end->toDateString(),
                'pay_date' => $item->pay_date?->toDateString(), 'status' => $item->status, 'net_total' => $item->net_total,
            ]),
        ]);
    }

    public function periodsApi(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $filters = $request->validate([
            'status' => 'nullable|in:draft,pending_review,approved,partially_paid,paid,voided', 'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = PayrollPeriod::query()->where('company_id', $companyId);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['from'])) $query->whereDate('period_start', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('period_end', '<=', $filters['to']);
        $periods = $query->withCount('entries')->orderByDesc('period_start')->paginate($filters['per_page'] ?? 20);
        return response()->json(['data' => $periods->getCollection()->map(fn (PayrollPeriod $period): array => $this->periodSummary($period)),
            'pagination' => ['current_page' => $periods->currentPage(), 'last_page' => $periods->lastPage(), 'per_page' => $periods->perPage(), 'total' => $periods->total()]]);
    }

    public function periodApi(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $this->assertCompany($request, $payrollPeriod->company_id);
        return response()->json(['data' => $this->periodDetails($payrollPeriod->load(['entries.employee', 'entries.lines']))]);
    }

    public function storePeriod(Request $request): JsonResponse|RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate([
            'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'pay_date' => 'nullable|date',
            'frequency' => 'required|in:hourly,daily,weekly,semimonthly,monthly,yearly',
        ]);
        $start = Carbon::parse($data['period_start'], config('app.timezone'))->startOfDay();
        $end = Carbon::parse($data['period_end'], config('app.timezone'))->startOfDay();
        if ($data['frequency'] === 'yearly') {
            if (! $start->isSameDay($start->copy()->startOfYear()) || ! $end->isSameDay($end->copy()->endOfYear()) || $start->year !== $end->year) {
                throw ValidationException::withMessages(['period_start' => 'A yearly run is reserved for a January 1 to December 31 13th-month pay run.']);
            }
        } elseif ($data['frequency'] === 'hourly' || $data['frequency'] === 'daily') {
            if (! $start->isSameDay($end)) {
                throw ValidationException::withMessages(['period_end' => 'Hourly and daily payroll runs must cover one calendar day.']);
            }
        } elseif ($data['frequency'] === 'weekly') {
            if ($start->diffInDays($end) !== 6) {
                throw ValidationException::withMessages(['period_end' => 'Weekly payroll periods must cover exactly seven calendar days.']);
            }
        } elseif ($data['frequency'] === 'semimonthly') {
            if ($start->diffInDays($end) > 15) {
                throw ValidationException::withMessages(['period_end' => 'Semimonthly payroll periods may cover no more than sixteen calendar days.']);
            }
        } elseif ($start->diffInDays($end) > 30) {
            throw ValidationException::withMessages(['period_end' => 'Monthly payroll periods may cover no more than thirty-one calendar days.']);
        }

        try {
            $period = DB::transaction(function () use ($companyId, $start, $end, $data, $request): PayrollPeriod {
                DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
                if ($data['frequency'] !== 'yearly') {
                    $overlap = PayrollPeriod::query()->where('company_id', $companyId)->where('frequency', '!=', 'yearly')
                        ->where('status', '!=', 'voided')->whereDate('period_start', '<=', $end->toDateString())
                        ->whereDate('period_end', '>=', $start->toDateString())->exists();
                    if ($overlap) {
                        throw ValidationException::withMessages(['period_start' => 'This period overlaps another active payroll period. Void the other draft or use a non-overlapping date range.']);
                    }
                }
                return PayrollPeriod::query()->create([
                    'company_id' => $companyId, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                    'pay_date' => $data['pay_date'] ?? null, 'frequency' => $data['frequency'], 'status' => 'draft', 'created_by' => $request->user()->id,
                ]);
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'payroll_period_identity_unique')) {
                throw ValidationException::withMessages(['period_start' => 'A payroll period with these dates and frequency already exists.']);
            }
            throw $exception;
        }
        $this->audit($period, $request->user(), 'period_created', ['period_start' => $period->period_start->toDateString(), 'period_end' => $period->period_end->toDateString()]);

        return $this->respond($request, ['message' => 'Payroll period created.', 'data' => $this->periodSummary($period)], 'Payroll period created.', $period);
    }

    public function calculatePeriod(Request $request, PayrollPeriod $payrollPeriod, PayrollCalculationService $calculator): JsonResponse|RedirectResponse
    {
        $this->assertCompany($request, $payrollPeriod->company_id);
        $period = $calculator->calculate($payrollPeriod, (int) $request->user()->id);
        return $this->respond($request, ['message' => 'Payroll calculated and is awaiting review.', 'data' => $this->periodDetails($period)], 'Payroll calculated. Review flags and line items before approval.', $period);
    }

    public function approvePeriod(Request $request, PayrollPeriod $payrollPeriod, PayrollCalculationService $calculator): JsonResponse|RedirectResponse
    {
        $this->assertCompany($request, $payrollPeriod->company_id);
        $data = $request->validate(['review_note' => 'nullable|string|max:1000']);
        $period = $calculator->approve($payrollPeriod, $request->user(), $data['review_note'] ?? null);
        return $this->respond($request, ['message' => 'Payroll approved.', 'data' => $this->periodDetails($period)], 'Payroll approved.', $period);
    }

    public function recordPayment(Request $request, PayrollEntry $payrollEntry): JsonResponse|RedirectResponse
    {
        $this->assertCompany($request, $payrollEntry->company_id);
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0|max:999999999.99', 'payment_date' => 'required|date',
            'method' => 'required|in:manual_cash,manual_bank_deposit,manual_cheque', 'reference_number' => 'nullable|string|max:120',
        ]);

        $payment = DB::transaction(function () use ($request, $payrollEntry, $data): PayrollPayment {
            $entry = PayrollEntry::query()->whereKey($payrollEntry->id)->lockForUpdate()->firstOrFail();
            $period = PayrollPeriod::query()->whereKey($entry->payroll_period_id)->lockForUpdate()->firstOrFail();
            if (! in_array($period->status, ['approved', 'partially_paid'], true)) {
                throw ValidationException::withMessages(['payroll' => 'A payment can only be recorded for approved payroll.']);
            }
            $amountCents = $this->toCents($data['amount']);
            $remaining = $this->toCents($entry->net_pay) - $this->toCents($entry->amount_paid);
            if ($amountCents <= 0 || $amountCents > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Payment must be positive and cannot exceed the employee’s remaining balance.']);
            }
            if (! empty($data['reference_number']) && PayrollPayment::query()->where('company_id', $entry->company_id)->where('reference_number', $data['reference_number'])->exists()) {
                throw ValidationException::withMessages(['reference_number' => 'That payment reference has already been recorded.']);
            }

            $payment = PayrollPayment::query()->create([
                'company_id' => $entry->company_id, 'payroll_period_id' => $period->id, 'payroll_entry_id' => $entry->id,
                'amount' => $this->fromCents($amountCents), 'payment_date' => $data['payment_date'], 'method' => $data['method'],
                'reference_number' => $data['reference_number'] ?: null, 'recorded_by' => $request->user()->id,
            ]);
            $paid = $this->toCents($entry->amount_paid) + $amountCents;
            $entry->update(['amount_paid' => $this->fromCents($paid)]);
            $entries = PayrollEntry::query()->where('payroll_period_id', $period->id)->get();
            $paidTotal = $entries->sum(fn (PayrollEntry $item): int => $this->toCents($item->amount_paid));
            $fullyPaid = $entries->every(fn (PayrollEntry $item): bool => $this->toCents($item->amount_paid) >= $this->toCents($item->net_pay));
            $hasPaid = $paidTotal > 0;
            $period->update(['paid_total' => $this->fromCents($paidTotal), 'status' => $fullyPaid ? 'paid' : ($hasPaid ? 'partially_paid' : 'approved')]);

            if ($this->toCents($entry->amount_paid) >= $this->toCents($entry->net_pay)) {
                $entry->attendanceLogs()->update(['is_paid' => true]);
            }
            $this->audit($period, $request->user(), 'payment_recorded', ['entry_id' => $entry->id, 'payment_id' => $payment->id, 'amount' => $payment->amount, 'method' => $payment->method]);
            return $payment;
        });

        return $this->respond($request, ['message' => 'Payment recorded; TimePay does not initiate a transfer.', 'data' => $payment], 'Manual payment recorded.', $payrollEntry->period);
    }

    public function markAsPaid(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_unless((int) $user->company_id === $companyId && strtoupper((string) $user->role) === User::ROLE_EMPLOYEE, 404);
        $data = $request->validate(['period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start']);
        $period = PayrollPeriod::query()->where('company_id', $companyId)->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])->whereIn('status', ['approved', 'partially_paid'])->first();
        abort_unless($period, 409, 'Calculate and approve a payroll period before recording a payment.');
        $entry = PayrollEntry::query()->where('payroll_period_id', $period->id)->where('user_id', $user->id)->firstOrFail();
        $remainingCents = $this->toCents($entry->net_pay) - $this->toCents($entry->amount_paid);
        abort_if($remainingCents <= 0, 409, 'This payroll entry has no remaining balance.');
        $request->merge([
            'amount' => $this->fromCents($remainingCents), 'payment_date' => now()->toDateString(),
            'method' => $user->payment_method ?: 'manual_cash', 'reference_number' => null,
        ]);
        return $this->recordPayment($request, $entry);
    }

    public function voidPeriod(Request $request, PayrollPeriod $payrollPeriod): JsonResponse|RedirectResponse
    {
        $this->assertCompany($request, $payrollPeriod->company_id);
        DB::transaction(function () use ($payrollPeriod, $request): void {
            $period = PayrollPeriod::query()->whereKey($payrollPeriod->id)->lockForUpdate()->firstOrFail();
            if (! in_array($period->status, ['draft', 'pending_review', 'approved'], true) || $period->payments()->exists()) {
                throw ValidationException::withMessages(['status' => 'Payroll can only be voided before any payment is recorded.']);
            }
            $before = ['status' => $period->status];
            $period->update(['status' => 'voided']);
            $this->audit($period, $request->user(), 'period_voided', ['before' => $before, 'after' => ['status' => 'voided']]);
        });
        return $this->respond($request, ['message' => 'Payroll period voided.', 'data' => ['id' => $payrollPeriod->id, 'status' => 'voided']], 'Payroll period voided.', $payrollPeriod);
    }

    public function updateEmployeeRate(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $companyId = $this->companyId($request);
        abort_unless((int) $user->company_id === $companyId && strtoupper((string) $user->role) === User::ROLE_EMPLOYEE, 404);
        $data = $request->validate([
            'salary_type' => 'sometimes|in:hourly,daily,monthly', 'rate' => 'nullable|numeric|min:0|max:99999999.99',
            'hourly_rate' => 'nullable|numeric|min:0|max:999999.99', 'daily_rate' => 'nullable|numeric|min:0|max:999999.99',
            'pay_frequency' => 'sometimes|in:hourly,daily,weekly,semimonthly,monthly,yearly',
            'effective_from' => 'nullable|date', 'payment_method' => 'nullable|in:manual_cash,manual_bank_deposit,manual_cheque',
            'overtime_eligible' => 'sometimes|nullable|boolean', 'payroll_eligible' => 'sometimes|nullable|boolean', 'rank_and_file' => 'sometimes|nullable|boolean',
            'standard_hours_per_day' => 'sometimes|integer|min:1|max:24', 'standard_work_days_per_month' => 'sometimes|integer|min:1|max:31',
            'sss_enabled' => 'sometimes|nullable|boolean', 'philhealth_enabled' => 'sometimes|nullable|boolean', 'pagibig_enabled' => 'sometimes|nullable|boolean', 'withholding_tax_enabled' => 'sometimes|nullable|boolean',
        ]);
        $profile = PayrollProfile::query()->firstOrCreate(
            ['user_id' => $user->id], ['company_id' => $companyId, 'salary_type' => 'hourly', 'pay_frequency' => 'monthly'],
        );

        $basis = $data['salary_type'] ?? $profile->salary_type;
        $rateValue = $data['rate'] ?? match ($basis) {
            'daily' => $data['daily_rate'] ?? null,
            'hourly' => $data['hourly_rate'] ?? null,
            default => null,
        };
        $effectiveFrom = Carbon::parse($data['effective_from'] ?? now()->toDateString(), config('app.timezone'))->toDateString();

        if ($rateValue !== null) {
            if (PayrollPeriod::query()->where('company_id', $companyId)->whereIn('status', ['approved', 'partially_paid', 'paid'])->whereDate('period_end', '>=', $effectiveFrom)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'A compensation change cannot overlap a finalized payroll period.']);
            }
            $this->saveRateHistory($user, $basis, $rateValue, $effectiveFrom, $request->user());
            $profile->salary_type = $basis;
            $legacyRates = $basis === 'daily' ? ['daily_rate' => $rateValue] : ($basis === 'hourly' ? ['hourly_rate' => $rateValue] : []);
            if ($legacyRates !== []) {
                $user->forceFill($legacyRates)->save();
            }
        }

        $profileValues = collect($data)->only([
            'pay_frequency', 'overtime_eligible', 'payroll_eligible', 'rank_and_file', 'standard_hours_per_day', 'standard_work_days_per_month',
            'sss_enabled', 'philhealth_enabled', 'pagibig_enabled', 'withholding_tax_enabled',
        ])->reject(fn ($value): bool => $value === null)->all();
        $profile->fill($profileValues)->save();
        if (array_key_exists('payment_method', $data)) {
            $user->forceFill(['payment_method' => $data['payment_method']])->save();
        }
        $this->auditForUser($companyId, $request->user(), 'compensation_profile_updated', $user, ['basis' => $basis, 'effective_from' => $effectiveFrom]);

        return $this->respond($request, ['message' => 'Payroll profile updated.', 'data' => ['employee_id' => $user->id, 'salary_type' => $profile->salary_type,
            'pay_frequency' => $profile->pay_frequency, 'current_rate' => $rateValue]], 'Employee payroll profile updated.', null);
    }

    public function updateSelectedEmployeeRate(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        return $this->updateEmployeeRate($request, $this->companyEmployee($request, (int) $data['user_id']));
    }

    public function storeProfileItem(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $companyId = $this->companyId($request);
        abort_unless((int) $user->company_id === $companyId && strtoupper((string) $user->role) === User::ROLE_EMPLOYEE, 404);
        $data = $request->validate([
            'category' => 'required|in:earning,deduction',
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'label' => 'required|string|max:120',
            'amount' => 'required|numeric|gt:0|max:99999999.99',
            'taxable' => 'sometimes|boolean',
            'authorization_reference' => 'required_if:category,deduction|nullable|string|max:120',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);
        if ($data['category'] === 'earning' && array_key_exists('taxable', $data) && ! (bool) $data['taxable']) {
            throw ValidationException::withMessages([
                'taxable' => 'Non-taxable recurring benefits are not supported until benefit-specific tax rules and cumulative limits are configured. Save this item as taxable or wait for the verified benefit rules.',
            ]);
        }

        $effectiveFrom = Carbon::parse($data['effective_from'], config('app.timezone'))->toDateString();
        $effectiveTo = isset($data['effective_to']) ? Carbon::parse($data['effective_to'], config('app.timezone'))->toDateString() : null;

        $item = DB::transaction(function () use ($companyId, $user, $request, $data, $effectiveFrom, $effectiveTo): PayrollProfileItem {
            User::query()->where('company_id', $companyId)->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $finalizedOverlap = PayrollPeriod::query()->where('company_id', $companyId)->whereIn('status', ['approved', 'partially_paid', 'paid'])
                ->whereDate('period_end', '>=', $effectiveFrom)
                ->when($effectiveTo, fn ($query) => $query->whereDate('period_start', '<=', $effectiveTo))
                ->exists();
            if ($finalizedOverlap) {
                throw ValidationException::withMessages(['effective_from' => 'Recurring payroll items cannot be changed within a finalized payroll period.']);
            }

            $items = PayrollProfileItem::query()->where('company_id', $companyId)->where('user_id', $user->id)
                ->where('category', $data['category'])->where('code', $data['code']);
            $atStart = (clone $items)->whereDate('effective_from', $effectiveFrom)->first();
            $next = (clone $items)->whereDate('effective_from', '>', $effectiveFrom)->orderBy('effective_from')->first();
            $end = $effectiveTo ?? $next?->effective_from->copy()->subDay()->toDateString();
            if ($end && $next && Carbon::parse($end)->gte($next->effective_from)) {
                throw ValidationException::withMessages(['effective_to' => 'This effective range overlaps a later version of the same payroll item.']);
            }

            $before = $atStart?->only(['label', 'amount', 'taxable', 'authorization_reference', 'effective_from', 'effective_to']);
            if (! $atStart) {
                $previous = (clone $items)->whereDate('effective_from', '<', $effectiveFrom)
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom))
                    ->orderByDesc('effective_from')->first();
                if ($previous) {
                    $previous->update(['effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString()]);
                }
                $atStart = new PayrollProfileItem([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'category' => $data['category'],
                    'code' => $data['code'],
                    'effective_from' => $effectiveFrom,
                    'created_by' => $request->user()->id,
                ]);
            }

            $atStart->fill([
                'label' => $data['label'],
                'amount' => $data['amount'],
                'taxable' => $data['category'] === 'earning' ? (bool) ($data['taxable'] ?? true) : false,
                'authorization_reference' => $data['category'] === 'deduction' ? $data['authorization_reference'] : null,
                'effective_to' => $end,
                'created_by' => $request->user()->id,
            ])->save();

            $this->auditForUser($companyId, $request->user(), 'payroll_item_updated', $user, [
                'item_id' => $atStart->id,
                'category' => $atStart->category,
                'code' => $atStart->code,
                'before' => $before,
                'after' => $atStart->only(['label', 'amount', 'taxable', 'authorization_reference', 'effective_from', 'effective_to']),
            ]);

            return $atStart;
        });

        return $this->respond($request, ['message' => 'Recurring payroll item saved.', 'data' => $item], 'Recurring payroll item saved.', null);
    }

    public function storeSelectedProfileItem(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        return $this->storeProfileItem($request, $this->companyEmployee($request, (int) $data['user_id']));
    }

    public function exportPeriodXlsx(Request $request, PayrollPeriod $payrollPeriod): BinaryFileResponse
    {
        $this->assertCompany($request, $payrollPeriod->company_id);
        abort_unless(in_array($payrollPeriod->status, ['approved', 'partially_paid', 'paid'], true), 409, 'Payroll exports are available only after approval.');
        $period = $payrollPeriod->load(['entries.employee', 'entries.lines']);
        return Excel::download(new PayrollRegisterExport($period), 'timepay-payroll-'.$period->period_start->format('Ymd').'-'.$period->period_end->format('Ymd').'.xlsx');
    }

    public function exportPeriodPdf(Request $request, PayrollPeriod $payrollPeriod)
    {
        $this->assertCompany($request, $payrollPeriod->company_id);
        abort_unless(in_array($payrollPeriod->status, ['approved', 'partially_paid', 'paid'], true), 409, 'Payroll exports are available only after approval.');
        $period = $payrollPeriod->load(['entries.employee', 'entries.lines']);
        $company = $request->user()->company;
        $companyName = data_get($period->entries->first()?->calculation_snapshot, 'company_snapshot.name', $company->name);
        return Pdf::loadView('pdfs.payroll-register', compact('period', 'company', 'companyName'))->setPaper('a4', 'landscape')
            ->download('timepay-payroll-'.$period->period_start->format('Ymd').'-'.$period->period_end->format('Ymd').'.pdf');
    }

    public function report(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $filters = $request->validate([
            'view' => 'required|in:hourly,daily,weekly,monthly,yearly', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'employee_id' => 'nullable|integer',
        ]);
        $from = $filters['from'] ?? now()->startOfYear()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 366) {
            throw ValidationException::withMessages(['to' => 'Payroll reports are limited to 366 days per request.']);
        }
        if (! empty($filters['employee_id'])) {
            abort_unless(User::query()->where('company_id', $companyId)->whereRaw('LOWER(role) = ?', ['employee'])->whereKey($filters['employee_id'])->exists(), 404);
        }
        $query = PayrollEntry::query()->where('company_id', $companyId)->whereHas('period', fn ($period) => $period
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])->whereDate('period_start', '>=', $from)->whereDate('period_end', '<=', $to));
        if (! empty($filters['employee_id'])) {
            $query->where('user_id', $filters['employee_id']);
        }
        $entries = $query->with(['employee', 'lines', 'period'])->orderBy('user_id')->get();
        if ($entries->count() > 5000) {
            throw ValidationException::withMessages(['employee_id' => 'Narrow the date range or select an employee; report exports are limited to 5,000 payroll entries.']);
        }
        $groups = match ($filters['view']) {
            'hourly' => $entries->groupBy(fn (PayrollEntry $entry): string => $entry->period->period_start->format('Y-m-d')),
            'daily' => $entries->groupBy(fn (PayrollEntry $entry): string => $entry->period->period_start->format('Y-m-d')),
            'weekly' => $entries->groupBy(fn (PayrollEntry $entry): string => $entry->period->period_start->startOfWeek()->toDateString()),
            'monthly' => $entries->groupBy(fn (PayrollEntry $entry): string => $entry->period->period_start->format('Y-m')),
            'yearly' => $entries->groupBy(fn (PayrollEntry $entry): string => $entry->period->period_start->format('Y')),
        };
        return response()->json(['view' => $filters['view'], 'from' => $from, 'to' => $to, 'data' => $groups->map(function ($items, $key) use ($filters): array {
            $result = [
                'period' => $key,
                'employee_count' => $items->pluck('user_id')->unique()->count(),
                'worked_minutes' => $items->sum('worked_minutes'),
                'gross_pay' => $this->fromCents($items->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->gross_pay))),
                'employee_deductions' => $this->fromCents($items->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->employee_deductions))),
                'employer_contributions' => $this->fromCents($items->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->employer_contributions))),
                'net_pay' => $this->fromCents($items->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->net_pay))),
                'employer_cost' => $this->fromCents($items->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->employer_cost))),
            ];
            if (! empty($filters['employee_id'])) {
                $result['entries'] = $items->map(function (PayrollEntry $entry): array {
                    $lines = $entry->lines;
                    return [
                        'employee_id' => $entry->user_id,
                        'employee_name' => data_get($entry->calculation_snapshot, 'employee_snapshot.name', $entry->employee?->name),
                        'period_start' => $entry->period->period_start->toDateString(),
                        'period_end' => $entry->period->period_end->toDateString(),
                        'worked_minutes' => $entry->worked_minutes,
                        'payable_days' => $entry->payable_days,
                        'gross_pay' => $entry->gross_pay,
                        'employee_deductions' => $entry->employee_deductions,
                        'employer_contributions' => $entry->employer_contributions,
                        'net_pay' => $entry->net_pay,
                        'amount_paid' => $entry->amount_paid,
                        'remaining_balance' => $this->fromCents(max(0, $this->toCents($entry->net_pay) - $this->toCents($entry->amount_paid))),
                        'payment_status' => $this->paymentStatus($entry),
                        'lines' => $lines->map(fn ($line): array => ['category' => $line->category, 'code' => $line->code, 'label' => $line->label, 'amount' => $line->amount, 'taxable' => $line->taxable]),
                    ];
                })->values();
            }
            return $result;
        })->values()]);
    }

    public function auditHistory(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $this->assertCompany($request, $payrollPeriod->company_id);
        $items = PayrollAudit::query()->where('company_id', $payrollPeriod->company_id)->where('entity_id', $payrollPeriod->id)
            ->where('entity_type', 'payroll_period')->with('actor')->orderByDesc('created_at')->get();
        return response()->json(['data' => $items->map(fn (PayrollAudit $audit): array => [
            'action' => $audit->action, 'actor_id' => $audit->actor_id, 'created_at' => $audit->created_at?->toIso8601String(), 'details' => $audit->after_data,
        ])]);
    }

    private function companyId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user && in_array(strtoupper((string) $user->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403, 'Employer payroll access is required.');
        abort_unless($user->company_id, 403, 'A company must be assigned to this account.');
        return (int) $user->company_id;
    }

    private function assertCompany(Request $request, int|string|null $companyId): void
    {
        abort_unless($companyId !== null && (int) $companyId === $this->companyId($request), 404);
    }

    private function companyEmployee(Request $request, int $userId): User
    {
        $companyId = $this->companyId($request);
        return User::query()->where('company_id', $companyId)->whereRaw('LOWER(role) = ?', ['employee'])->whereKey($userId)->firstOrFail();
    }

    private function saveRateHistory(User $employee, string $basis, int|float|string $amount, string $effectiveFrom, User $actor): void
    {
        $date = Carbon::parse($effectiveFrom, config('app.timezone'));
        $history = PayrollCompensationHistory::query()->where('company_id', $employee->company_id)->where('user_id', $employee->id)
            ->whereDate('effective_from', $effectiveFrom)->first();
        if ($history) {
            $history->update(['basis' => $basis, 'rate' => $amount, 'changed_by' => $actor->id]);
        } else {
            PayrollCompensationHistory::query()->where('company_id', $employee->company_id)->where('user_id', $employee->id)
                ->whereDate('effective_from', '<', $effectiveFrom)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom))
                ->update(['effective_to' => $date->copy()->subDay()->toDateString()]);
            $next = PayrollCompensationHistory::query()->where('company_id', $employee->company_id)->where('user_id', $employee->id)
                ->whereDate('effective_from', '>', $effectiveFrom)->orderBy('effective_from')->first();
            PayrollCompensationHistory::query()->create([
                'company_id' => $employee->company_id, 'user_id' => $employee->id, 'basis' => $basis, 'rate' => $amount,
                'effective_from' => $effectiveFrom, 'effective_to' => $next?->effective_from->copy()->subDay()->toDateString(), 'changed_by' => $actor->id,
            ]);
        }
    }

    private function periodSummary(PayrollPeriod $period): array
    {
        return [
            'id' => $period->id, 'period_start' => $period->period_start?->toDateString(), 'period_end' => $period->period_end?->toDateString(),
            'pay_date' => $period->pay_date?->toDateString(), 'frequency' => $period->frequency, 'status' => $period->status,
            'employee_count' => $period->entries_count ?? $period->entries()->count(), 'gross_pay' => $period->gross_total,
            'employee_deductions' => $period->deduction_total, 'employer_contributions' => $period->employer_contribution_total,
            'net_pay' => $period->net_total, 'paid' => $period->paid_total,
            'remaining_balance' => $this->fromCents(max(0, $this->toCents($period->net_total) - $this->toCents($period->paid_total))),
            'employer_cost' => $this->fromCents($this->toCents($period->gross_total) + $this->toCents($period->employer_contribution_total)),
        ];
    }

    private function periodDetails(PayrollPeriod $period): array
    {
        return $this->periodSummary($period) + ['entries' => $period->entries->map(fn (PayrollEntry $entry): array => [
            'id' => $entry->id, 'employee' => ['id' => $entry->user_id, 'name' => data_get($entry->calculation_snapshot, 'employee_snapshot.name', $entry->employee?->name)],
            'worked_minutes' => $entry->worked_minutes, 'payable_days' => $entry->payable_days, 'gross_pay' => $entry->gross_pay,
            'employee_deductions' => $entry->employee_deductions, 'employer_contributions' => $entry->employer_contributions,
            'net_pay' => $entry->net_pay, 'amount_paid' => $entry->amount_paid, 'remaining_balance' => $this->fromCents(max(0, $this->toCents($entry->net_pay) - $this->toCents($entry->amount_paid))),
            'payment_status' => $this->paymentStatus($entry),
            'review_flags' => $entry->review_flags ?? [], 'lines' => $entry->lines->map(fn ($line): array => ['category' => $line->category, 'code' => $line->code, 'label' => $line->label, 'amount' => $line->amount, 'taxable' => $line->taxable]),
        ])->all()];
    }

    private function audit(PayrollPeriod $period, User $actor, string $action, array $data): void
    {
        PayrollAudit::query()->create(['company_id' => $period->company_id, 'actor_id' => $actor->id, 'action' => $action,
            'entity_type' => 'payroll_period', 'entity_id' => $period->id, 'after_data' => $data]);
    }

    private function auditForUser(int $companyId, User $actor, string $action, User $employee, array $data): void
    {
        PayrollAudit::query()->create(['company_id' => $companyId, 'actor_id' => $actor->id, 'action' => $action,
            'entity_type' => 'payroll_profile', 'entity_id' => $employee->id, 'after_data' => $data]);
    }

    private function respond(Request $request, array $json, string $message, ?PayrollPeriod $period): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($json);
        }
        $redirect = redirect()->route('employer.payroll');
        if ($period) {
            $redirect = $redirect->with('selected_period_id', $period->id);
        }
        return $redirect->with('success', $message);
    }

    private function toCents(int|float|string|null $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{0,}))?$/', $value, $parts)) {
            return 0;
        }
        $fraction = str_pad(substr($parts[3] ?? '', 0, 3), 3, '0');
        $cents = (int) $parts[2] * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);
        return ($parts[1] ?? '') === '-' ? -$cents : $cents;
    }

    private function fromCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);
        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function paymentStatus(PayrollEntry $entry): string
    {
        $net = $this->toCents($entry->net_pay);
        $paid = $this->toCents($entry->amount_paid);

        return $net <= 0 ? 'no_payment_due' : ($paid >= $net ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'));
    }
}
