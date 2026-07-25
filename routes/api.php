<?php

use App\Http\Controllers\Api\V1\AuditEventController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\LogoutEverywhereController;
use App\Http\Controllers\Api\V1\Catalog\ProductCategoryController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Identity\AcceptInvitationController;
use App\Http\Controllers\Api\V1\Identity\InvitationController;
use App\Http\Controllers\Api\V1\Identity\MemberController;
use App\Http\Controllers\Api\V1\Identity\RegisterTenantController;
use App\Http\Controllers\Api\V1\Inventory\StockLevelController;
use App\Http\Controllers\Api\V1\Inventory\StockMovementController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Mock\CyclesMockController;
use App\Http\Controllers\Api\V1\Mock\LabelScanMockController;
use App\Http\Controllers\Api\V1\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Api\V1\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Purchasing\SupplierController;
use App\Http\Controllers\Api\V1\Purchasing\SupplierProductController;
use App\Http\Controllers\Api\V1\SitesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('tenants', RegisterTenantController::class)->middleware('idempotent')->name('tenants.register');
    Route::post('auth/login', LoginController::class)->middleware('idempotent')->name('auth.login');
    Route::post('invitations/accept', AcceptInvitationController::class)->middleware('idempotent')->name('invitations.accept');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::delete('auth/logout', LogoutController::class)->name('auth.logout');
        Route::delete('auth/tokens', LogoutEverywhereController::class)->name('auth.logout-everywhere');
        Route::get('me', MeController::class)->name('me');

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

        // MOCK — no backing tables until S3-S4. See each controller's
        // docblock. Shapes are a first draft; refine when the real domain
        // lands, as a documented OpenAPI change, not a silent one.
        Route::get('cycles', [CyclesMockController::class, 'index'])->name('cycles.index');
        Route::get('labels/{code}', [LabelScanMockController::class, 'show'])->name('labels.show');
    });
});
