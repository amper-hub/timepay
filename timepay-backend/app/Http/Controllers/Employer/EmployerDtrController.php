<?php

namespace App\Http\Controllers\Employer;

use App\Exports\DtrWorkbookExport;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\DtrReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class EmployerDtrController extends Controller
{
    public function __construct(private readonly DtrReportService $reports) {}

    public function show(Request $request, int $employee): JsonResponse
    {
        $employer = $this->authorizedEmployer($request);
        [$startDate, $endDate] = $this->dates($request);
        $report = $this->reports->forEmployee(
            $this->companyEmployee($employer, $employee),
            $startDate,
            $endDate
        );

        return response()->json(['data' => $report]);
    }

    public function downloadPdf(Request $request, int $employee)
    {
        $employer = $this->authorizedEmployer($request);
        [$startDate, $endDate] = $this->dates($request);
        $report = $this->reports->forEmployee(
            $this->companyEmployee($employer, $employee),
            $startDate,
            $endDate
        );

        return $this->pdfDownload($report);
    }

    public function downloadExcel(Request $request, int $employee)
    {
        $employer = $this->authorizedEmployer($request);
        [$startDate, $endDate] = $this->dates($request);
        $report = $this->reports->forEmployee(
            $this->companyEmployee($employer, $employee),
            $startDate,
            $endDate
        );

        return Excel::download(
            new DtrWorkbookExport([$report]),
            $this->filename($report, 'xlsx')
        );
    }

    public function downloadAllExcel(Request $request)
    {
        $employer = $this->authorizedEmployer($request);
        [$startDate, $endDate] = $this->dates($request);
        $company = Company::query()->findOrFail($employer->company_id);
        $start = Carbon::createFromFormat('!Y-m-d', $startDate, config('app.timezone'));
        $end = Carbon::createFromFormat('!Y-m-d', $endDate, config('app.timezone'));

        if ($start->diffInDays($end) > 92) {
            return response()->json(['message' => 'Consolidated exports are limited to 93 days at a time.'], 422);
        }

        $employeeCount = User::query()
            ->where('company_id', $company->id)
            ->where('role', User::ROLE_EMPLOYEE)
            ->count();

        if ($employeeCount > 200) {
            return response()->json(['message' => 'Consolidated exports are limited to 200 employees at a time.'], 422);
        }

        $reports = $this->reports->forCompany($company->id, $startDate, $endDate);
        $metadata = [
            'company_name' => $company->name,
            'period' => Carbon::parse($startDate, config('app.timezone'))->format('M d, Y')
                .' - '
                .Carbon::parse($endDate, config('app.timezone'))->format('M d, Y'),
            'employee_count' => count($reports),
            'total_hours' => array_sum(array_column($reports, 'total_hours')),
            'generated_at' => Carbon::now(config('app.timezone'))->format('M d, Y g:i A T'),
        ];
        $filename = 'TimePay_DTR_All_Employees_'.$startDate.'_to_'.$endDate.'.xlsx';

        return Excel::download(new DtrWorkbookExport($reports, $metadata), $filename);
    }

    private function authorizedEmployer(Request $request): User
    {
        $employer = $request->user();

        abort_unless(
            in_array($employer->role, [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true),
            403,
            'Employer access is required to view employee DTRs.'
        );
        abort_unless($employer->company_id, 403, 'Your account is not assigned to a company.');

        return $employer;
    }

    private function companyEmployee(User $employer, int $employeeId): User
    {
        return User::query()
            ->where('company_id', $employer->company_id)
            ->where('role', User::ROLE_EMPLOYEE)
            ->whereKey($employeeId)
            ->with('company')
            ->firstOrFail();
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
    private function pdfDownload(array $report)
    {
        $logoPath = resource_path('img/timepay-logo.png');
        $pdf = Pdf::loadView('pdfs.dtr', [
            'reports' => [$report],
            'logoPath' => is_file($logoPath) ? $logoPath : null,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($report, 'pdf'));
    }

    /** @param array<string, mixed> $report */
    private function filename(array $report, string $extension): string
    {
        $name = Str::slug((string) $report['employee']['name']) ?: 'employee';

        return "TimePay_DTR_{$name}_{$report['period_start']}_to_{$report['period_end']}.{$extension}";
    }
}
