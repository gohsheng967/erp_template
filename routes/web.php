<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\FileCategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\WidgetController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WarehouseController;

use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectDocumentController;
use App\Http\Controllers\Project\ProjectMilestoneController;
use App\Http\Controllers\Project\ProjectTaskController;

use App\Http\Controllers\Project\MilestoneController;

use App\Http\Controllers\Transactions\ClaimsController;
use App\Http\Controllers\Transactions\PurchaseRequestController;
use App\Http\Controllers\Transactions\PurchaseOrderController;
use App\Http\Controllers\Transactions\ArInvoiceController;

use App\Http\Controllers\PettyCash\PettyCashController;
use App\Http\Controllers\PettyCash\PettyCashUsageController;
use App\Http\Controllers\PettyCash\PettyCashTopupController;
use App\Http\Controllers\PettyCash\PettyCashWalletController;
use App\Http\Controllers\PettyCash\PettyCashStatementController;

use App\Http\Controllers\Inventory\VehicleController;
use App\Http\Controllers\Inventory\StockController;


use App\Http\Controllers\Pdf\ClaimPdfController;

use App\Http\Controllers\Auth\MFASetupController;
use App\Http\Controllers\Auth\MFAVerifyController;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;

// Redirect directly to login
Route::get('/', function () {
    return redirect()->route('login');
});

Route::prefix('public')->name('public.')->group(function () {

    Route::get('/vehicles/{publicUuid}', 
        [PublicInventoryController::class, 'vehicle']
    )->name('vehicles.show');

    // future
    // Route::get('/equipment/{publicUuid}', ...)->name('equipment.show');
    // Route::get('/assets/{publicUuid}', ...)->name('assets.show');

});

