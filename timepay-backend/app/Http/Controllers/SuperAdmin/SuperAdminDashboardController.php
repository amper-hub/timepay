<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function index(): View
    {
        $employers = User::query()
            ->where('role', User::ROLE_EMPLOYER)
            ->with('company')
            ->latest()
            ->limit(6)
            ->get();

        $hasStatusColumn = Schema::hasColumn('users', 'status');

        $stats = [
            'totalEmployers' => User::query()->where('role', User::ROLE_EMPLOYER)->count(),
            'activeEmployers' => $hasStatusColumn
                ? User::query()->where('role', User::ROLE_EMPLOYER)->where('status', 'active')->count()
                : User::query()->where('role', User::ROLE_EMPLOYER)->count(),
            'adminUsers' => User::query()->where('role', User::ROLE_ADMIN)->count(),
            'employees' => User::query()->where('role', User::ROLE_EMPLOYEE)->count(),
            'companies' => Company::query()->count(),
            'recentSignups' => User::query()->where('role', User::ROLE_EMPLOYER)->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return view('super-admin.dashboard', compact('employers', 'stats'));
    }
}
