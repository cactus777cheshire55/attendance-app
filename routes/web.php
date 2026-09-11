<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SendMailController;
use App\Http\Controllers\StaffApplicationController;
use App\Http\Controllers\StaffController;
use App\Http\Middleware\AdminRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::get('/', function () {
    return view('user.user-login');
});

Route::get('/register', function () {
    return view('user.register');
})->name('register');

Route::get('/login', function () {
    return view('user.user-login');
})->name('login');

Route::prefix('admin')->group(function () {
    Route::get('login', function () {
        return view('admin.admin-login');
    });

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::post('logout', function (Request $request) {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/auth/verify-email', function () {
        return view('auth.verify-email');
    })->name('verification.notice');
    Route::get('/auth/verification-notification-get', [SendMailController::class, 'send'])->name('verification.send.get');
    Route::post('/auth/verification-notification', [SendMailController::class, 'send'])->name('verification.send');
});

Route::middleware(['auth', AdminRedirect::class])->group(function () {
    Route::redirect('/user/attendance-register', '/attendance');
    Route::get('/attendance', [StaffController::class, 'attendance'])->name('user.attendance');
    Route::post('/attendance', [StaffController::class, 'attendanceStatus']);
    Route::get('/attendance/list', [StaffController::class, 'staffList']);
    Route::get('/stamp_correction_request/list', [StaffApplicationController::class, 'application']);
    Route::get('/attendance/report', [ReportController::class, 'report']);
    Route::get('/attendance/detail/{id}', [StaffApplicationController::class, 'showApplication'])->name('user.showApplication');
    Route::get('/attendance/{id}', [StaffController::class, 'show']);

    Route::post('/attendance/{id}', [StaffController::class, 'applicationUpdate']);

});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::redirect('/admin/admin-attendance-list', '/admin/attendance/list');
    Route::get('/admin/attendance/list', [AdminController::class, 'index']);
    Route::get('/admin/staff/list', [AdminController::class, 'adminList']);
    Route::get('/admin/attendance/staff/{id}', [AdminController::class, 'adminAttendance']);
    Route::get('/admin/attendance/{id}', [AdminController::class, 'adminDetail']);

    Route::post('/admin/attendance/{id}', [AdminController::class, 'adminUpdateDetail']);

    Route::get('/stamp_correction_request/approve/{id}', [AdminController::class, 'approve']);
    Route::post('/stamp_correction_request/approve/{id}', [AdminController::class, 'updateApprove']);

    Route::get('/admin/stamp_correction_request/list', [AdminController::class, 'applicationList']);
    Route::post('/export', [AdminController::class, 'export']);
});
