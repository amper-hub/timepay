<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
});

// Protected routes requiring Sanctum authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('attendance/punch', [AttendanceController::class, 'punch']);
});
