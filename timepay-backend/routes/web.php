<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Employer\EmployerDashboardController;
use App\Http\Controllers\Employer\EmployerAttendanceController;
use App\Http\Controllers\Employer\EmployerGeofenceController;
use App\Http\Controllers\Employer\EmployerPayrollController;
use Illuminate\Support\Facades\Route;

// 1. Redirect the root URL straight to your employer dashboard
Route::get('/', function () {
    return redirect('/employer/dashboard');
});

// 2. --- PHASE 3: EMPLOYER PORTAL ROUTES ---
Route::middleware(['auth'])->prefix('employer')->group(function () {
    Route::get('/dashboard', [EmployerDashboardController::class, 'index'])->name('employer.dashboard');

    Route::get('/geofence', [EmployerGeofenceController::class, 'edit'])->name('employer.geofence');
    Route::post('/geofence', [EmployerGeofenceController::class, 'update'])->name('employer.geofence.update');

    Route::get('/attendance', [EmployerAttendanceController::class, 'index'])->name('employer.attendance');

    Route::get('/payroll', [EmployerPayrollController::class, 'index'])->name('employer.payroll');
    Route::post('/payroll/update/{user}', [EmployerPayrollController::class, 'updateEmployeeRate'])->name('employer.payroll.update');
});

// 3. --- FIX BREEZE REDIRECT TRAP ---
// When Breeze logs you in, it goes here. We immediately redirect it to YOUR dashboard!
Route::get('/dashboard', function () {
    return redirect('/employer/dashboard');
})->middleware(['auth'])->name('dashboard'); // <-- Removed the 'verified' loop trap!

// Profile settings (Optional default Breeze stuff)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require base_path('routes/auth.php');
