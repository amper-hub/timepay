<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $payslip['id'] }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }

        .page {
            padding: 34px;
        }

        .header {
            border-bottom: 2px solid #e2e8f0;
            display: table;
            padding-bottom: 18px;
            width: 100%;
        }

        .brand,
        .meta {
            display: table-cell;
            vertical-align: top;
        }

        .brand {
            width: 60%;
        }

        .meta {
            color: #475569;
            text-align: right;
            width: 40%;
        }

        .logo {
            height: 52px;
            margin-bottom: 10px;
            max-width: 180px;
        }

        h1 {
            font-size: 24px;
            margin: 0;
        }

        h2 {
            font-size: 16px;
            margin: 24px 0 10px;
        }

        .muted {
            color: #64748b;
        }

        .employee-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-top: 22px;
            padding: 16px;
        }

        table {
            border-collapse: collapse;
            margin-top: 10px;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 11px 8px;
            text-align: left;
        }

        th {
            background: #f1f5f9;
            color: #334155;
            font-size: 11px;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .amount {
            text-align: right;
        }

        .total-row td {
            border-bottom: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .footer {
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 11px;
            margin-top: 34px;
            padding-top: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="brand">
                @if ($logoPath)
                    <img class="logo" src="{{ $logoPath }}" alt="{{ $company->name }} logo">
                @endif
                <h1>{{ $company->name }}</h1>
                <div class="muted">Employee Payslip</div>
            </div>
            <div class="meta">
                <strong>Pay Period</strong><br>
                {{ $payslip['pay_period'] }}<br><br>
                <strong>Generated</strong><br>
                {{ now()->format('M d, Y') }}
            </div>
        </div>

        <div class="employee-box">
            <strong>Employee Name</strong><br>
            {{ $employee->name }}<br>
            <span class="muted">{{ $employee->email }}</span>
        </div>

        <h2>Earnings</h2>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Regular Hours</th>
                    <th class="amount">Hourly Rate</th>
                    <th class="amount">Gross Pay</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Verified attendance</td>
                    <td class="amount">{{ number_format($payslip['regular_hours'], 2) }}</td>
                    <td class="amount">{{ $payslip['formatted_hourly_rate'] }}</td>
                    <td class="amount">{{ $payslip['formatted_gross_pay'] }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Summary</h2>
        <table>
            <tbody>
                <tr>
                    <td>Gross Pay</td>
                    <td class="amount">{{ $payslip['formatted_gross_pay'] }}</td>
                </tr>
                <tr>
                    <td>Deductions</td>
                    <td class="amount">{{ $payslip['formatted_deductions'] }}</td>
                </tr>
                <tr class="total-row">
                    <td>Net Pay</td>
                    <td class="amount">{{ $payslip['formatted_net_pay'] }}</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            This digital payslip was generated automatically by TimePay.
        </div>
    </div>
</body>
</html>
