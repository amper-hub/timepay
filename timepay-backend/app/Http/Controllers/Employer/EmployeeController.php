<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\PayrollCompensationHistory;
use App\Models\PayrollAudit;
use App\Models\PayrollEntry;
use App\Models\PayrollProfile;
use App\Models\PayrollPeriod;
use App\Models\AttendanceLog;
use App\Support\PayrollReviewFlags;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Display employees belonging to the authenticated employer's company.
     */
    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor && in_array(strtoupper((string) $actor->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403);
        abort_unless($actor->company_id, 403, 'A company must be assigned to this account.');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'integer'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $employees = User::query()
            ->where('company_id', $actor->company_id)
            ->whereRaw('LOWER(role) = ?', ['employee'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            }))
            ->with('payrollProfile')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
        $asOf = Carbon::today(config('app.timezone'));
        $currentRates = PayrollCompensationHistory::query()->where('company_id', $actor->company_id)
            ->whereIn('user_id', $employees->getCollection()->pluck('id'))
            ->whereDate('effective_from', '<=', $asOf)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOf))
            ->orderByDesc('effective_from')->get()->unique('user_id')->keyBy('user_id');
        $employees->getCollection()->each(function (User $employee) use ($currentRates): void {
            $employee->setAttribute('current_compensation', $currentRates->get($employee->id));
            $path = $employee->baseline_photo_path;
            $safePath = $path && ! str_contains(str_replace('\\', '/', $path), '..')
                ? str($path)->replace('\\', '/')->replaceStart('storage/', '')->toString()
                : null;
            $enrolled = $safePath !== null
                && (Storage::disk('private')->exists($safePath) || Storage::disk('public')->exists($safePath));
            $employee->setAttribute('baseline_enrolled', $enrolled);
        });

        $periods = PayrollPeriod::query()->where('company_id', $actor->company_id)
            ->orderByDesc('period_start')->limit(12)->get();
        $selectedPeriod = null;
        if ($periods->isNotEmpty()) {
            $selectedPeriod = $validated['period_id'] ?? $periods->first()->id;
            $selectedPeriod = $periods->firstWhere('id', (int) $selectedPeriod);
            abort_unless($selectedPeriod, 404);
        }

        $attendanceSummary = collect();
        $payrollEntries = collect();
        if ($selectedPeriod && $employees->isNotEmpty()) {
            $employeeIds = $employees->getCollection()->pluck('id');
            $from = $selectedPeriod->period_start->copy()->startOfDay()->subDay();
            $until = $selectedPeriod->period_end->copy()->addDay()->addDay()->startOfDay();
            $logs = AttendanceLog::query()
                ->where('company_id', $actor->company_id)->whereIn('user_id', $employeeIds)
                ->where('timestamp', '>=', $from)->where('timestamp', '<', $until)
                ->orderBy('user_id')->orderBy('timestamp')->orderBy('id')->get();
            $periodStart = $selectedPeriod->period_start->copy()->startOfDay();
            $periodEndExclusive = $selectedPeriod->period_end->copy()->addDay()->startOfDay();

            foreach ($logs->groupBy('user_id') as $userId => $employeeLogs) {
                $inRange = $employeeLogs->filter(fn ($log): bool =>
                    $log->timestamp->greaterThanOrEqualTo($periodStart)
                    && $log->timestamp->lessThan($periodEndExclusive)
                );
                $open = null;
                $incomplete = 0;
                $crossesPeriodBoundary = false;
                foreach ($employeeLogs as $log) {
                    if ($log->status !== 'verified' || $log->is_suspicious) {
                        continue;
                    }
                    if ($log->type === 'clock_in') {
                        if ($open) $incomplete++;
                        $open = $log;
                    } elseif ($log->type === 'clock_out') {
                        if ($open && $log->timestamp->greaterThan($open->timestamp)) {
                            if (($open->timestamp->lt($periodStart) && $log->timestamp->gte($periodStart))
                                || ($open->timestamp->lt($periodEndExclusive) && $log->timestamp->gte($periodEndExclusive))) {
                                $crossesPeriodBoundary = true;
                            }
                            $open = null;
                        } else $incomplete++;
                    }
                }
                if ($open) $incomplete++;
                $attendanceSummary[(int) $userId] = [
                    'events' => $inRange->count(),
                    'shifts' => $inRange->where('type', 'clock_in')->count(),
                    'review' => $inRange->filter(fn ($log): bool => $log->status !== 'verified' || $log->is_suspicious)->count() + $incomplete + (int) $crossesPeriodBoundary,
                    'boundary' => $crossesPeriodBoundary,
                ];
            }

            $payrollEntries = PayrollEntry::query()->where('company_id', $actor->company_id)
                ->where('payroll_period_id', $selectedPeriod->id)->whereIn('user_id', $employeeIds)
                ->get()->keyBy('user_id');
        }

        return view('employer.employees.index', [
            'employees' => $employees,
            'search' => $search,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'attendanceSummary' => $attendanceSummary,
            'payrollEntries' => $payrollEntries,
            'criticalReviewFlagCodes' => PayrollReviewFlags::critical(),
            'currency' => $actor->company?->currency ?: 'PHP',
        ]);
    }

    /** Show a company-scoped employee profile and its existing payroll settings. */
    public function show(Request $request, User $user): View
    {
        $actor = $request->user();
        abort_unless($actor && in_array(strtoupper((string) $actor->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403);
        abort_unless($actor->company_id, 403, 'A company must be assigned to this account.');

        $employee = User::query()->where('company_id', $actor->company_id)
            ->whereRaw('LOWER(role) = ?', ['employee'])
            ->with(['payrollProfile.items', 'compensationHistory'])
            ->whereKey($user->id)->firstOrFail();
        $today = Carbon::today(config('app.timezone'));
        $currentRate = $employee->compensationHistory
            ->filter(fn ($rate): bool => $rate->effective_from->lte($today)
                && (! $rate->effective_to || $rate->effective_to->gte($today)))
            ->sortByDesc('effective_from')->first();

        return view('employer.employees.show', [
            'employee' => $employee,
            'currentRate' => $currentRate,
            'frequencyLabels' => [
                'hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly',
                'semimonthly' => 'Semi-monthly', 'monthly' => 'Monthly', 'yearly' => 'Yearly',
            ],
        ]);
    }

    /** Reset one employee's baseline under employer company authorization. */
    public function resetBaseline(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && in_array(strtoupper((string) $actor->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403);
        abort_unless($actor->company_id, 403);
        abort_unless((int) $user->company_id === (int) $actor->company_id && strtolower((string) $user->role) === 'employee', 404);

        $baselinePath = $user->baseline_photo_path;
        $normalizedPath = $baselinePath ? str($baselinePath)->replace('\\', '/')->replaceStart('storage/', '')->toString() : null;
        abort_if($normalizedPath && (str_contains($normalizedPath, '..') || str_starts_with($normalizedPath, '/')), 422, 'The stored baseline path is invalid.');

        $staged = [];
        $privateDisk = Storage::disk('private');
        try {
            if ($normalizedPath) {
                foreach (['private', 'public'] as $diskName) {
                    $disk = Storage::disk($diskName);
                    if (! $disk->exists($normalizedPath)) continue;
                    $stagedPath = 'baseline-reset-pending/'.Str::uuid().'.bin';
                    if ($diskName === 'private') {
                        if (! $privateDisk->move($normalizedPath, $stagedPath)) throw new \RuntimeException('Unable to stage the baseline file.');
                    } else {
                        if (! $privateDisk->put($stagedPath, $disk->get($normalizedPath))) throw new \RuntimeException('Unable to stage the baseline file securely.');
                        if (! $disk->delete($normalizedPath)) {
                            $privateDisk->delete($stagedPath);
                            throw new \RuntimeException('Unable to remove the public baseline file.');
                        }
                    }
                    $staged[] = [$diskName, $stagedPath, $normalizedPath];
                }
            }

            DB::transaction(function () use ($actor, $user, $baselinePath): void {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $locked->company_id === (int) $actor->company_id && strtolower((string) $locked->role) === 'employee', 404);
                if ($locked->baseline_photo_path !== $baselinePath) throw new \RuntimeException('The employee baseline changed during reset. Please retry.');
                $locked->forceFill(['baseline_photo_path' => null, 'cloud_face_id' => null])->save();
                \App\Models\AttendanceEventResponse::query()->where('user_id', $locked->id)
                    ->whereJsonContains('response->face_verification->enrolled', true)->delete();
                PayrollAudit::query()->create([
                    'company_id' => $actor->company_id,
                    'actor_id' => $actor->id,
                    'action' => 'employee_face_baseline_reset',
                    'entity_type' => User::class,
                    'entity_id' => $locked->id,
                    'before_data' => ['baseline_configured' => $baselinePath !== null],
                    'after_data' => ['baseline_configured' => false],
                ]);
            });
        } catch (\Throwable $exception) {
            foreach (array_reverse($staged) as [$diskName, $stagedPath, $originalPath]) {
                try {
                    if ($diskName === 'private') $privateDisk->move($stagedPath, $originalPath);
                    else {
                        Storage::disk($diskName)->put($originalPath, $privateDisk->get($stagedPath));
                        $privateDisk->delete($stagedPath);
                    }
                } catch (\Throwable) {}
            }
            throw $exception;
        }

        $cleanupSucceeded = true;
        foreach ($staged as [$diskName, $stagedPath]) {
            try { $cleanupSucceeded = $privateDisk->delete($stagedPath) && $cleanupSucceeded; }
            catch (\Throwable) { $cleanupSucceeded = false; }
        }

        return redirect()->back()->with($cleanupSucceeded ? 'success' : 'error', $cleanupSucceeded
            ? 'Baseline photo reset. The employee must enroll again at their next attendance punch.'
            : 'Baseline access was reset, but secure file cleanup did not complete. Contact support before re-enrollment.');
    }

    /**
     * Create a new employee with a temporary password.
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor && in_array(strtoupper((string) $actor->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403);
        abort_unless($actor->company_id, 403, 'A company must be assigned to this account.');
        $companyId = (int) $actor->company_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'hourly_rate' => 'required_without_all:daily_rate,monthly_rate|nullable|numeric|min:0|max:999999.99',
            'salary_type' => 'sometimes|in:hourly,daily,monthly',
            'daily_rate' => 'required_if:salary_type,daily|nullable|numeric|min:0|max:99999999.99',
            'monthly_rate' => 'required_if:salary_type,monthly|nullable|numeric|min:0|max:99999999.99',
            'pay_frequency' => 'sometimes|in:hourly,daily,weekly,semimonthly,monthly',
            'payment_method' => 'nullable|in:manual_cash,manual_bank_deposit,manual_cheque',
        ]);

        $validated['email'] = mb_strtolower(trim($validated['email']));
        $temporaryPassword = Str::password(12);
        $salaryType = $validated['salary_type'] ?? 'hourly';
        $rate = match ($salaryType) {
            'daily' => $validated['daily_rate'],
            'monthly' => $validated['monthly_rate'],
            default => $validated['hourly_rate'],
        };
        $employee = DB::transaction(function () use ($validated, $companyId, $actor, $temporaryPassword, $salaryType, $rate): User {
            $employee = User::create([
                'company_id' => $companyId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $temporaryPassword,
                'role' => 'employee',
                'hourly_rate' => $validated['hourly_rate'] ?? 0,
                'daily_rate' => $validated['daily_rate'] ?? 0,
                'payment_method' => $validated['payment_method'] ?? 'manual_cash',
                'requires_password_change' => true,
            ]);

            PayrollProfile::query()->create([
                'company_id' => $companyId,
                'user_id' => $employee->id,
                'salary_type' => $salaryType,
                'pay_frequency' => $validated['pay_frequency'] ?? 'monthly',
            ]);
            PayrollCompensationHistory::query()->create([
                'company_id' => $companyId,
                'user_id' => $employee->id,
                'basis' => $salaryType,
                'rate' => $rate,
                'effective_from' => Carbon::now(config('app.timezone'))->toDateString(),
                'changed_by' => $actor->id,
            ]);

            return $employee;
        });

        return response()->json([
            'message' => 'Employee created successfully.',
            'temporary_password' => $temporaryPassword,
            'user' => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => $employee->role,
                'salary_type' => $salaryType,
                'rate' => (string) $rate,
                'pay_frequency' => $validated['pay_frequency'] ?? 'monthly',
                'hourly_rate' => $employee->hourly_rate,
                'payment_method' => $employee->payment_method,
                'requires_password_change' => $employee->requires_password_change,
            ],
        ], 201);
    }

    /**
     * Deactivate an employee account without deleting its historical records.
     */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && in_array(strtoupper((string) $actor->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403);
        if (
            (int) $user->company_id !== (int) $actor->company_id
            || strtolower((string) $user->role) !== 'employee'
        ) {
            abort(403, 'Unauthorized');
        }

        if (strtolower((string) $user->status) === 'inactive') {
            return redirect()->back()->with('success', 'Employee account is already deactivated.');
        }

        DB::transaction(function () use ($actor, $user): void {
            $before = ['status' => $user->status ?? 'active'];
            $user->status = 'inactive';
            $user->save();
            $user->tokens()->delete();

            PayrollAudit::query()->create([
                'company_id' => $actor->company_id,
                'actor_id' => $actor->id,
                'action' => 'employee_account_deactivated',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'before_data' => $before,
                'after_data' => ['status' => 'inactive'],
            ]);
        });

        return redirect()->back()->with('success', 'Employee account deactivated. Historical records have been retained.');
    }
}
