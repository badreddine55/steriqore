<?php

use App\Http\Controllers\Api\V1\AuditEventController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\LogoutEverywhereController;
use App\Http\Controllers\Api\V1\Catalog\ProductCategoryController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Compliance\NonConformityController;
use App\Http\Controllers\Api\V1\Equipment\DeviceController;
use App\Http\Controllers\Api\V1\Equipment\DeviceProgramController;
use App\Http\Controllers\Api\V1\Equipment\MaintenanceRecordController;
use App\Http\Controllers\Api\V1\Identity\AcceptInvitationController;
use App\Http\Controllers\Api\V1\Identity\InvitationController;
use App\Http\Controllers\Api\V1\Identity\MemberController;
use App\Http\Controllers\Api\V1\Identity\PatientPortalAccountController;
use App\Http\Controllers\Api\V1\Identity\PatientPortalDossierController;
use App\Http\Controllers\Api\V1\Identity\PatientPortalEvidenceController;
use App\Http\Controllers\Api\V1\Identity\PatientPortalLoginController;
use App\Http\Controllers\Api\V1\Identity\PatientPortalLogoutController;
use App\Http\Controllers\Api\V1\Identity\PatientPortalMeController;
use App\Http\Controllers\Api\V1\Identity\PatientPortalPasswordController;
use App\Http\Controllers\Api\V1\Identity\PatientPortalProstheticCasesController;
use App\Http\Controllers\Api\V1\Identity\RegisterTenantController;
use App\Http\Controllers\Api\V1\Identity\UserController;
use App\Http\Controllers\Api\V1\Inventory\AlertController;
use App\Http\Controllers\Api\V1\Inventory\StockLevelController;
use App\Http\Controllers\Api\V1\Inventory\StockMovementController;
use App\Http\Controllers\Api\V1\Labeling\CycleLabelController;
use App\Http\Controllers\Api\V1\Labeling\DluRuleController;
use App\Http\Controllers\Api\V1\Labeling\LabelController;
use App\Http\Controllers\Api\V1\Labeling\LabelScanController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Api\V1\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Purchasing\SupplierController;
use App\Http\Controllers\Api\V1\Purchasing\SupplierProductController;
use App\Http\Controllers\Api\V1\Reporting\DataExportRequestController;
use App\Http\Controllers\Api\V1\Reporting\EvidenceExportController;
use App\Http\Controllers\Api\V1\SitesController;
use App\Http\Controllers\Api\V1\Sterilization\ControlTestController;
use App\Http\Controllers\Api\V1\Sterilization\CycleAttachmentController;
use App\Http\Controllers\Api\V1\Sterilization\CycleController;
use App\Http\Controllers\Api\V1\Sterilization\CycleItemController;
use App\Http\Controllers\Api\V1\Sterilization\CycleReleaseController;
use App\Http\Controllers\Api\V1\Prosthetics\LaboratoryController;
use App\Http\Controllers\Api\V1\Prosthetics\ProstheticCaseController;
use App\Http\Controllers\Api\V1\Prosthetics\ProstheticDashboardController;
use App\Http\Controllers\Api\V1\Traceability\EvidenceSearchController;
use App\Http\Controllers\Api\V1\Traceability\LabelUsageController;
use App\Http\Controllers\Api\V1\Traceability\PatientController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:public-api')->group(function () {
        Route::post('tenants', RegisterTenantController::class)->middleware('idempotent')->name('tenants.register');
        Route::post('auth/login', LoginController::class)->middleware('idempotent')->name('auth.login');
        Route::post('invitations/accept', AcceptInvitationController::class)->middleware('idempotent')->name('invitations.accept');
        Route::post('patient-portal/login', PatientPortalLoginController::class)->middleware('idempotent')->name('patient-portal.login');
    });

    Route::middleware(['auth:sanctum', 'tenant', 'not_patient_portal', 'throttle:api'])->group(function () {
        Route::delete('auth/logout', LogoutController::class)->name('auth.logout');
        Route::delete('auth/tokens', LogoutEverywhereController::class)->name('auth.logout-everywhere');
        Route::get('me', MeController::class)->name('me');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.patch');

        Route::get('sites', [SitesController::class, 'index'])->name('sites.index');

        Route::post('invitations', [InvitationController::class, 'store'])
            ->middleware('idempotent')
            ->name('invitations.store');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::delete('members/{tenantUser}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('audit-events', [AuditEventController::class, 'index'])->name('audit-events.index');

        Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
        Route::post('product-categories', [ProductCategoryController::class, 'store'])
            ->middleware('idempotent')
            ->name('product-categories.store');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])
            ->middleware('idempotent')
            ->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])
            ->middleware('idempotent')
            ->name('suppliers.store');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('suppliers/{supplier}/products', [SupplierProductController::class, 'index'])->name('suppliers.products.index');
        Route::post('suppliers/{supplier}/products', [SupplierProductController::class, 'store'])
            ->middleware('idempotent')
            ->name('suppliers.products.store');

        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
            ->middleware('idempotent')
            ->name('purchase-orders.store');
        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::post('purchase-orders/{purchaseOrder}/order', [PurchaseOrderController::class, 'order'])
            ->middleware('idempotent')
            ->name('purchase-orders.order');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->middleware('idempotent')
            ->name('purchase-orders.cancel');
        Route::post('purchase-orders/{purchaseOrder}/receipts', [GoodsReceiptController::class, 'store'])
            ->middleware('idempotent')
            ->name('purchase-orders.receipts.store');

        Route::get('stock-levels', [StockLevelController::class, 'index'])->name('stock-levels.index');
        Route::post('stock-levels/rebuild', [StockLevelController::class, 'rebuild'])
            ->middleware('idempotent')
            ->name('stock-levels.rebuild');
        Route::post('stock-movements/issue', [StockMovementController::class, 'issue'])
            ->middleware('idempotent')
            ->name('stock-movements.issue');
        Route::post('stock-movements/transfer', [StockMovementController::class, 'transfer'])
            ->middleware('idempotent')
            ->name('stock-movements.transfer');
        Route::post('stock-movements/adjust', [StockMovementController::class, 'adjust'])
            ->middleware('idempotent')
            ->name('stock-movements.adjust');

        Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::post('alerts/detect', [AlertController::class, 'detect'])
            ->middleware('idempotent')
            ->name('alerts.detect');
        Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])
            ->middleware('idempotent')
            ->name('alerts.resolve');

        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('devices', [DeviceController::class, 'store'])
            ->middleware('idempotent')
            ->name('devices.store');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
        Route::patch('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

        Route::get('devices/{device}/programs', [DeviceProgramController::class, 'index'])->name('devices.programs.index');
        Route::post('devices/{device}/programs', [DeviceProgramController::class, 'store'])
            ->middleware('idempotent')
            ->name('devices.programs.store');
        Route::patch('devices/{device}/programs/{program}', [DeviceProgramController::class, 'update'])->name('devices.programs.update');
        Route::delete('devices/{device}/programs/{program}', [DeviceProgramController::class, 'destroy'])->name('devices.programs.destroy');

        Route::get('devices/{device}/maintenance-records', [MaintenanceRecordController::class, 'index'])->name('devices.maintenance-records.index');
        Route::post('devices/{device}/maintenance-records', [MaintenanceRecordController::class, 'store'])
            ->middleware('idempotent')
            ->name('devices.maintenance-records.store');

        Route::get('cycles', [CycleController::class, 'index'])->name('cycles.index');
        Route::post('cycles', [CycleController::class, 'store'])
            ->middleware('idempotent')
            ->name('cycles.store');
        Route::get('cycles/{cycle}', [CycleController::class, 'show'])->name('cycles.show');
        Route::post('cycles/{cycle}/start', [CycleController::class, 'start'])
            ->middleware('idempotent')
            ->name('cycles.start');
        Route::post('cycles/{cycle}/complete', [CycleController::class, 'complete'])
            ->middleware('idempotent')
            ->name('cycles.complete');
        Route::post('cycles/{cycle}/submit-for-release', [CycleController::class, 'submitForRelease'])
            ->middleware('idempotent')
            ->name('cycles.submit-for-release');

        Route::get('cycles/{cycle}/items', [CycleItemController::class, 'index'])->name('cycles.items.index');
        Route::post('cycles/{cycle}/items', [CycleItemController::class, 'store'])
            ->middleware('idempotent')
            ->name('cycles.items.store');
        Route::delete('cycles/{cycle}/items/{item}', [CycleItemController::class, 'destroy'])->name('cycles.items.destroy');

        Route::get('cycles/{cycle}/control-tests', [ControlTestController::class, 'index'])->name('cycles.control-tests.index');
        Route::post('cycles/{cycle}/control-tests', [ControlTestController::class, 'store'])
            ->middleware('idempotent')
            ->name('cycles.control-tests.store');

        Route::get('cycles/{cycle}/attachments', [CycleAttachmentController::class, 'index'])->name('cycles.attachments.index');
        Route::post('cycles/{cycle}/attachments', [CycleAttachmentController::class, 'store'])
            ->middleware('idempotent')
            ->name('cycles.attachments.store');
        Route::delete('cycles/{cycle}/attachments/{media}', [CycleAttachmentController::class, 'destroy'])->name('cycles.attachments.destroy');

        Route::get('cycles/{cycle}/release', [CycleReleaseController::class, 'show'])->name('cycles.release.show');
        Route::post('cycles/{cycle}/release', [CycleReleaseController::class, 'store'])
            ->middleware('idempotent')
            ->name('cycles.release.store');

        Route::get('dlu-rules', [DluRuleController::class, 'index'])->name('dlu-rules.index');
        Route::post('dlu-rules', [DluRuleController::class, 'store'])
            ->middleware('idempotent')
            ->name('dlu-rules.store');
        Route::patch('dlu-rules/{rule}', [DluRuleController::class, 'update'])->name('dlu-rules.update');
        Route::delete('dlu-rules/{rule}', [DluRuleController::class, 'destroy'])->name('dlu-rules.destroy');

        Route::get('cycles/{cycle}/labels', [CycleLabelController::class, 'index'])->name('cycles.labels.index');
        Route::post('cycles/{cycle}/labels', [CycleLabelController::class, 'store'])
            ->middleware('idempotent')
            ->name('cycles.labels.store');

        Route::post('labels/{label}/print', [LabelController::class, 'print'])
            ->middleware('idempotent')
            ->name('labels.print');
        Route::get('labels/{label}/pdf', [LabelController::class, 'pdf'])->name('labels.pdf');
        Route::get('labels/{label}/qr-code', [LabelController::class, 'qrCode'])->name('labels.qr-code');
        Route::get('labels/{label}/datamatrix', [LabelController::class, 'dataMatrix'])->name('labels.datamatrix');
        Route::get('labels/{label}/usage', [LabelUsageController::class, 'show'])->name('labels.usage.show');
        Route::post('labels/{label}/usage', [LabelUsageController::class, 'store'])
            ->middleware('idempotent')
            ->name('labels.usage.store');
        Route::get('labels/{label}/usage/dossier', [LabelUsageController::class, 'dossier'])->name('labels.usage.dossier');

        Route::get('labels/{code}', [LabelScanController::class, 'show'])->name('labels.show');

        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
        Route::post('patients', [PatientController::class, 'store'])
            ->middleware('idempotent')
            ->name('patients.store');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::delete('patients/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');
        Route::post('patients/{patient}/portal-account', PatientPortalAccountController::class)
            ->middleware('idempotent')
            ->name('patients.portal-account');

        Route::get('laboratories', [LaboratoryController::class, 'index'])->name('laboratories.index');

        Route::get('prosthetic-cases/dashboard', ProstheticDashboardController::class)->name('prosthetic-cases.dashboard');
        Route::get('prosthetic-cases/waiting-placement', [ProstheticCaseController::class, 'waitingPlacement'])->name('prosthetic-cases.waiting-placement');
        Route::get('prosthetic-cases', [ProstheticCaseController::class, 'index'])->name('prosthetic-cases.index');
        Route::post('prosthetic-cases', [ProstheticCaseController::class, 'store'])
            ->middleware('idempotent')
            ->name('prosthetic-cases.store');
        Route::get('prosthetic-cases/{id}', [ProstheticCaseController::class, 'show'])->name('prosthetic-cases.show');
        Route::patch('prosthetic-cases/{id}', [ProstheticCaseController::class, 'update'])
            ->middleware('idempotent')
            ->name('prosthetic-cases.update');
        Route::post('prosthetic-cases/{id}/status', [ProstheticCaseController::class, 'changeStatus'])
            ->middleware('idempotent')
            ->name('prosthetic-cases.status');
        Route::get('prosthetic-cases/{id}/history', [ProstheticCaseController::class, 'history'])->name('prosthetic-cases.history');
        Route::delete('prosthetic-cases/{id}', [ProstheticCaseController::class, 'destroy'])->name('prosthetic-cases.destroy');

        Route::get('evidence-search', [EvidenceSearchController::class, 'index'])->name('evidence-search.index');
        Route::get('evidence-search/export', [EvidenceExportController::class, 'index'])->name('evidence-search.export');

        Route::get('data-export-requests', [DataExportRequestController::class, 'index'])
            ->name('data-export-requests.index');
        Route::post('data-export-requests', [DataExportRequestController::class, 'store'])
            ->middleware('idempotent')
            ->name('data-export-requests.store');
        Route::get('data-export-requests/{dataExportRequest}', [DataExportRequestController::class, 'show'])
            ->name('data-export-requests.show');
        Route::post('data-export-requests/{dataExportRequest}/download', [DataExportRequestController::class, 'download'])
            ->name('data-export-requests.download');

        Route::get('non-conformities', [NonConformityController::class, 'index'])->name('non-conformities.index');
        Route::post('non-conformities', [NonConformityController::class, 'store'])
            ->middleware('idempotent')
            ->name('non-conformities.store');
        Route::get('non-conformities/{nonConformity}', [NonConformityController::class, 'show'])->name('non-conformities.show');
        Route::post('non-conformities/{nonConformity}/resolve', [NonConformityController::class, 'resolve'])
            ->middleware('idempotent')
            ->name('non-conformities.resolve');
    });

    Route::middleware(['auth:sanctum', 'tenant', 'patient_portal', 'throttle:api'])->prefix('patient-portal')->name('patient-portal.')->group(function () {
        Route::delete('logout', PatientPortalLogoutController::class)->name('logout');
        Route::get('me', PatientPortalMeController::class)->name('me');
        Route::patch('me/password', PatientPortalPasswordController::class)->middleware('idempotent')->name('me.password');
        Route::get('evidence', PatientPortalEvidenceController::class)->name('evidence');
        Route::get('cases', PatientPortalProstheticCasesController::class)->name('cases');
        Route::get('usage/{labelUsage}/dossier', PatientPortalDossierController::class)->name('dossier');
    });
});
