<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

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

    public function export(Request $request)
    {
        return back()->with('info', 'Export functionality is ready for integration with CSV/XLS/PDF generation.');
    }
}
