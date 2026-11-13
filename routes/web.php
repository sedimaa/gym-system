<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffAccountController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // Own profile (name, e-mail, password). Accounts are never self-deleted.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Admin only (enforced by UserPolicy)
    Route::get('/staff-accounts', [StaffAccountController::class, 'index'])->name('staff-accounts.index');
    Route::get('/staff-accounts/create', [StaffAccountController::class, 'create'])->name('staff-accounts.create');
    Route::post('/staff-accounts', [StaffAccountController::class, 'store'])->name('staff-accounts.store');
    Route::get('/staff-accounts/{account}/edit', [StaffAccountController::class, 'edit'])->name('staff-accounts.edit');
    Route::put('/staff-accounts/{account}', [StaffAccountController::class, 'update'])->name('staff-accounts.update');
    Route::patch('/staff-accounts/{account}/toggle-active', [StaffAccountController::class, 'toggleActive'])->name('staff-accounts.toggle-active');
});

require __DIR__.'/auth.php';
