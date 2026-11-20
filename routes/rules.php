<?php

// Rutas de festivos, vacaciones y reglas laborales.
// Dueño: nomina-backend. Registrar aquí; no editar routes/web.php.

use App\Http\Controllers\HolidayController;
use App\Http\Controllers\MyVacationController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\VacationRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('holidays')->name('holidays.')->group(function (): void {
        Route::get('/', [HolidayController::class, 'index'])
            ->middleware('permission:holidays.view')
            ->name('index');
        Route::post('generate/{year}', [HolidayController::class, 'generate'])
            ->whereNumber('year')
            ->middleware('permission:holidays.manage')
            ->name('generate');
        Route::post('/', [HolidayController::class, 'store'])
            ->middleware('permission:holidays.manage')
            ->name('store');
        Route::delete('{holiday}', [HolidayController::class, 'destroy'])
            ->middleware('permission:holidays.manage')
            ->name('destroy');
    });

    Route::prefix('vacations')->name('vacations.')->group(function (): void {
        Route::get('/', [VacationController::class, 'index'])
            ->middleware('permission:vacations.view')
            ->name('index');
        Route::post('periods/generate', [VacationController::class, 'generatePeriods'])
            ->middleware('permission:vacations.manage')
            ->name('periods.generate');
        Route::post('requests', [VacationRequestController::class, 'store'])
            ->middleware('permission:vacations.manage')
            ->name('requests.store');
        Route::post('requests/{vacationRequest}/decide', [VacationRequestController::class, 'decide'])
            ->middleware('permission:vacations.approve')
            ->name('requests.decide');
    });

    Route::get('my/vacations', [MyVacationController::class, 'index'])->name('my.vacations');
});
