<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AttendanceCorrectionRequestController;

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\AttendanceCorrectionController as AdminAttendanceCorrectionController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;


Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/attendance');
})->middleware(['auth', 'signed'])->name('verification.verify');


Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');


Route::get('/admin/login', function () {
    return view('admin.login');
})->middleware('guest')->name('admin.login');

Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('admin.login.store');

Route::get('/', function () {

    if (!Auth::check()) {
        return redirect('/login');
    }

    $user = Auth::user();

    $isAdmin = $user->roles()->where('name', 'admin')->exists();

    return redirect(
        $isAdmin
            ? '/admin/attendance/list'
            : '/attendance'
    );
});


Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {

    Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])
        ->name('attendance.list');

    Route::get('/attendance/staff/{user}', [AdminAttendanceController::class, 'staffShow'])
    ->name('attendance.staff.show');

    Route::get('/attendance/{user}/{date}', [AdminAttendanceController::class, 'showByUserDate'])
    ->name('attendance.show.by-date');

    Route::post('/attendance', [AdminAttendanceController::class, 'store'])
    ->name('attendance.store');

    Route::get('/attendance/{attendance}', [AdminAttendanceController::class, 'show'])
        ->name('attendance.show');

    Route::put('/attendance/{attendance}', [AdminAttendanceController::class, 'update'])
        ->name('attendance.update');

    Route::get('/staff/list', [AdminStaffController::class, 'index'])
        ->name('staff.list');

    Route::get('/attendance/staff/{user}/export', [AdminAttendanceController::class, 'staffExportCsv'])
    ->name('attendance.staff.export');

    Route::post('/logout', function (\Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    })->name('logout');
});


Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index');

    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])
        ->name('attendance.clock_in');

    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])
        ->name('attendance.clock_out');

    Route::post('/attendance/break-start', [AttendanceController::class, 'breakStart'])
        ->name('attendance.break_start');

    Route::post('/attendance/break-end', [AttendanceController::class, 'breakEnd'])
        ->name('attendance.break_end');

    Route::get('/attendance/list', [AttendanceController::class, 'list'])
        ->name('attendance.list');

    Route::get('/attendance/detail/{id}', [AttendanceCorrectionController::class, 'show'])
        ->name('attendance.detail.show');

    Route::post('/attendance/detail/{id}', [AttendanceCorrectionController::class, 'store'])
        ->name('attendance.detail.store');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionRequestController::class, 'index'])
        ->name('stamp_correction_request.list');
});

Route::prefix('stamp_correction_request')
    ->middleware(['auth', 'admin'])
    ->name('stamp_correction_request.')
    ->group(function () {
        Route::get('/approve/{attendance_correction}', [AdminAttendanceCorrectionController::class, 'show'])
            ->name('approve.show');

        Route::put('/approve/{attendance_correction}', [AdminAttendanceCorrectionController::class, 'update'])
            ->name('approve.update');
    });
