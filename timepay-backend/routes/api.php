<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Employee\LeaveController;
use App\Http\Controllers\Api\Employee\PayrollController as EmployeePayrollController;
use App\Http\Controllers\Api\EmployeeDtrController;
use App\Http\Controllers\Employer\EmployerPayrollController;
use App\Http\Controllers\Api\MobilePasswordResetController;
use App\Http\Controllers\Api\PayrollPayslipController;
use App\Http\Controllers\Api\ProfileController as ApiProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/update-temporary-password', [AuthController::class, 'updateTemporaryPassword'])
        ->name('api.auth.update-temporary-password');
});

// Public OTP password reset flow shared by web and mobile accounts.
Route::post('/forgot-password', [MobilePasswordResetController::class, 'sendOtp'])
    ->middleware('throttle:5,1');
Route::post('/verify-reset-otp', [MobilePasswordResetController::class, 'resetPassword'])
    ->middleware('throttle:10,1');
// Preserve the endpoint used by older installed mobile app versions.
Route::post('/reset-password', [MobilePasswordResetController::class, 'resetPassword'])
    ->middleware('throttle:10,1');

// Protected routes requiring Sanctum authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn']);
    Route::get('attendance/status', [AttendanceController::class, 'status']);
    Route::post('attendance/store', [AttendanceController::class, 'store']);
    Route::post('attendance/punch', [AttendanceController::class, 'punch']);
    Route::get('attendance/history', [AttendanceController::class, 'history']);
    Route::get('attendance/{attendanceLog}/photo', [AttendanceController::class, 'photo'])
        ->name('api.attendance.photo');

    Route::patch('profile/name', [ApiProfileController::class, 'updateName']);
    Route::patch('profile/password', [ApiProfileController::class, 'updatePassword']);
    Route::post('profile/reset-face', [ApiProfileController::class, 'resetFace']);

    Route::get('payroll/payslips', [PayrollPayslipController::class, 'index']);
    Route::get('payroll/payslip/{id}/download', [PayrollPayslipController::class, 'download']);

    Route::prefix('employer/payroll')->group(function () {
        Route::get('dashboard', [EmployerPayrollController::class, 'dashboardApi']);
        Route::get('reports', [EmployerPayrollController::class, 'report']);
        Route::get('periods', [EmployerPayrollController::class, 'periodsApi']);
        Route::post('periods', [EmployerPayrollController::class, 'storePeriod']);
        Route::get('periods/{payrollPeriod}', [EmployerPayrollController::class, 'periodApi']);
        Route::post('periods/{payrollPeriod}/calculate', [EmployerPayrollController::class, 'calculatePeriod']);
        Route::post('periods/{payrollPeriod}/approve', [EmployerPayrollController::class, 'approvePeriod']);
        Route::post('periods/{payrollPeriod}/void', [EmployerPayrollController::class, 'voidPeriod']);
        Route::get('periods/{payrollPeriod}/audit', [EmployerPayrollController::class, 'auditHistory']);
        Route::get('periods/{payrollPeriod}/export.xlsx', [EmployerPayrollController::class, 'exportPeriodXlsx']);
        Route::get('periods/{payrollPeriod}/register.pdf', [EmployerPayrollController::class, 'exportPeriodPdf']);
        Route::post('entries/{payrollEntry}/payments', [EmployerPayrollController::class, 'recordPayment']);
        Route::patch('profiles/{user}', [EmployerPayrollController::class, 'updateEmployeeRate']);
        Route::post('profiles/{user}/items', [EmployerPayrollController::class, 'storeProfileItem']);
    });

    Route::prefix('employee')->group(function () {
        Route::get('pending-pay', [EmployeePayrollController::class, 'pendingPay']);
        Route::get('payroll/ytd', [PayrollPayslipController::class, 'yearToDate']);
        Route::get('dtr', [EmployeeDtrController::class, 'show']);
        Route::get('dtr/pdf', [EmployeeDtrController::class, 'downloadPdf']);
        Route::get('leave-balance', [LeaveController::class, 'balance']);
        Route::get('leaves', [LeaveController::class, 'index']);
        Route::post('leaves', [LeaveController::class, 'store']);
    });
});
