<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Payroll summary</title><style>
    @page { margin: 30px; } body { font-family: DejaVu Sans,sans-serif; color:#172033; font-size:10px; }
    h1 { font-size:18px; margin:0 0 6px; } .muted { color:#64748b; } table { width:100%; border-collapse:collapse; margin-top:18px; }
    th { background:#eef4f1; text-align:left; } td,th { border-bottom:1px solid #dbe2e8; padding:7px; } .amount { text-align:right; }
</style></head>
<body>
    <h1>{{ $companyName }} · Finalized payroll summary</h1>
    <div class="muted">{{ $start->format('F Y') }} · {{ $employeeName }}</div>
    <table><thead><tr><th>Payroll period</th><th>Status</th><th class="amount">Gross</th><th class="amount">Deductions</th><th class="amount">Net</th><th class="amount">Paid</th></tr></thead><tbody>
        @foreach ($entries as $entry)<tr><td>{{ $entry->period->period_start->format('M d') }} – {{ $entry->period->period_end->format('M d, Y') }}</td><td>{{ str($entry->period->status)->replace('_', ' ')->title() }}</td><td class="amount">{{ $company->formatMoney($entry->gross_pay) }}</td><td class="amount">{{ $company->formatMoney($entry->employee_deductions) }}</td><td class="amount">{{ $company->formatMoney($entry->net_pay) }}</td><td class="amount">{{ $company->formatMoney($entry->amount_paid) }}</td></tr>@endforeach
    </tbody></table>
    <p class="muted">This summary contains approved payroll snapshots only. It does not represent an unrecorded payment or transfer.</p>
</body></html>
