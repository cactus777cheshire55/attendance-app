<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // テスト用
    Route::post('login', [AttendanceRecordController::class, 'login']);

    Route::get('attendance-records', [AttendanceRecordController::class, 'index']);
    Route::get('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show']);
    Route::post('attendance-records', [AttendanceRecordController::class, 'store'])->middleware('auth:sanctum');
    Route::put('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy'])->middleware('auth:sanctum');
});
