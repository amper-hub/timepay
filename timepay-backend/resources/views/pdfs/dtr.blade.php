<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>TimePay Daily Time Record</title>
    <style>
        @page { margin: 24px 28px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .brand { border-bottom: 2px solid #059669; margin-bottom: 14px; padding-bottom: 9px; }
        .brand img { max-height: 34px; max-width: 150px; }
        .brand-name { color: #047857; font-size: 18px; font-weight: bold; }
        .subtitle { color: #64748b; font-size: 10px; margin-top: 3px; }
        .details { margin: 10px 0 12px; width: 100%; }
        .details td { padding: 3px 8px 3px 0; vertical-align: top; }
        .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .value { color: #0f172a; font-size: 10px; font-weight: bold; }
        table.records { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .records th { background: #ecfdf5; color: #065f46; font-size: 7.5px; padding: 7px 5px; text-align: left; }
        .records td { border-bottom: 1px solid #dbe3e8; font-size: 8px; padding: 6px 5px; vertical-align: top; }
        .records .date { width: 12%; }
        .records .schedule { width: 16%; }
        .records .punch { width: 15%; }
        .records .hours { text-align: right; width: 10%; }
        .records .status { width: 15%; }
        .muted { color: #64748b; }
        .totals { background: #f8fafc; border: 1px solid #dbe3e8; margin-top: 12px; padding: 9px 11px; }
        .total-hours { color: #047857; font-size: 13px; font-weight: bold; }
        .note { color: #64748b; font-size: 8px; line-height: 1.4; margin-top: 9px; }
        .signatures { margin-top: 36px; page-break-inside: avoid; width: 100%; }
        .signatures td { padding-right: 36px; width: 50%; }
        .signature-line { border-top: 1px solid #64748b; margin-top: 28px; padding-top: 5px; }
        .generated { color: #64748b; font-size: 7px; margin-top: 14px; }
    </style>
</head>
<body>
@foreach ($reports as $report)
    @if (! $loop->first)
        <div style="page-break-before: always"></div>
    @endif
    <div class="brand">
        @if ($logoPath)
            <img src="{{ $logoPath }}" alt="TimePay">
        @else
            <div class="brand-name">TimePay</div>
        @endif
        <div class="subtitle">Daily Time Record</div>
    </div>

    <table class="details">
        <tr>
            <td>
                <div class="label">Company</div>
                <div class="value">{{ $report['company']['name'] ?: '—' }}</div>
            </td>
            <td>
                <div class="label">Employee</div>
                <div class="value">{{ $report['employee']['name'] }}</div>
            </td>
            <td>
                <div class="label">Employee ID</div>
                <div class="value">{{ $report['employee']['id'] }}</div>
            </td>
            <td>
                <div class="label">Reporting period</div>
                <div class="value">{{ $report['period'] }}</div>
            </td>
        </tr>
    </table>

    <table class="records">
        <thead>
            <tr>
                <th class="date">Attendance date</th>
                <th class="schedule">Company schedule</th>
                <th class="punch">Clock in</th>
                <th class="punch">Clock out</th>
                <th class="hours">Net hours</th>
                <th class="status">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['records'] as $record)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($record['date'])->format('M d, Y') }}</td>
                    <td>{{ $record['scheduled_shift'] ?: '—' }}</td>
                    <td>{{ $record['clock_in'] ?: '—' }}</td>
                    <td>{{ $record['clock_out'] ?: '—' }}</td>
                    <td class="hours">{{ $record['net_hours'] === null ? '—' : number_format($record['net_hours'], 2) }}</td>
                    <td>{{ $record['status'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No attendance records for this reporting period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <span class="label">Reporting-period total</span><br>
        <span class="total-hours">{{ number_format($report['total_hours'], 2) }} hours</span>
    </div>

    <div class="note">
        Break start and end times are not stored in the attendance records, so no break deduction is applied. Only complete verified punch pairs contribute hours. An unmatched, flagged, or rejected punch is shown without invented time.
    </div>

    <table class="signatures">
        <tr>
            <td><div class="signature-line">Employee signature</div></td>
            <td><div class="signature-line">Authorized employer</div></td>
        </tr>
    </table>
    <div class="generated">Generated {{ $report['generated_at'] }} ({{ $report['timezone'] }})</div>
@endforeach
</body>
</html>
