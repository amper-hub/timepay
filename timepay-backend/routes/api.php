<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\PayrollPayslipController;
use App\Http\Controllers\Api\Employee\LeaveController;
use App\Http\Controllers\Api\ProfileController as ApiProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/update-temporary-password', [AuthController::class, 'updateTemporaryPassword'])
        ->name('api.auth.update-temporary-password');
});

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

    Route::patch('profile/name', [ApiProfileController::class, 'updateName']);
    Route::patch('profile/password', [ApiProfileController::class, 'updatePassword']);
    Route::post('profile/reset-face', [ApiProfileController::class, 'resetFace']);

    Route::get('payroll/payslips', [PayrollPayslipController::class, 'index']);
    Route::get('payroll/payslip/{id}/download', [PayrollPayslipController::class, 'download']);

    Route::prefix('employee')->group(function () {
        Route::get('leave-balance', [LeaveController::class, 'balance']);
        Route::get('leaves', [LeaveController::class, 'index']);
        Route::post('leaves', [LeaveController::class, 'store']);
    });
});
