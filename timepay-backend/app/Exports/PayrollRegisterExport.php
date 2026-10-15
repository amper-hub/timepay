<?php

namespace App\Exports;

use App\Models\PayrollPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PayrollRegisterExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private readonly PayrollPeriod $period) {}

    public function headings(): array
    {
        return ['Employee ID', 'Employee Name', 'Hours', 'Days', 'Gross Pay', 'Employee Deductions', 'Employer Contributions', 'Net Pay', 'Paid', 'Balance', 'Review Flags'];
    }

    public function array(): array
    {
        return $this->period->entries->map(fn ($entry): array => [
            (int) $entry->user_id,
            DtrSpreadsheetText::safe(data_get($entry->calculation_snapshot, 'employee_snapshot.name', $entry->employee?->name ?? '')),
            round($entry->worked_minutes / 60, 2),
            (int) $entry->payable_days,
            $entry->gross_pay,
            $entry->employee_deductions,
            $entry->employer_contributions,
            $entry->net_pay,
            $entry->amount_paid,
            $this->fromCents(max(0, $this->toCents($entry->net_pay) - $this->toCents($entry->amount_paid))),
            DtrSpreadsheetText::safe(implode(', ', $entry->review_flags ?? [])),
        ])->all();
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

    private function fromCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public function title(): string
    {
        return 'Payroll Register';
    }
}
