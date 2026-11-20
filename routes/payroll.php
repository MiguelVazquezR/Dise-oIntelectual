<?php

// Rutas de pre-nómina, conceptos, ajustes y recibos imprimibles.
// Dueño: nomina-backend. Registrar aquí; no editar routes/web.php.
//
// Nombres del contrato §6: `payroll.periods.index`, `payroll.receipt`, etc.
// La autorización real vive en el servidor: middleware `permission:{slug}`,
// comprobaciones explícitas en los servicios y aislamiento por `BranchAccess`.

use App\Http\Controllers\Payroll\AdjustmentController;
use App\Http\Controllers\Payroll\PayrollCalculationController;
use App\Http\Controllers\Payroll\PayrollConceptController;
use App\Http\Controllers\Payroll\PayrollPeriodController;
use App\Http\Controllers\Payroll\PayrollReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('payroll')->name('payroll.')->group(function (): void {
        Route::prefix('periods')->name('periods.')->group(function (): void {
            Route::get('/', [PayrollPeriodController::class, 'index'])
                ->middleware('permission:payroll.view')
                ->name('index');
            Route::post('/', [PayrollPeriodController::class, 'store'])
                ->middleware('permission:payroll.calculate')
                ->name('store');
            Route::get('{period}', [PayrollPeriodController::class, 'show'])
                ->middleware('permission:payroll.view')
                ->name('show');
            Route::post('{period}/calculate', [PayrollCalculationController::class, 'store'])
                ->middleware('permission:payroll.calculate')
                ->name('calculate');
            Route::post('{period}/review', [PayrollPeriodController::class, 'review'])
                ->middleware('permission:payroll.review')
                ->name('review');
            Route::post('{period}/close', [PayrollPeriodController::class, 'close'])
                ->middleware('permission:payroll.close')
                ->name('close');
            // Ajustes por concepto dentro del periodo (reutiliza el payload de ajustes).
            Route::post('{period}/adjustments', [AdjustmentController::class, 'store'])
                ->middleware('permission:adjustments.manage')
                ->name('adjustments.store');
        });

        Route::get('items/{item}/receipt', [PayrollReceiptController::class, 'show'])
            ->middleware('permission:payroll.view')
            ->name('receipt');
    });

    Route::prefix('adjustments')->name('adjustments.')->group(function (): void {
        Route::get('/', [AdjustmentController::class, 'index'])
            ->middleware('permission:adjustments.view')
            ->name('index');
        Route::post('/', [AdjustmentController::class, 'store'])
            ->middleware('permission:adjustments.manage')
            ->name('store');
        Route::post('{adjustment}/decide', [AdjustmentController::class, 'decide'])
            ->middleware('permission:adjustments.approve')
            ->name('decide');
    });

    Route::prefix('concepts')->name('concepts.')->group(function (): void {
        Route::get('/', [PayrollConceptController::class, 'index'])
            ->middleware('permission:concepts.view')
            ->name('index');
        Route::post('/', [PayrollConceptController::class, 'store'])
            ->middleware('permission:concepts.manage')
            ->name('store');
        Route::put('{concept}', [PayrollConceptController::class, 'update'])
            ->middleware('permission:concepts.manage')
            ->name('update');
        Route::delete('{concept}', [PayrollConceptController::class, 'destroy'])
            ->middleware('permission:concepts.manage')
            ->name('destroy');
    });
});
