<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TenderController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'bidflow']));
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', 'buyer.identity'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::middleware(['organization', 'abilities:rfq:read'])->group(function (): void {
            Route::get('/tenders', [TenderController::class, 'index']);
            Route::get('/tenders/{tender}', [TenderController::class, 'show']);
            Route::get('/tenders/{tender}/items', [ItemController::class, 'index']);
            Route::get('/tenders/{tender}/invitations', [InvitationController::class, 'index']);
            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::middleware('abilities:rfq:write')->group(function (): void {
                Route::post('/tenders', [TenderController::class, 'store']);
                Route::patch('/tenders/{tender}', [TenderController::class, 'update']);
                Route::post('/tenders/{tender}/items', [ItemController::class, 'store']);
                Route::patch('/tenders/{tender}/items/{item}', [ItemController::class, 'update']);
                Route::delete('/tenders/{tender}/items/{item}', [ItemController::class, 'destroy']);
                Route::post('/tenders/{tender}/invitations', [InvitationController::class, 'store']);
                Route::post('/tenders/{tender}/publish', [TenderController::class, 'publish']);
            });
        });
    });
});

Route::prefix('v1/portal')->group(function (): void {
    Route::post('/auth/login', [App\Http\Controllers\Portal\AuthController::class, 'store'])->middleware('throttle:login');
    Route::middleware('auth:supplier-api')->group(function (): void {
        Route::post('/auth/logout', [App\Http\Controllers\Portal\AuthController::class, 'destroy']);
        Route::get('/invitations', [PortalController::class, 'home']);
        Route::middleware('supplier.organization')->prefix('/{organization}/invitations/{invitation}')->group(function (): void {
            Route::get('/', [PortalController::class, 'show']);
            Route::put('/bid', [PortalController::class, 'save']);
            Route::post('/bid/submit', [PortalController::class, 'submit']);
        });
    });
});
Route::prefix('v1')->middleware(['auth:sanctum', 'buyer.identity', 'organization', 'abilities:rfq:read'])->group(function (): void {
    Route::get('/tenders/{tender}/evaluation', [EvaluationController::class, 'show']);
    Route::get('/tenders/{tender}/bids', [EvaluationController::class, 'show']);
    Route::get('/tenders/{tender}/awards', [EvaluationController::class, 'history']);
    Route::get('/tenders/{tender}/work-orders', [EvaluationController::class, 'orders']);
    Route::get('/work-orders/{workOrder}', [EvaluationController::class, 'order']);
    Route::post('/tenders/{tender}/close', [EvaluationController::class, 'close'])->middleware('abilities:rfq:write');
    Route::post('/tenders/{tender}/awards', [EvaluationController::class, 'allocate'])->middleware('abilities:rfq:write');
});