// ===============================
// Protected Pages AFTER MFA
// ===============================
Route::middleware(['auth', 'auth.mfa'])->group(function () {

    Route::get('action-task-count', [WidgetController::class, 'actionTask'])->name('priority-summary');
    Route::get('action-task-list',  [WidgetController::class, 'actionTaskList'])->name('priority-list');

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

    Route::patch('/projects/{project}/budget', [ProjectController::class, 'updateBudget'])
        ->name('projects.update-budget');


    Route::get('/projects/{project}/claims/summary', [ProjectController::class, 'summary'])
        ->name('projects.claims.summary');

    Route::get('/projects/{project}/purchase-requests/summary', [ProjectController::class, 'purchaseRequestSummary'])
        ->name('projects.pr.summary');

    Route::get('/projects/{project}/ar/summary', [ProjectController::class, 'aRSummary'])
        ->name('projects.ar.summary');

    Route::get('projects/{project}/overview/kpi',  [ProjectController::class, 'kpi'])
        ->name('projects.overview.kpi');

    // ----------------------------------------
    // PROJECT DOCUMENTS
    // ----------------------------------------
    Route::prefix('projects')
        ->name('projects.')
        ->group(function () {

            Route::prefix('{projectUuid}/documents')
                ->name('documents.')
                ->group(function () {

                    Route::get(
                        '/',
                        [ProjectDocumentController::class, 'index']
                    )->name('index');

                    Route::post(
                        '/',
                        [ProjectDocumentController::class, 'upload']
                    )->name('upload');

                    Route::post(
                        '/url',
                        [ProjectDocumentController::class, 'uploadUrl']
                    )->name('upload-url');
                });

            Route::get(
                'documents/{documentUuid}/download',
                [ProjectDocumentController::class, 'download']
            )->name('documents.download');

            Route::delete(
                'documents/{documentUuid}',
                [ProjectDocumentController::class, 'destroy']
            )->name('documents.destroy');
        });



        // ------------------------------
    // PURCHASE ORDERS
    // ------------------------------
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])
            ->name('index');

        Route::get('/{uuid}', [PurchaseOrderController::class, 'show'])
            ->name('show');

        Route::post('/{uuid}/submit', [PurchaseOrderController::class, 'submit'])
            ->name('submit');

        Route::post('/{uuid}/confirm-order', [PurchaseOrderController::class, 'confirmOrder'])
            ->name('confirm-order');

        Route::post('/{uuid}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('cancel');

        Route::post('/{uuid}/update-terms', [PurchaseOrderController::class, 'updateTerms'])->name('update-terms');

        Route::get('/{uuid}/deliveries', [PurchaseOrderController::class, 'deliveries'])->name('deliveries.index');
        
        Route::post('/{uuid}/deliveries', [PurchaseOrderController::class, 'storeDelivery']) ->name('deliveries.store');
        
        Route::delete('/{uuid}/deliveries', [PurchaseOrderController::class, 'destroyDelivery']) ->name('deliveries.destroy');

    });

    // ------------------------------
    // CLAIMS
    // ------------------------------
    Route::prefix('claims')->name('claims.')->group(function () {
        Route::get('/', [ClaimsController::class, 'index'])->name('index');
        Route::get('/create', [ClaimsController::class, 'create'])->name('create');
        Route::post('/', [ClaimsController::class, 'store'])->name('store');
        Route::get('/{claim}', [ClaimsController::class, 'show'])->name('show');
        Route::get('/{claim}/edit', [ClaimsController::class, 'edit'])->name('edit');
        Route::post('/{claim}', [ClaimsController::class, 'update'])->name('update');
        Route::delete('/{claim}', [ClaimsController::class, 'destroy'])->name('destroy');
        Route::post('/{claim}/approval', [ClaimsController::class, 'approval'])->name('approval');
        Route::post('/{claim}/paid', [ClaimsController::class, 'markPaid'])->name('paid');
    });

    Route::prefix('ar-invoices')->name('ar-invoices.')->group(function () {
        Route::get('/', [ArInvoiceController::class, 'index'])
            ->name('index');

        Route::get('/create', [ArInvoiceController::class, 'create'])
            ->name('create');

        Route::post('/', [ArInvoiceController::class, 'store'])
            ->name('store');

        Route::get('/{invoice}', [ArInvoiceController::class, 'show'])
            ->name('show');

        Route::get('/{invoice}/edit', [ArInvoiceController::class, 'edit'])
            ->name('edit');

        Route::post('/{invoice}', [ArInvoiceController::class, 'update'])
            ->name('update');

        Route::post('/{invoice}/cancel', [ArInvoiceController::class, 'cancel'])
            ->name('destroy');

        Route::post('/{invoice}/approval', [ArInvoiceController::class, 'approval'])
            ->name('approval');

        Route::post('/{invoice}/received', [ArInvoiceController::class, 'markReceived'])
            ->name('received');
    });

    Route::prefix('petty-cash')->name('petty-cash.')->group(function () {
        Route::get('/', [PettyCashController::class, 'index'])->name('index');
        Route::post('/usage', [PettyCashUsageController::class, 'store'])->name('usage.store');

        Route::get('/topups', [PettyCashTopupController::class, 'index'])->name('topups.index');
        Route::post('/topups', [PettyCashTopupController::class, 'store'])->name('topups.store');
        Route::post('/topups/{topup}/approve', [PettyCashTopupController::class, 'approve'])->name('topups.approve');
        Route::post('/topups/{topup}/pay', [PettyCashTopupController::class, 'pay'])->name('topups.pay');
        Route::delete('/topups/{topup}', [PettyCashTopupController::class, 'destroy'])->name('topups.destroy');

        Route::get('/wallets', [PettyCashWalletController::class, 'index'])->name('wallets.index');
        Route::get('/wallets/{walletUuid}', [PettyCashStatementController::class, 'show'])->name('wallets.show');
        Route::post('/wallets/claims', [PettyCashStatementController::class, 'storeClaim'])->name('wallets.store.claims');

    });


    // ------------------------------
    // STAKEHOLDERS
    // ------------------------------
    Route::prefix('clients')->resource('clients', ClientController::class)->except(['create', 'edit']);

    Route::prefix('suppliers')->name('suppliers.')->group(function () {

        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::get('/create', [SupplierController::class, 'create'])->name('create');
        Route::post('/', [SupplierController::class, 'store'])->name('store');

        Route::get('/search', [SupplierController::class, 'search'])->name('search');
        Route::post('/inline', [SupplierController::class, 'inlineStore'])->name('inline');
        Route::get('/{uuid}', [SupplierController::class, 'show'])->name('show');
        Route::get('/{uuid}/edit', [SupplierController::class, 'edit'])->name('edit');
        Route::post('/{uuid}', [SupplierController::class, 'update'])->name('update');

        Route::delete('/{uuid}', [SupplierController::class, 'destroy'])->name('destroy');

        Route::patch('/{uuid}/note', [SupplierController::class, 'updateNote'])->name('update-note');

        Route::post('/{uuid}/purchase-quotations/upload', [SupplierController::class, 'upload'])->name('purchase-quotations.upload');
        Route::delete('/{uuid}/purchase-quotations/{quotation}', [SupplierController::class, 'destroyQuotation'])->name('purchase-quotations.destroy');

        Route::get('/simple-list/options', [SupplierController::class, 'list'])->name('simple-list');
    });

    Route::prefix('purchase-request')->name('purchase-request.')->group(function () {
        Route::get('/',[PurchaseRequestController::class, 'index'])->name('index');
        Route::post('/purchase-quotations', [PurchaseRequestController::class, 'addQuotations'])->name('add-quote');
        Route::get('/init-form',[PurchaseRequestController::class, 'initPurchaseRequestForm'])->name('init-form');
        Route::get('/{uuid}/show',[PurchaseRequestController::class, 'show'])->name('show');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('store');
        Route::get('/{uuid}/edit', [PurchaseRequestController::class, 'edit'])->name('edit');
        Route::put('{uuid}', [PurchaseRequestController::class, 'update'])->name('update');
        Route::post('{uuid}/submit', [PurchaseRequestController::class, 'submit'])->name('submit');
        Route::get('{uuid}/supplier/{supplier_uuid}/quotations', [PurchaseRequestController::class, 'quotations'])->name('quotations');
        Route::post('/{uuid}/quotations/attach', [PurchaseRequestController::class, 'attachQuotation'])->name('quotations.attach');
        Route::delete('/{uuid}/quotations/{quotationUuid}', [PurchaseRequestController::class, 'detachQuotation'])->name('detach-quotation');

        Route::delete('/{uuid}', [PurchaseRequestController::class, 'destroy'])->name('destroy');
        Route::post('/{uuid}/approval', [PurchaseRequestController::class, 'approval'])->name('approval');

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

    Route::prefix('inventory')->name('inventory.')->group(function () {

        Route::prefix('vehicles')->name('vehicles.')->group(function () {

            Route::get('/', [VehicleController::class, 'index'])->name('index');
            Route::post('/', [VehicleController::class, 'store'])->name('store');
            Route::put('/{uuid}', [VehicleController::class, 'update'])->name('update');
            Route::delete('/{uuid}', [VehicleController::class, 'destroy'])->name('destroy');
            Route::get('/{uuid}', [VehicleController::class, 'show'])->name('show');
            Route::get('/{uuid}/qr', [VehicleController::class, 'qrCode'])->name('qr');
            Route::post('/{uuid}/allocate', [VehicleController::class, 'allocate'])->name('allocate');            
            Route::get('/users/list', [VehicleController::class, 'loadUsers'])->name('allocatable.users');
            Route::get('/projects/list', [VehicleController::class, 'loadProjects'])->name('allocatable.projects');
            Route::get('/{uuid}/compliance', [VehicleController::class, 'compliance'])->name('compliance');
            Route::post('{uuid}/insurance', [VehicleController::class, 'storeInsurance'])->name('insurance.store');
            Route::post('{uuid}/insurance/{insurance}', [VehicleController::class, 'updateInsurance'])->name('insurance.update');
            Route::post('{uuid}/insurance/trigger/renew', [VehicleController::class, 'renewInsurance'])->name('insurance.renew');

            Route::post('{uuid}/roadtax', [VehicleController::class, 'storeRoadtax'])->name('roadtax.store');
            Route::post('{uuid}/roadtax/{roadtax}', [VehicleController::class, 'updateRoadtax'])->name('roadtax.update');
            Route::post('{uuid}/roadtax/trigger/renew', [VehicleController::class, 'renewRoadtax'])->name('roadtax.renew');
            // Route::post('/{uuid}/insurance', [VehicleController::class, 'storeInsurance'])->name('insurance.store');
            // Route::post('/{uuid}/roadtax', [VehicleController::class, 'storeRoadtax'])->name('roadtax.store');
            // Route::post('/{uuid}/saman', [VehicleController::class, 'storeSaman'])->name('saman.store');

        });

        Route::prefix('stocks')->name('stocks.')->group(function () {
            Route::get('/', [StockController::class, 'index'])->name('index');
            Route::get('/movements', [StockController::class, 'movements'])->name('movements');
            Route::post('/issue', [StockController::class, 'issue'])->name('issue');
            Route::post('/transfer', [StockController::class, 'transfer'])->name('transfer');
            Route::post('/adjust', [StockController::class, 'adjust'])->name('adjust');
        });
        
    });
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

// ------------------------------
    // DOCUMENT MODULE
    // ------------------------------
    Route::prefix('documents')->group(function () {

        // ----------------------------------------
        // DOCUMENT TYPE LIST (CRUD)
        // ----------------------------------------
        Route::get('/types', [DocumentController::class, 'types'])
            ->name('documents.types');

        Route::post('/types', [DocumentController::class, 'storeType'])
            ->name('documents.types.store');

        Route::patch('/types/{documentType}', [DocumentController::class, 'updateType'])
            ->name('documents.types.update');

        Route::delete('/types/{documentType}', [DocumentController::class, 'destroyType'])
            ->name('documents.types.delete');


        // ----------------------------------------
        // FIELD BUILDER
        // ----------------------------------------
        Route::get('/{code}/fields', [DocumentController::class, 'editFields'])
            ->name('documents.fields');

        Route::post('/{code}/fields/save', [DocumentController::class, 'saveFields'])
            ->name('documents.fields.save');


        // ----------------------------------------
        // TEMPLATE EDITOR
        // ----------------------------------------
        Route::get('/{code}/template', [DocumentController::class, 'editTemplate'])
            ->name('documents.template.edit');

        Route::post('/{code}/template/update', [DocumentController::class, 'updateTemplate'])
            ->name('documents.template.update');


        // ----------------------------------------
        // PDF PREVIEW (From Template)
        // ----------------------------------------
        Route::post('/{code}/preview', [DocumentController::class, 'previewTemplate'])
            ->name('documents.template.preview');

    });

    Route::prefix('projects/{project}')->group(function () {

        Route::post('milestones', 
            [MilestoneController::class, 'store']
        )->name('milestones.store');

        Route::post('milestones/{milestone}/action-tasks',
            [MilestoneController::class, 'storeActionTask']
        )->name('milestone.action-tasks.store');

        Route::patch('action-tasks/{task}',
            [MilestoneController::class, 'updateActionTask']
        )->name('milestone.action-tasks.update');

        Route::delete('action-tasks/{task}',
            [MilestoneController::class, 'destroyActionTask']
        )->name('milestone.action-tasks.destroy');

        Route::post('milestones/{milestone}/phases',
            [MilestoneController::class, 'storePhase']
        )->name('milestone.phases.store');

        Route::patch('phases/{phase}',
            [MilestoneController::class, 'updatePhase']
        )->name('milestone.phases.update');

        Route::post('phases/reorder',
            [MilestoneController::class, 'reorderPhases']
        )->name('milestone.phases.reorder');

        Route::post('phases/{phase}/status',
            [MilestoneController::class, 'changePhaseStatus']
        )->name('milestone.phases.status');

        Route::post('phases/{phase}/tasks',
            [MilestoneController::class, 'storePhaseTask']
        )->name('milestone.phase-tasks.store');

        Route::patch('phase-tasks/{task}',
            [MilestoneController::class, 'updatePhaseTask']
        )->name('milestone.phase-tasks.update');

        Route::delete('phase-tasks/{task}',
            [MilestoneController::class, 'destroyPhaseTask']
        )->name('milestone.phase-tasks.destroy');
    });    
    
    Route::prefix('warehouses')->group(function () {
        Route::resource('/', WarehouseController::class)
            ->names('warehouses')
            ->except(['show']);
    });

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
