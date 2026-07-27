<?php

use App\Http\Controllers\Web\Sterilization\ControlTestController;
use App\Http\Controllers\Web\Sterilization\CycleAttachmentController;
use App\Http\Controllers\Web\Sterilization\CycleControlTestController;
use App\Http\Controllers\Web\Sterilization\CycleController;
use App\Http\Controllers\Web\Sterilization\CycleItemController;
use App\Http\Controllers\Web\Tenancy\SwitchSiteController;
use App\Http\Controllers\Web\Tenancy\SwitchTenantController;
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
    });

    Route::get('control-tests', [ControlTestController::class, 'index'])->name('control-tests.index');
});

require __DIR__.'/settings.php';
