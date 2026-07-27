<?php

use App\Http\Controllers\Web\Catalog\ProductCategoryController;
use App\Http\Controllers\Web\Catalog\ProductController;
use App\Http\Controllers\Web\Compliance\NonConformityController;
use App\Http\Controllers\Web\Equipment\DeviceController;
use App\Http\Controllers\Web\Equipment\DeviceProgramController;
use App\Http\Controllers\Web\Equipment\MaintenanceRecordController;
use App\Http\Controllers\Web\Inventory\AlertController;
use App\Http\Controllers\Web\Inventory\BatchController;
use App\Http\Controllers\Web\Inventory\StockLevelController;
use App\Http\Controllers\Web\Inventory\StockMovementController as WebStockMovementController;
use App\Http\Controllers\Web\Labeling\CycleLabelController;
use App\Http\Controllers\Web\Labeling\LabelController;
use App\Http\Controllers\Web\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Web\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Web\Purchasing\SupplierController;
use App\Http\Controllers\Web\Purchasing\SupplierProductController;
use App\Http\Controllers\Web\Sterilization\ControlTestController;
use App\Http\Controllers\Web\Sterilization\CycleAttachmentController;
use App\Http\Controllers\Web\Sterilization\CycleController;
use App\Http\Controllers\Web\Sterilization\CycleControlTestController;
use App\Http\Controllers\Web\Sterilization\CycleItemController;
use App\Http\Controllers\Web\Tenancy\SwitchSiteController;
use App\Http\Controllers\Web\Tenancy\SwitchTenantController;
use App\Http\Controllers\Web\Traceability\EvidenceSearchController;
use App\Http\Controllers\Web\Traceability\LabelUsageController;
use App\Http\Controllers\Web\Traceability\PatientController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'tenant.web'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::post('tenant/switch', [SwitchTenantController::class, 'store'])->name('tenant.switch');
    Route::post('site/switch', [SwitchSiteController::class, 'store'])->name('site.switch');

    Route::prefix('cycles')->name('cycles.')->group(function () {
        Route::get('/', [CycleController::class, 'index'])->name('index');
        Route::post('/', [CycleController::class, 'store'])->name('store');
        Route::get('{cycle}', [CycleController::class, 'show'])->name('show');
        Route::post('{cycle}/start', [CycleController::class, 'start'])->name('start');
        Route::post('{cycle}/complete', [CycleController::class, 'complete'])->name('complete');
        Route::post('{cycle}/submit-for-release', [CycleController::class, 'submitForRelease'])->name('submit-for-release');
        Route::post('{cycle}/release', [CycleController::class, 'release'])->name('release');

        Route::post('{cycle}/items', [CycleItemController::class, 'store'])->name('items.store');
        Route::delete('{cycle}/items/{item}', [CycleItemController::class, 'destroy'])->name('items.destroy');

        Route::post('{cycle}/attachments', [CycleAttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('{cycle}/attachments/{media}', [CycleAttachmentController::class, 'destroy'])->name('attachments.destroy');

        Route::post('{cycle}/control-tests', [CycleControlTestController::class, 'store'])->name('control-tests.store');

        Route::get('{cycle}/labels', [CycleLabelController::class, 'index'])->name('labels.index');
        Route::post('{cycle}/labels', [CycleLabelController::class, 'store'])->name('labels.store');
    });

    Route::get('control-tests', [ControlTestController::class, 'index'])->name('control-tests.index');

    Route::prefix('devices')->name('devices.')->group(function () {
        Route::get('/', [DeviceController::class, 'index'])->name('index');
        Route::post('/', [DeviceController::class, 'store'])->name('store');
        Route::get('{device}', [DeviceController::class, 'show'])->name('show');
        Route::patch('{device}', [DeviceController::class, 'update'])->name('update');

        Route::post('{device}/programs', [DeviceProgramController::class, 'store'])->name('programs.store');
        Route::patch('{device}/programs/{program}', [DeviceProgramController::class, 'update'])->name('programs.update');
        Route::delete('{device}/programs/{program}', [DeviceProgramController::class, 'destroy'])->name('programs.destroy');

        Route::post('{device}/maintenance-records', [MaintenanceRecordController::class, 'store'])->name('maintenance-records.store');
    });

    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('{product}', [ProductController::class, 'show'])->name('show');
        Route::patch('{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('{product}', [ProductController::class, 'destroy'])->name('destroy');
    });

    Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');

    Route::get('batches', [BatchController::class, 'index'])->name('batches.index');

    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [StockLevelController::class, 'index'])->name('index');
        Route::get('movements', [WebStockMovementController::class, 'index'])->name('movements');
    });

    Route::prefix('alerts')->name('alerts.')->group(function () {
        Route::get('/', [AlertController::class, 'index'])->name('index');
        Route::post('{alert}/resolve', [AlertController::class, 'resolve'])->name('resolve');
    });

    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/', [PatientController::class, 'index'])->name('index');
        Route::post('/', [PatientController::class, 'store'])->name('store');
        Route::get('{patient}', [PatientController::class, 'show'])->name('show');
        Route::delete('{patient}', [PatientController::class, 'destroy'])->name('destroy');
    });

    Route::get('evidence-search', [EvidenceSearchController::class, 'index'])->name('evidence-search.index');
    Route::get('evidence-search/export', [EvidenceSearchController::class, 'export'])->name('evidence-search.export');

    Route::prefix('non-conformities')->name('non-conformities.')->group(function () {
        Route::get('/', [NonConformityController::class, 'index'])->name('index');
        Route::post('/', [NonConformityController::class, 'store'])->name('store');
        Route::post('{nonConformity}/resolve', [NonConformityController::class, 'resolve'])->name('resolve');
    });

    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::patch('{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::delete('{supplier}', [SupplierController::class, 'destroy'])->name('destroy');

        Route::post('{supplier}/products', [SupplierProductController::class, 'store'])->name('products.store');
    });

    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('create', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::post('{purchaseOrder}/order', [PurchaseOrderController::class, 'order'])->name('order');
        Route::post('{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');

        Route::post('{purchaseOrder}/receipts', [GoodsReceiptController::class, 'store'])->name('receipts.store');
    });

    Route::prefix('labels')->name('labels.')->group(function () {
        Route::get('/', [LabelController::class, 'index'])->name('index');
        Route::get('{label}', [LabelController::class, 'show'])->name('show');
        Route::post('{label}/print', [LabelController::class, 'print'])->name('print');
        Route::get('{label}/pdf', [LabelController::class, 'pdf'])->name('pdf');
        Route::get('{label}/qr-code', [LabelController::class, 'qrCode'])->name('qr-code');
        Route::get('{label}/datamatrix', [LabelController::class, 'dataMatrix'])->name('datamatrix');
        Route::post('{label}/usage', [LabelUsageController::class, 'store'])->name('usage.store');
        Route::get('{label}/usage/dossier', [LabelUsageController::class, 'dossier'])->name('usage.dossier');
    });
});

require __DIR__.'/settings.php';
