<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TimePay Platform Report</title>
    <style>
        * { box-sizing: border-box; }
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 22px; margin: 0; }
        p { color: #475569; margin: 5px 0 0; }
        .header { border-bottom: 1px solid #cbd5e1; margin-bottom: 20px; padding-bottom: 14px; }
        .summary { margin-bottom: 20px; width: 100%; }
        .summary td { border: 1px solid #cbd5e1; padding: 12px; width: 33.333%; }
        .summary-label { color: #64748b; font-size: 10px; }
        .summary-value { color: #0f172a; font-size: 20px; font-weight: bold; margin-top: 4px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #ecfdf5; color: #065f46; font-size: 10px; text-align: left; text-transform: uppercase; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TimePay Platform Report</h1>
        <p>Generated {{ now()->format('M d, Y g:i A') }}</p>
    </div>

    <table class="summary">
        <tr>
            <td><div class="summary-label">Employers</div><div class="summary-value">{{ number_format($summary['employerCount']) }}</div></td>
            <td><div class="summary-label">Employees</div><div class="summary-value">{{ number_format($summary['employeeCount']) }}</div></td>
            <td><div class="summary-label">Companies</div><div class="summary-value">{{ number_format($summary['companyCount']) }}</div></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Company</th>
                <th>Employer</th>
                <th>Email</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportRows as $employer)
                <tr>
                    <td>{{ $employer->company?->name ?? 'No company assigned' }}</td>
                    <td>{{ $employer->name }}</td>
                    <td>{{ $employer->email }}</td>
                    <td>{{ ucfirst($employer->status ?? 'active') }}</td>
                    <td>{{ $employer->created_at?->format('M d, Y') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">No employer data is available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
