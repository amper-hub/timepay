<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class DtrWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly array $reports,
        private readonly array $metadata = []
    ) {}

    public function sheets(): array
    {
        return [
            new DtrSummarySheet($this->reports, $this->metadata),
            new DtrRecordsSheet($this->reports),
        ];
    }
}

class DtrSummarySheet implements FromArray, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly array $reports,
        private readonly array $metadata = []
    ) {}

    public function array(): array
    {
        $first = $this->reports[0] ?? null;
        $companyName = $first['company']['name'] ?? $this->metadata['company_name'] ?? 'TimePay';
        $totalHours = array_sum(array_column($this->reports, 'total_hours'));
        $rows = [
            ['TimePay Daily Time Record'],
            ['Company', DtrSpreadsheetText::safe($companyName)],
            ['Reporting period', $first['period'] ?? $this->metadata['period'] ?? ''],
            ['Generated on', $first['generated_at'] ?? $this->metadata['generated_at'] ?? now()->format('M d, Y g:i A T')],
        ];

        $isConsolidated = array_key_exists('employee_count', $this->metadata);

        if (! $isConsolidated && count($this->reports) === 1) {
            $rows[] = ['Employee ID', $first['employee']['id'] ?? ''];
            $rows[] = ['Employee name', DtrSpreadsheetText::safe($first['employee']['name'] ?? '')];
            $rows[] = ['Total hours', round((float) $totalHours, 2)];
        } else {
            $rows[] = ['Employees included', $this->metadata['employee_count'] ?? count($this->reports)];
            $rows[] = ['Total hours', round((float) $totalHours, 2)];
            $rows[] = [];
            $rows[] = ['Employee ID', 'Employee Name', 'Period Total Hours'];

            foreach ($this->reports as $report) {
                $rows[] = [
                    (int) $report['employee']['id'],
                    DtrSpreadsheetText::safe($report['employee']['name']),
                    round((float) $report['total_hours'], 2),
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'DTR Summary';
    }
}

class DtrRecordsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array<string, mixed>>  $reports
     */
    public function __construct(private readonly array $reports) {}

    public function headings(): array
    {
        return [
            'Employee ID',
            'Employee Name',
            'Company',
            'Period Start',
            'Period End',
            'Attendance Date',
            'Scheduled Shift',
            'Clock In',
            'Clock Out',
            'Break Duration (not recorded)',
            'Net Hours (decimal)',
            'Attendance Status',
            'Remarks',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->reports as $report) {
            foreach ($report['records'] as $record) {
                $rows[] = [
                    (int) $report['employee']['id'],
                    DtrSpreadsheetText::safe($report['employee']['name']),
                    DtrSpreadsheetText::safe($report['company']['name'] ?? ''),
                    $report['period_start'],
                    $report['period_end'],
                    $record['date'],
                    DtrSpreadsheetText::safe($record['scheduled_shift'] ?? ''),
                    DtrSpreadsheetText::safe($record['clock_in'] ?? ''),
                    DtrSpreadsheetText::safe($record['clock_out'] ?? ''),
                    '',
                    $record['net_hours'] === null ? '' : (float) $record['net_hours'],
                    DtrSpreadsheetText::safe($record['status']),
                    DtrSpreadsheetText::safe($record['remarks'] ?? ''),
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Attendance';
    }
}
