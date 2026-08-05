<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\EmployerRegistrationController;
use App\Http\Controllers\Employer\EmployerDashboardController;
use App\Http\Controllers\Employer\EmployerAttendanceController;
use App\Http\Controllers\Employer\EmployerGeofenceController;
use App\Http\Controllers\Employer\EmployerPayrollController;
use App\Http\Controllers\Employer\EmployeeController;
use App\Http\Controllers\Employer\LeaveController as EmployerLeaveController;
use App\Http\Controllers\Employer\SettingsController;
use App\Http\Controllers\SuperAdmin\EmployerManagementController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\SuperAdmin\LeaveAuditController;
use App\Http\Controllers\SuperAdmin\PlatformOversightController;
use App\Http\Controllers\SuperAdmin\ReportController;
use App\Http\Controllers\SuperAdmin\SuperAdminDashboardController;
use App\Http\Middleware\IsSuperAdmin;
use Illuminate\Support\Facades\Route;

// 1. Redirect the root URL based on the authenticated user's role
Route::get('/', function () {
    $user = auth()->user();

    if ($user && $user->role === \App\Models\User::ROLE_SUPER_ADMIN) {
        return redirect('/super-admin/dashboard');
    }

    if (! $user) {
        return view('welcome');
    }

    return redirect('/employer/dashboard');
});

Route::view('/register-employer', 'auth.register-employer')->middleware('guest')->name('employer.register');
Route::post('/register-employer', [EmployerRegistrationController::class, 'store'])->middleware('guest')->name('employer.register.store');

// 2. --- SUPER ADMIN PORTAL ROUTES ---
Route::prefix('super-admin')->name('super-admin.')->middleware(['auth', IsSuperAdmin::class])->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'impersonate'])->name('impersonate');

    Route::get('/platform', [PlatformOversightController::class, 'index'])->name('platform.index');
    Route::patch('/platform/geofence-settings', [PlatformOversightController::class, 'updateGeofenceSettings'])->name('platform.geofence-settings.update');
    Route::patch('/platform/employees/{employee}/reset-photo', [PlatformOversightController::class, 'resetPhoto'])->name('platform.employees.reset-photo');

    Route::resource('employers', EmployerManagementController::class);
    Route::post('/employers/{employer}/approve', [EmployerManagementController::class, 'approve'])->name('employers.approve');
    Route::post('/employers/{employer}/suspend', [EmployerManagementController::class, 'suspend'])->name('employers.suspend');
    Route::post('/employers/{user}/impersonate', [ImpersonationController::class, 'impersonate'])->name('employers.impersonate');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('/leaves', [LeaveAuditController::class, 'index'])->name('leaves.index');

});

Route::post('/impersonation/leave', [ImpersonationController::class, 'leave'])
    ->middleware('auth')
    ->name('impersonation.leave');

// 3. --- PHASE 3: EMPLOYER PORTAL ROUTES ---
Route::middleware(['auth'])->prefix('employer')->group(function () {
    Route::get('/dashboard', [EmployerDashboardController::class, 'index'])->name('employer.dashboard');

    Route::get('/geofence', [EmployerGeofenceController::class, 'edit'])->name('employer.geofence');
    Route::post('/geofence', [EmployerGeofenceController::class, 'update'])->name('employer.geofence.update');

    Route::get('/settings', [SettingsController::class, 'index'])->name('employer.settings.index');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('employer.settings.update');

    Route::get('/attendance', [EmployerAttendanceController::class, 'index'])->name('employer.attendance');

    Route::get('/leaves', [EmployerLeaveController::class, 'index'])->name('employer.leaves.index');
    Route::patch('/leaves/{leaveRequest}', [EmployerLeaveController::class, 'update'])->name('employer.leaves.update');

    Route::get('/payroll', [EmployerPayrollController::class, 'index'])->name('employer.payroll');
    Route::post('/payroll/update/{user}', [EmployerPayrollController::class, 'updateEmployeeRate'])->name('employer.payroll.update');

    Route::post('/employees', [EmployeeController::class, 'store'])->name('employer.employees.store');
});

// 4. --- FIX BREEZE REDIRECT TRAP ---
// When Breeze logs you in, it goes here. We immediately redirect it to the correct dashboard.
Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user && $user->isSuperAdmin()) {
        return redirect('/super-admin/dashboard');
    }

    return redirect('/employer/dashboard');
})->middleware(['auth'])->name('dashboard');

// Profile settings (Optional default Breeze stuff)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require base_path('routes/auth.php');
