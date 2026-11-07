<?php

use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EvaluationController;
use App\Http\Controllers\Web\ItemController;
use App\Http\Controllers\Web\OrganizationController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\TenderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'create'])->name('home');
Route::middleware('guest:web')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::middleware('auth:web')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::middleware(['web.organization'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/tenders', [TenderController::class, 'index'])->name('tenders.index');
        Route::get('/tenders/create', [TenderController::class, 'create'])->name('tenders.create');
        Route::post('/tenders', [TenderController::class, 'store'])->name('tenders.store');
        Route::get('/tenders/{tender}', [TenderController::class, 'show'])->name('tenders.show');
        Route::get('/tenders/{tender}/edit', [TenderController::class, 'edit'])->name('tenders.edit');
        Route::patch('/tenders/{tender}', [TenderController::class, 'update'])->name('tenders.update');
        Route::get('/tenders/{tender}/items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('/tenders/{tender}/items', [ItemController::class, 'store'])->name('items.store');
        Route::get('/tenders/{tender}/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::patch('/tenders/{tender}/items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('/tenders/{tender}/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/tenders/{tender}/suppliers', [SupplierController::class, 'index'])->name('tenders.suppliers');
        Route::post('/tenders/{tender}/invitations', [SupplierController::class, 'store'])->name('invitations.store');
        Route::get('/tenders/{tender}/publish', [TenderController::class, 'confirm'])->name('tenders.confirm');
        Route::post('/tenders/{tender}/publish', [TenderController::class, 'publish'])->name('tenders.publish');
    });
});

Route::prefix('portal')->name('portal.')->group(function (): void {
    Route::middleware('guest:supplier')->group(function (): void {
        Route::get('/login', [App\Http\Controllers\Portal\AuthController::class, 'create'])->name('login');
        Route::post('/login', [App\Http\Controllers\Portal\AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
    });
    Route::middleware('auth:supplier')->group(function (): void {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::post('/logout', [App\Http\Controllers\Portal\AuthController::class, 'destroy'])->name('logout');
        Route::middleware('supplier.organization')->prefix('/{organization}/invitations/{invitation}')->group(function (): void {
            Route::get('/', [PortalController::class, 'show'])->name('invitation');
            Route::put('/bid', [PortalController::class, 'save'])->name('bid.save');
            Route::post('/bid/submit', [PortalController::class, 'submit'])->name('bid.submit');
        });
    });
});
Route::middleware(['auth:web', 'web.organization'])->group(function (): void {
    Route::get('/tenders/{tender}/evaluation', [EvaluationController::class, 'show'])->name('tenders.evaluation');
    Route::post('/tenders/{tender}/close', [EvaluationController::class, 'close'])->name('tenders.close');
    Route::post('/tenders/{tender}/awards', [EvaluationController::class, 'allocate'])->name('tenders.allocate');
});
