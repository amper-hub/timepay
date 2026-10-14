<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DtrReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeDtrController extends Controller
{
    public function __construct(private readonly DtrReportService $reports) {}

    public function show(Request $request): JsonResponse
    {
        $employee = $this->authenticatedEmployee($request);
        [$startDate, $endDate] = $this->dates($request);

        return response()->json([
            'data' => $this->reports->forEmployee($employee, $startDate, $endDate),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $employee = $this->authenticatedEmployee($request);
        [$startDate, $endDate] = $this->dates($request);
        $report = $this->reports->forEmployee($employee, $startDate, $endDate);
        $logoPath = resource_path('img/timepay-logo.png');
        $pdf = Pdf::loadView('pdfs.dtr', [
            'reports' => [$report],
            'logoPath' => is_file($logoPath) ? $logoPath : null,
        ])->setPaper('a4', 'landscape');
        $filename = $this->filename($report, 'pdf');

        return $pdf->download($filename);
    }

    private function authenticatedEmployee(Request $request): User
    {
        $employee = $request->user()->loadMissing('company');

        abort_unless($employee->role === User::ROLE_EMPLOYEE, 403, 'Daily time records are only available to employees.');
        abort_unless($employee->company, 404, 'Employee company record not found.');

        return $employee;
    }

    /** @return array{string, string} */
    private function dates(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $start = Carbon::createFromFormat('!Y-m-d', $validated['start_date'], config('app.timezone'));
        $end = Carbon::createFromFormat('!Y-m-d', $validated['end_date'], config('app.timezone'));

        if ($start->diffInDays($end) > 365) {
            throw ValidationException::withMessages([
                'end_date' => 'The reporting period cannot exceed 366 calendar days.',
            ]);
        }

        return [$validated['start_date'], $validated['end_date']];
    }

    /** @param array<string, mixed> $report */
    private function filename(array $report, string $extension): string
    {
        $name = Str::slug((string) $report['employee']['name']) ?: 'employee';

        return "TimePay_DTR_{$name}_{$report['period_start']}_to_{$report['period_end']}.{$extension}";
    }
}
