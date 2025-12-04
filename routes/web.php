<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\MFASetupController;
use App\Http\Controllers\Auth\MFAVerifyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;

// Redirect directly to login
Route::get('/', function () {
    return redirect()->route('login');
});

// ===============================
// Protected Pages AFTER MFA
// ===============================
Route::middleware(['auth', 'auth.mfa'])->group(function () {

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


// ===============================
// MFA Setup (ONLY BEFORE fully verified)
// ===============================
Route::middleware(['auth'])->group(function () {

    // Step 1: Show QR + secret
    Route::get('/mfa/setup', [MFASetupController::class, 'showSetup'])
        ->name('mfa.setup');

    // Step 2: Verify first-time MFA code (enrollment)
    Route::post('/mfa/setup/verify', [MFASetupController::class, 'verify'])
        ->name('mfa.setup.verify');
});


// ===============================
// MFA Login Verification
// ===============================
Route::middleware('auth')->group(function () {

    // Show MFA verify screen
    Route::get('/mfa/verify', [MFAVerifyController::class, 'index'])
        ->name('mfa.verify');

    // Submit MFA code during login
    Route::post('/mfa/verify', [MFAVerifyController::class, 'verify']);
});


require __DIR__ . '/auth.php';
