<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpiryController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffAccountController;
use App\Http\Controllers\WalkinController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Gym logo (shown on the login page too, so no login needed)
Route::get('/branding/logo', [SettingsController::class, 'logo'])->name('branding.logo');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Own profile (name, e-mail, password). Accounts are never self-deleted.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Members: staff and admin; deactivate/delete are admin-only (MemberPolicy)
    Route::resource('members', MemberController::class);
    Route::patch('/members/{member}/deactivate', [MemberController::class, 'deactivate'])->name('members.deactivate');
    Route::patch('/members/{member}/reactivate', [MemberController::class, 'reactivate'])->name('members.reactivate');

    // Payments: create a membership period; delete is admin-only (PaymentPolicy)
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::get('/expiry', ExpiryController::class)->name('expiry.index');

    Route::resource('walkins', WalkinController::class)->only(['index', 'store', 'destroy']);

    // Attendance: check-in for staff and admin; add/correct/delete records is admin-only (AttendancePolicy)
    Route::post('/attendance/records', [AttendanceController::class, 'storeRecord'])->name('attendance.records.store');
    Route::resource('attendance', AttendanceController::class)->except('show')->parameters(['attendance' => 'attendance']);

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{format}', [ReportController::class, 'export'])->name('reports.export');

    // Admin only (enforced by policies)
    Route::resource('plans', PlanController::class)->except('show');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::get('/backups/{file}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::delete('/backups/{file}', [BackupController::class, 'destroy'])->name('backups.destroy');

    Route::get('/activity-log', ActivityLogController::class)->name('activity-log.index');

    Route::get('/staff-accounts', [StaffAccountController::class, 'index'])->name('staff-accounts.index');
    Route::get('/staff-accounts/create', [StaffAccountController::class, 'create'])->name('staff-accounts.create');
    Route::post('/staff-accounts', [StaffAccountController::class, 'store'])->name('staff-accounts.store');
    Route::get('/staff-accounts/{account}/edit', [StaffAccountController::class, 'edit'])->name('staff-accounts.edit');
    Route::put('/staff-accounts/{account}', [StaffAccountController::class, 'update'])->name('staff-accounts.update');
    Route::patch('/staff-accounts/{account}/toggle-active', [StaffAccountController::class, 'toggleActive'])->name('staff-accounts.toggle-active');
});

require __DIR__.'/auth.php';
