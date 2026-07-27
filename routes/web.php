<?php

use App\Http\Controllers\Web\Sterilization\CycleController;
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
        Route::get('{cycle}', [CycleController::class, 'show'])->name('show');
    });
});

require __DIR__.'/settings.php';
