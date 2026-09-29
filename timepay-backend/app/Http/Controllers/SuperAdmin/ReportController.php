<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Exports\ReportsExport;
use App\Models\Company;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $employerCount = User::query()->where('role', User::ROLE_EMPLOYER)->count();
        $employeeCount = User::query()->where('role', User::ROLE_EMPLOYEE)->count();
        $adminCount = User::query()->where('role', User::ROLE_ADMIN)->count();
        $companyCount = Company::query()->count();
        $recentEmployers = User::query()
            ->where('role', User::ROLE_EMPLOYER)
            ->with('company')
            ->latest()
            ->paginate(8)
            ->withQueryString();
        $hasStatusColumn = Schema::hasColumn('users', 'status');

        return view('super-admin.reports.index', compact(
            'adminCount',
            'companyCount',
            'employeeCount',
            'employerCount',
            'hasStatusColumn',
            'recentEmployers'
        ));
    }

    public function exportXlsx()
    {
        return Excel::download(
            new ReportsExport($this->reportRows()),
            'timepay-platform-report-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function exportPdf()
    {
        $reportRows = $this->reportRows();
        $summary = [
            'employerCount' => $reportRows->count(),
            'employeeCount' => User::query()->where('role', User::ROLE_EMPLOYEE)->count(),
            'companyCount' => Company::query()->count(),
        ];

        return Pdf::loadView('super-admin.reports.pdf', compact('reportRows', 'summary'))
            ->setPaper('a4', 'landscape')
            ->download('timepay-platform-report-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Return every employer row required by both report download formats.
     */
    private function reportRows(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_EMPLOYER)
            ->with('company')
            ->latest()
            ->get();
    }
}
