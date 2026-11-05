<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payroll Payslip</title>
    <style>
        @page { margin: 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; }
        h1 { margin: 0; font-size: 20px; }
        h2 { margin: 22px 0 8px; color: #047857; font-size: 12px; }
        .muted { color: #637083; }
        .card { border: 1px solid #dbe2e8; border-radius: 6px; margin-top: 18px; padding: 12px; }
        .grid { width: 100%; }
        .grid td { width: 50%; padding: 4px 8px 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eef4f1; text-align: left; }
        td, th { border-bottom: 1px solid #e3e8ed; padding: 7px; }
        .amount { text-align: right; white-space: nowrap; }
        .total { background: #f2f8f5; font-weight: bold; }
        .net { background: #e7f5ed; color: #065f46; font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $payslip['company_name'] }}</h1>
    <div class="muted">Employee payslip · {{ str($payslip['payment_status'])->replace('_', ' ')->title() }}</div>
    <div class="card">
        <table class="grid"><tr><td><strong>Employee</strong><br>{{ $payslip['employee_name'] }}</td><td><strong>Payroll period</strong><br>{{ $payslip['pay_period'] }}</td></tr>
            <tr><td><strong>Employee number</strong><br><span class="muted">Not configured</span></td><td><strong>Pay date</strong><br>{{ $payslip['pay_date'] ?? 'Not set' }}</td></tr>
            <tr><td><strong>Pay frequency</strong><br>{{ str($payslip['pay_frequency'])->replace('_', ' ')->title() }}</td><td><strong>Hours / payable days</strong><br>{{ number_format($payslip['regular_hours'], 2) }} hours / {{ $payslip['payable_days'] }} days</td></tr>
        </table>
    </div>
    <h2>Earnings</h2>
    <table><thead><tr><th>Description</th><th class="amount">Amount</th></tr></thead><tbody>
        @foreach ($payslip['earnings'] as $line)<tr><td>{{ $line['label'] }}</td><td class="amount">{{ $line['formatted_amount'] }}</td></tr>@endforeach
        <tr class="total"><td>Gross pay</td><td class="amount">{{ $payslip['formatted_gross_pay'] }}</td></tr>
    </tbody></table>
    <h2>Employee deductions</h2>
    <table><thead><tr><th>Description</th><th class="amount">Amount</th></tr></thead><tbody>
        @forelse ($payslip['deduction_lines'] as $line)<tr><td>{{ $line['label'] }}</td><td class="amount">{{ $line['formatted_amount'] }}</td></tr>@empty<tr><td class="muted">No employee deductions were recorded.</td><td class="amount">{{ $payslip['formatted_deductions'] }}</td></tr>@endforelse
        <tr class="total"><td>Total employee deductions</td><td class="amount">{{ $payslip['formatted_deductions'] }}</td></tr>
        <tr class="net"><td>Net pay</td><td class="amount">{{ $payslip['formatted_net_pay'] }}</td></tr>
    </tbody></table>
    <h2>Employer contributions · informational only</h2>
    <table><thead><tr><th>Description</th><th class="amount">Amount</th></tr></thead><tbody>
        @forelse ($payslip['employer_contribution_lines'] as $line)<tr><td>{{ $line['label'] }}</td><td class="amount">{{ $line['formatted_amount'] }}</td></tr>@empty<tr><td class="muted">No employer contributions recorded.</td><td class="amount">{{ $payslip['formatted_employer_contributions'] }}</td></tr>@endforelse
        <tr class="total"><td>Total employer contributions</td><td class="amount">{{ $payslip['formatted_employer_contributions'] }}</td></tr>
    </tbody></table>
    <h2>Year to date · {{ $payslip['year_to_date']['year'] }}</h2>
    <table><tbody>
        <tr><td>Gross pay</td><td class="amount">{{ $payslip['year_to_date']['formatted_gross_pay'] }}</td><td>Employee deductions</td><td class="amount">{{ $payslip['year_to_date']['formatted_employee_deductions'] }}</td></tr>
        <tr><td>Employer contributions</td><td class="amount">{{ $payslip['year_to_date']['formatted_employer_contributions'] }}</td><td>Net pay</td><td class="amount">{{ $payslip['year_to_date']['formatted_net_pay'] }}</td></tr>
    </tbody></table>
    <div class="card"><strong>Payment status:</strong> {{ str($payslip['payment_status'])->replace('_', ' ')->title() }} · Paid {{ $payslip['formatted_amount_paid'] }} · Balance {{ $payslip['formatted_remaining_balance'] }}<br><span class="muted">Payment status reflects employer-recorded payments. TimePay does not initiate bank transfers.</span></div>
    @if (! empty($payslip['review_flags']))<div class="card"><strong>Payroll review notes</strong><ul>@foreach ($payslip['review_flags'] as $flag)<li>{{ str($flag)->replace('_', ' ') }}</li>@endforeach</ul></div>@endif
    <p class="muted" style="margin-top: 24px">Generated {{ now()->setTimezone(config('app.timezone'))->format('M d, Y g:i A T') }} · Amounts are snapshots from the approved payroll calculation.</p>
</body>
</html>
