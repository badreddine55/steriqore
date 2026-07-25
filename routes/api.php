<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\LogoutEverywhereController;
use App\Http\Controllers\Api\V1\Identity\AcceptInvitationController;
use App\Http\Controllers\Api\V1\Identity\InvitationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Mock\CyclesMockController;
use App\Http\Controllers\Api\V1\Mock\LabelScanMockController;
use App\Http\Controllers\Api\V1\Mock\ProductsMockController;
use App\Http\Controllers\Api\V1\Mock\StockLevelsMockController;
use App\Http\Controllers\Api\V1\SitesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
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

        // MOCK — no backing tables until S2-S3-S4. See each controller's
        // docblock. Shapes are a first draft; refine when the real domain
        // lands, as a documented OpenAPI change, not a silent one.
        Route::get('products', [ProductsMockController::class, 'index'])->name('products.index');
        Route::get('stock-levels', [StockLevelsMockController::class, 'index'])->name('stock-levels.index');
        Route::get('cycles', [CyclesMockController::class, 'index'])->name('cycles.index');
        Route::get('labels/{code}', [LabelScanMockController::class, 'show'])->name('labels.show');
    });
});
