<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollPayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $this->authenticatedEmployee($request);
        $entries = PayrollEntry::query()->where('company_id', $employee->company_id)->where('user_id', $employee->id)
            ->whereHas('period', fn ($query) => $query->whereIn('status', ['approved', 'partially_paid', 'paid']))
            ->with(['period', 'lines', 'payments'])->whereHas('period', fn ($query) => $query->whereDate('period_start', '>=', now()->subMonths(24)->startOfMonth()))
            ->orderByDesc('created_at')->get();

        return response()->json(['data' => $entries->map(fn (PayrollEntry $entry): array => $this->payslipData($employee, $entry))->values()]);
    }

    public function yearToDate(Request $request): JsonResponse
    {
        $employee = $this->authenticatedEmployee($request);
        $asOf = $request->validate(['as_of' => 'nullable|date'])['as_of'] ?? now()->toDateString();
        return response()->json(['data' => $this->yearToDateData($employee, Carbon::parse($asOf, config('app.timezone')))]);
    }

    public function download(Request $request, string $id)
    {
        $employee = $this->authenticatedEmployee($request);
        $company = $employee->company;

        if (preg_match('/^entry-(\d+)$/', $id, $matches) === 1) {
            $entry = PayrollEntry::query()->where('company_id', $employee->company_id)->where('user_id', $employee->id)
                ->whereKey((int) $matches[1])->whereHas('period', fn ($query) => $query->whereIn('status', ['approved', 'partially_paid', 'paid']))
                ->with(['period', 'lines', 'payments'])->firstOrFail();
            $payslip = $this->payslipData($employee, $entry);
            return Pdf::loadView('pdfs.payroll-payslip', compact('company', 'employee', 'entry', 'payslip'))
                ->setPaper('a4')->download('timepay-payslip-'.$entry->period->period_start->format('Ymd').'-'.$entry->id.'.pdf');
        }

        // Keep the historic YYYY-MM link working where approved period snapshots exist.
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $id) === 1, 404);
        $start = Carbon::createFromFormat('Y-m-d', $id.'-01', config('app.timezone'))->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $entries = PayrollEntry::query()->where('company_id', $employee->company_id)->where('user_id', $employee->id)
            ->whereHas('period', fn ($query) => $query->whereIn('status', ['approved', 'partially_paid', 'paid'])
                ->whereDate('period_start', '>=', $start->toDateString())->whereDate('period_end', '<=', $end->toDateString()))
            ->with(['period', 'lines', 'payments'])->get();
        abort_if($entries->isEmpty(), 404, 'No finalized payroll record exists for this month.');
        $snapshot = $entries->first()->calculation_snapshot ?? [];
        $employeeName = $snapshot['employee_snapshot']['name'] ?? $employee->name;
        $companyName = $snapshot['company_snapshot']['name'] ?? $company->name;
        return Pdf::loadView('pdfs.payroll-monthly-summary', compact('company', 'companyName', 'employeeName', 'entries', 'start', 'end'))
            ->setPaper('a4')->download('timepay-payslip-'.$start->format('Y-m').'.pdf');
    }

    private function authenticatedEmployee(Request $request): User
    {
        $user = $request->user()->loadMissing('company');
        abort_unless(strtoupper((string) $user->role) === User::ROLE_EMPLOYEE, 403, 'Payroll records are only available to employees.');
        abort_unless($user->company, 404, 'Employee company record not found.');
        return $user;
    }

    private function payslipData(User $employee, PayrollEntry $entry): array
    {
        $period = $entry->period;
        $snapshot = $entry->calculation_snapshot ?? [];
        $employeeSnapshot = $snapshot['employee_snapshot'] ?? [];
        $companySnapshot = $snapshot['company_snapshot'] ?? [];
        $currency = $companySnapshot['currency'] ?? $employee->company->currency;
        $lines = $entry->lines->map(fn ($line): array => [
            'category' => $line->category, 'code' => $line->code, 'label' => $line->label,
            'amount' => $line->amount, 'formatted_amount' => $this->formatMoney($line->amount, $currency),
            'taxable' => $line->taxable, 'details' => $line->details,
        ])->values();
        $paidCents = $this->toCents($entry->amount_paid);
        $netCents = $this->toCents($entry->net_pay);
        $balanceCents = max(0, $netCents - $paidCents);
        $paymentStatus = $netCents <= 0 ? 'no_payment_due' : ($paidCents >= $netCents ? 'paid' : ($paidCents > 0 ? 'partially_paid' : 'unpaid'));
        $ytd = $this->yearToDateData($employee, $period->period_end);

        return [
            'id' => 'entry-'.$entry->id, 'entry_id' => $entry->id, 'payroll_period_id' => $period->id,
            'employee_name' => $employeeSnapshot['name'] ?? $employee->name,
            'company_name' => $companySnapshot['name'] ?? $employee->company->name,
            'currency' => $currency,
            'pay_period' => $period->period_start->format('M d, Y').' - '.$period->period_end->format('M d, Y'),
            'period_start' => $period->period_start->toDateString(), 'period_end' => $period->period_end->toDateString(),
            'pay_date' => $period->pay_date?->toDateString(), 'pay_frequency' => $period->frequency,
            'regular_hours' => round($entry->worked_minutes / 60, 2), 'payable_days' => $entry->payable_days,
            'gross_pay' => $entry->gross_pay, 'deductions' => $entry->employee_deductions, 'employer_contributions' => $entry->employer_contributions,
            'net_pay' => $entry->net_pay, 'amount_paid' => $entry->amount_paid,
            'remaining_balance' => $this->fromCents($balanceCents),
            'payment_status' => $paymentStatus,
            'formatted_gross_pay' => $this->formatMoney($entry->gross_pay, $currency),
            'formatted_deductions' => $this->formatMoney($entry->employee_deductions, $currency),
            'formatted_employer_contributions' => $this->formatMoney($entry->employer_contributions, $currency),
            'formatted_net_pay' => $this->formatMoney($entry->net_pay, $currency),
            'formatted_amount_paid' => $this->formatMoney($entry->amount_paid, $currency),
            'formatted_remaining_balance' => $this->formatMoney($this->fromCents($balanceCents), $currency),
            'earnings' => $lines->where('category', 'earning')->values(),
            'deduction_lines' => $lines->where('category', 'employee_deduction')->values(),
            'employer_contribution_lines' => $lines->where('category', 'employer_contribution')->values(),
            'year_to_date' => $ytd,
            'review_flags' => $entry->review_flags ?? [],
        ];
    }

    private function yearToDateData(User $employee, Carbon $asOf): array
    {
        $yearStart = $asOf->copy()->startOfYear()->toDateString();
        $asOfDate = $asOf->toDateString();
        $entries = PayrollEntry::query()->where('company_id', $employee->company_id)->where('user_id', $employee->id)
            ->whereHas('period', fn ($query) => $query->whereIn('status', ['approved', 'partially_paid', 'paid'])
                ->whereDate('period_start', '>=', $yearStart)->whereDate('period_end', '<=', $asOfDate))
            ->with('lines')->get();

        $grossCents = $entries->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->gross_pay));
        $taxableCents = $entries->sum(fn (PayrollEntry $entry): int => $entry->lines->where('category', 'earning')->where('taxable', true)
            ->sum(fn ($line): int => $this->toCents($line->amount))
            - $entry->lines->where('category', 'employee_deduction')->whereIn('code', ['sss_employee', 'philhealth_employee', 'pagibig_employee'])
                ->sum(fn ($line): int => $this->toCents($line->amount)));
        $deductionCents = $entries->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->employee_deductions));
        $employerCents = $entries->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->employer_contributions));
        $netCents = $entries->sum(fn (PayrollEntry $entry): int => $this->toCents($entry->net_pay));
        $currency = $employee->company->currency;
        $data = [
            'year' => (int) $asOf->year, 'gross_pay' => $this->fromCents($grossCents),
            'taxable_compensation' => $this->fromCents(max(0, $taxableCents)),
            'currency' => $currency,
            'employee_deductions' => $this->fromCents($deductionCents),
            'employer_contributions' => $this->fromCents($employerCents),
            'net_pay' => $this->fromCents($netCents),
            'formatted_gross_pay' => $this->formatMoney($this->fromCents($grossCents), $currency),
            'formatted_taxable_compensation' => $this->formatMoney($this->fromCents(max(0, $taxableCents)), $currency),
            'formatted_employee_deductions' => $this->formatMoney($this->fromCents($deductionCents), $currency),
            'formatted_employer_contributions' => $this->formatMoney($this->fromCents($employerCents), $currency),
            'formatted_net_pay' => $this->formatMoney($this->fromCents($netCents), $currency),
            'payroll_entries' => $entries->count(),
        ];
        return $data;
    }

    private function toCents(int|float|string|null $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if (! preg_match('/^(-?)(\d+)(?:\.(\d*))?$/', $value, $matches)) {
            return 0;
        }

        $fraction = str_pad(substr($matches[3] ?? '', 0, 3), 3, '0');
        $cents = ((int) $matches[2] * 100) + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function formatMoney(int|float|string|null $amount, ?string $currency): string
    {
        $symbol = $currency === 'USD' ? '$' : '₱';

        return $symbol.' '.number_format((float) $amount, 2);
    }
}
