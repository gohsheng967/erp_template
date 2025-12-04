<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoleController;
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
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/company-profile', [CompanyProfileController::class, 'index'])->name('company.profile');
    Route::post('/company-profile', [CompanyProfileController::class, 'update'])->name('company.profile.update');


    Route::get('/users', [UserController::class, 'index'])->name('users.index');

    Route::post('/users', [UserController::class, 'store'])->name('users.store');

    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->name('users.reset-password');

    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
        ->name('users.toggle-status');

    // Department pages
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    // Roles under department
    Route::post('/departments/{department}/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // Permission Management (view roles → assign permissions)
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->name('permissions.index');

    // Load permissions for a specific role
    Route::get('/permissions/{role}', [PermissionController::class, 'show'])
        ->name('permissions.show');

    // Save updated permissions for a role
    Route::post('/permissions/{role}/update', [PermissionController::class, 'update'])
        ->name('permissions.update');
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
