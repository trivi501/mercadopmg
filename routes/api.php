<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MerchantController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST v1 — consumida por la aplicación Android
|--------------------------------------------------------------------------
| Autenticación: token Bearer de Sanctum obtenido en POST /api/v1/login.
| Encabezado opcional X-Device-Name para identificar el dispositivo en la auditoría.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::get('catalogs', [CatalogController::class, 'all'])->name('catalogs');
        Route::get('tianguis', [CatalogController::class, 'tianguis'])->name('tianguis');
        Route::get('routes', [CatalogController::class, 'routes'])->name('routes');
        Route::get('giros', [CatalogController::class, 'businessTypes'])->name('giros');
        Route::get('concepts', [CatalogController::class, 'concepts'])->name('concepts');

        Route::get('merchants/folio/{folio}', [MerchantController::class, 'byFolio'])->name('merchants.by-folio');
        Route::get('merchants/{merchant}/account', [MerchantController::class, 'account'])->name('merchants.account');
        Route::get('merchants/{merchant}/payments', [MerchantController::class, 'payments'])->name('merchants.payments');
        Route::get('merchants/{merchant}/receipts', [MerchantController::class, 'receipts'])->name('merchants.receipts');
        Route::apiResource('merchants', MerchantController::class)->except('destroy');

        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

        Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('receipts/{receipt}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
        Route::post('receipts/{receipt}/print', [ReceiptController::class, 'print'])->name('receipts.print');
        Route::post('receipts/{receipt}/reprint', [ReceiptController::class, 'reprint'])->name('receipts.reprint');
        Route::post('receipts/{receipt}/cancel', [ReceiptController::class, 'cancel'])->name('receipts.cancel');

        Route::post('sync', [SyncController::class, 'push'])->middleware('throttle:api-sync')->name('sync');

        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('reports', ReportController::class)->name('reports');
    });
});
