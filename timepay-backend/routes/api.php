<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\Employee\LeaveController;
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
    Route::get('attendance/status', [AttendanceController::class, 'status']);
    Route::post('attendance/store', [AttendanceController::class, 'store']);
    Route::post('attendance/punch', [AttendanceController::class, 'punch']);

    Route::prefix('employee')->group(function () {
        Route::get('leaves', [LeaveController::class, 'index']);
        Route::post('leaves', [LeaveController::class, 'store']);
    });
});
