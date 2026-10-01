<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Catalogs\BusinessTypeController;
use App\Http\Controllers\Catalogs\ConceptController;
use App\Http\Controllers\Catalogs\RouteController;
use App\Http\Controllers\Catalogs\TianguisController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReceiptVerificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Reports\ReportFactory;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// Verificación pública del QR impreso en el ticket.
Route::get('verificar/{uuid}', ReceiptVerificationController::class)
    ->whereUuid('uuid')
    ->middleware('throttle:30,1')
    ->name('receipts.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Padrón de comerciantes
    Route::get('comerciantes/mapa', [MerchantController::class, 'map'])->name('merchants.map');
    Route::get('comerciantes/exportar', [MerchantController::class, 'export'])->name('merchants.export');
    Route::get('comerciantes/{merchant}/qr', [MerchantController::class, 'qr'])->name('merchants.qr');
    Route::resource('comerciantes', MerchantController::class)
        ->parameters(['comerciantes' => 'merchant'])
        ->names('merchants');

    // Catálogos
    Route::prefix('catalogos')->name('catalogs.')->group(function () {
        Route::resource('giros', BusinessTypeController::class)->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['giros' => 'businessType'])->names('business-types');
        Route::resource('tianguis', TianguisController::class)->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['tianguis' => 'tianguis'])->names('tianguis');
        Route::resource('rutas', RouteController::class)->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['rutas' => 'route'])->names('routes');
        Route::resource('conceptos', ConceptController::class)->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['conceptos' => 'concept'])->names('concepts');
    });

    // Cobranza y recibos
    Route::get('cobros/nuevo', [ChargeController::class, 'create'])->name('charges.create');
    Route::post('cobros', [ChargeController::class, 'store'])->name('charges.store');

    Route::get('recibos', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::get('recibos/reimpresiones', [ReceiptController::class, 'reprints'])->name('receipts.reprints');
    Route::get('recibos/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('recibos/{receipt}/ticket', [ReceiptController::class, 'thermal'])->name('receipts.thermal');
    Route::get('recibos/{receipt}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
    Route::post('recibos/{receipt}/imprimir', [ReceiptController::class, 'print'])->name('receipts.print');
    Route::post('recibos/{receipt}/reimprimir', [ReceiptController::class, 'reprint'])->name('receipts.reprint');
    Route::post('recibos/{receipt}/cancelar', [ReceiptController::class, 'cancel'])->name('receipts.cancel');

    // Reportes
    $reportTypes = implode('|', array_keys(ReportFactory::REPORTS));
    Route::get('reportes/{type?}', [ReportController::class, 'index'])->where('type', $reportTypes)->name('reports.index');
    Route::get('reportes/{type}/exportar', [ReportController::class, 'export'])->where('type', $reportTypes)->name('reports.export');

    // Administración
    Route::get('auditoria', AuditLogController::class)->name('audit.index');
    Route::resource('usuarios', UserController::class)->only(['index', 'store', 'update'])
        ->parameters(['usuarios' => 'user'])->names('users');
    Route::delete('usuarios/{user}/tokens', [UserController::class, 'revokeTokens'])->name('users.revoke-tokens');
    Route::get('configuracion', [SettingController::class, 'edit'])->name('settings.general');
    Route::put('configuracion', [SettingController::class, 'update'])->name('settings.general.update');
});

require __DIR__.'/settings.php';
