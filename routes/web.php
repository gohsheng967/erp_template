<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\FileCategoryController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectDocumentController;
use App\Http\Controllers\Project\ProjectMilestoneController;
use App\Http\Controllers\Project\ProjectTaskController;

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


    Route::get('/file-categories', [FileCategoryController::class, 'index'])->name('file-categories.index');
    Route::post('/file-categories', [FileCategoryController::class, 'store'])->name('file-categories.store');
    Route::patch('/file-categories/{fileCategory}', [FileCategoryController::class, 'update'])->name('file-categories.update');
    Route::delete('/file-categories/{fileCategory}', [FileCategoryController::class, 'destroy'])->name('file-categories.destroy');


    Route::get('/projects', [ProjectController::class, 'index'])
        ->name('projects.index');

    Route::get('/projects/create', [ProjectController::class, 'create'])
        ->name('projects.create');

    Route::post('/projects', [ProjectController::class, 'store'])
        ->name('projects.store');

    Route::get('/projects/{project}', [ProjectController::class, 'show'])
        ->name('projects.show');

    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])
        ->name('projects.edit');

    Route::put('/projects/{project}', [ProjectController::class, 'update'])
        ->name('projects.update');

    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])
        ->name('projects.destroy');

        // ------------------------------
    // PURCHASE ORDERS
    // ------------------------------
    Route::prefix('purchase')->name('purchase.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{order}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::get('/{order}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
        Route::put('/{order}', [PurchaseOrderController::class, 'update'])->name('update');
        Route::delete('/{order}', [PurchaseOrderController::class, 'destroy'])->name('destroy');
    });

    // ------------------------------
    // CLAIMS
    // ------------------------------
    Route::prefix('claims')->name('claims.')->group(function () {
        Route::get('/', [ClaimController::class, 'index'])->name('index');
        Route::get('/create', [ClaimController::class, 'create'])->name('create');
        Route::post('/', [ClaimController::class, 'store'])->name('store');
        Route::get('/{claim}', [ClaimController::class, 'show'])->name('show');
        Route::get('/{claim}/edit', [ClaimController::class, 'edit'])->name('edit');
        Route::put('/{claim}', [ClaimController::class, 'update'])->name('update');
        Route::delete('/{claim}', [ClaimController::class, 'destroy'])->name('destroy');
    });

    // ------------------------------
    // INVOICE
    // ------------------------------
    Route::prefix('invoice')->name('invoice.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
        Route::put('/{invoice}', [InvoiceController::class, 'update'])->name('update');
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');
    });

    // ------------------------------
    // OFFICE INVENTORY
    // ------------------------------
    Route::get('/inventory/office', [InventoryController::class, 'office'])
        ->name('inventory.office');
    // ------------------------------
    // OPERATION INVENTORY
    // ------------------------------
    Route::get('/inventory/operation', [InventoryController::class, 'operation'])
        ->name('inventory.operation');


    // ----------------------------------------
    // PROJECT DOCUMENTS
    // ----------------------------------------
    Route::post('/projects/{project}/documents/upload', [ProjectDocumentController::class, 'upload'])
        ->name('projects.documents.upload');

    Route::get('/projects/documents/{document}/download', [ProjectDocumentController::class, 'download'])
        ->name('projects.documents.download');

    Route::delete('/projects/documents/{document}', [ProjectDocumentController::class, 'destroy'])
        ->name('projects.documents.delete');



    // ----------------------------------------
    // MILESTONES
    // ----------------------------------------
    Route::post('/projects/{project}/milestones', [ProjectMilestoneController::class, 'store'])
        ->name('projects.milestones.store');

    Route::put('/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])
        ->name('projects.milestones.update');

    Route::delete('/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])
        ->name('projects.milestones.destroy');


    // ----------------------------------------
    // MILESTONE → TASKS
    // ----------------------------------------
    Route::post('/milestones/{milestone}/tasks', [ProjectTaskController::class, 'store'])
        ->name('milestones.tasks.store');

    Route::put('/milestones/{milestone}/tasks/{task}', [ProjectTaskController::class, 'update'])
        ->name('milestones.tasks.update');

    Route::delete('/milestones/{milestone}/tasks/{task}', [ProjectTaskController::class, 'destroy'])
        ->name('milestones.tasks.destroy');

        
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
