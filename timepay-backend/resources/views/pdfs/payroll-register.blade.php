<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payroll Register</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 9px; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        .muted { color: #637083; }
        .summary { margin: 16px 0; border: 1px solid #dbe2e8; border-radius: 6px; padding: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eef4f1; color: #334155; font-size: 8px; text-align: left; }
        td, th { border: 1px solid #dbe2e8; padding: 6px; vertical-align: top; }
        .amount { text-align: right; white-space: nowrap; }
        .flags { color: #996600; }
    </style>
</head>
<body>
    <h1>{{ $companyName }} · Payroll Register</h1>
    <div class="muted">{{ $period->period_start->format('M d, Y') }} – {{ $period->period_end->format('M d, Y') }} · {{ str($period->frequency)->replace('_', ' ')->title() }} · {{ str($period->status)->replace('_', ' ')->title() }}</div>
    <div class="summary">
        Gross: {{ $company->formatMoney($period->gross_total) }} &nbsp;&nbsp;
        Employee deductions: {{ $company->formatMoney($period->deduction_total) }} &nbsp;&nbsp;
        Employer contributions: {{ $company->formatMoney($period->employer_contribution_total) }} &nbsp;&nbsp;
        Net: {{ $company->formatMoney($period->net_total) }} &nbsp;&nbsp;
        Paid: {{ $company->formatMoney($period->paid_total) }}
    </div>
    <table>
        <thead><tr><th>Employee</th><th>Hours / days</th><th class="amount">Gross</th><th class="amount">Deductions</th><th class="amount">Employer contributions</th><th class="amount">Net</th><th class="amount">Paid</th><th>Review flags</th></tr></thead>
        <tbody>
            @foreach ($period->entries as $entry)
                <tr>
                    <td>{{ data_get($entry->calculation_snapshot, 'employee_snapshot.name', $entry->employee?->name) }}</td>
                    <td>{{ number_format($entry->worked_minutes / 60, 2) }} hrs<br>{{ $entry->payable_days }} days</td>
                    <td class="amount">{{ $company->formatMoney($entry->gross_pay) }}</td>
                    <td class="amount">{{ $company->formatMoney($entry->employee_deductions) }}</td>
                    <td class="amount">{{ $company->formatMoney($entry->employer_contributions) }}</td>
                    <td class="amount">{{ $company->formatMoney($entry->net_pay) }}</td>
                    <td class="amount">{{ $company->formatMoney($entry->amount_paid) }}</td>
                    <td class="flags">{{ implode(', ', array_map(fn ($flag) => str($flag)->replace('_', ' ')->toString(), $entry->review_flags ?? [])) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="margin-top: 14px">Generated {{ now()->setTimezone(config('app.timezone'))->format('M d, Y g:i A T') }}. Payment status records manual confirmation only and does not represent a bank transfer.</p>
</body>
</html>
