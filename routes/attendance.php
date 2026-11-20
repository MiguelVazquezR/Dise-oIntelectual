<?php

// Rutas de sucursales, horarios, colaboradores, asistencias, incidencias,
// festivos, vacaciones, checador y dashboard.
// Dueño: nomina-backend. Registrar aquí; no editar routes/web.php.

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\Kiosk\FaceClockController;
use App\Http\Controllers\Kiosk\KioskController;
use App\Http\Controllers\Kiosk\PinClockController;
use App\Http\Controllers\MyAttendanceController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('branches')->name('branches.')->group(function (): void {
        Route::get('/', [BranchController::class, 'index'])
            ->middleware('permission:branches.view')
            ->name('index');
        Route::get('create', [BranchController::class, 'create'])
            ->middleware('permission:branches.manage')
            ->name('create');
        Route::post('/', [BranchController::class, 'store'])
            ->middleware('permission:branches.manage')
            ->name('store');
        Route::get('{branch}/edit', [BranchController::class, 'edit'])
            ->middleware('permission:branches.manage')
            ->name('edit');
        Route::put('{branch}', [BranchController::class, 'update'])
            ->middleware('permission:branches.manage')
            ->name('update');
        Route::delete('{branch}', [BranchController::class, 'destroy'])
            ->middleware('permission:branches.manage')
            ->name('destroy');
    });

    Route::prefix('schedules')->name('schedules.')->group(function (): void {
        Route::get('/', [ScheduleController::class, 'index'])
            ->middleware('permission:schedules.view')
            ->name('index');
        Route::get('create', [ScheduleController::class, 'create'])
            ->middleware('permission:schedules.manage')
            ->name('create');
        Route::post('/', [ScheduleController::class, 'store'])
            ->middleware('permission:schedules.manage')
            ->name('store');
        Route::get('{schedule}/edit', [ScheduleController::class, 'edit'])
            ->middleware('permission:schedules.manage')
            ->name('edit');
        Route::put('{schedule}', [ScheduleController::class, 'update'])
            ->middleware('permission:schedules.manage')
            ->name('update');
        Route::delete('{schedule}', [ScheduleController::class, 'destroy'])
            ->middleware('permission:schedules.manage')
            ->name('destroy');
    });

    Route::prefix('employees')->name('employees.')->group(function (): void {
        Route::get('/', [EmployeeController::class, 'index'])
            ->middleware('permission:employees.view')
            ->name('index');
        Route::get('export', [EmployeeController::class, 'export'])
            ->middleware('permission:employees.view')
            ->name('export');
        Route::get('create', [EmployeeController::class, 'create'])
            ->middleware('permission:employees.manage')
            ->name('create');
        Route::post('/', [EmployeeController::class, 'store'])
            ->middleware('permission:employees.manage')
            ->name('store');
        Route::get('{employee}', [EmployeeController::class, 'show'])
            ->middleware('permission:employees.view')
            ->name('show');
        Route::get('{employee}/edit', [EmployeeController::class, 'edit'])
            ->middleware('permission:employees.manage')
            ->name('edit');
        Route::put('{employee}', [EmployeeController::class, 'update'])
            ->middleware('permission:employees.manage')
            ->name('update');
        Route::post('{employee}/pin', [EmployeeController::class, 'updatePin'])
            ->middleware('permission:employees.manage')
            ->name('pin');
    });

    // Asistencias (§7.3): listado de días por colaborador y ficha individual.
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->middleware('permission:attendance.view')
        ->name('attendance.index');
    Route::get('attendance/employee/{employee}', [AttendanceController::class, 'show'])
        ->middleware('permission:attendance.view');

    // «Mi asistencia»: colaborador vinculado a la cuenta (sin permiso adicional).
    Route::get('my/attendance', [MyAttendanceController::class, 'index'])->name('my.attendance');

    // Incidencias.
    Route::prefix('incidents')->name('incidents.')->group(function (): void {
        Route::get('/', [IncidentController::class, 'index'])
            ->middleware('permission:incidents.view')
            ->name('index');
        Route::post('/', [IncidentController::class, 'store'])
            ->middleware('permission:incidents.manage')
            ->name('store');
        Route::put('{incident}', [IncidentController::class, 'update'])
            ->middleware('permission:incidents.manage')
            ->name('update');
        Route::post('{incident}/decide', [IncidentController::class, 'decide'])
            ->middleware('permission:incidents.approve')
            ->name('decide');
    });

    // Checador: PIN con límites por IP y por número de empleado; facial sin AWS.
    Route::prefix('kiosk')->name('kiosk.')->group(function (): void {
        Route::get('/', [KioskController::class, 'index'])
            ->middleware('permission:attendance.record')
            ->name('index');
        Route::post('pin', [PinClockController::class, 'store'])
            ->middleware([
                'permission:attendance.record',
                'throttle:kiosk-pin',
                'throttle:kiosk-pin-employee',
            ])
            ->name('pin');
        Route::post('face', [FaceClockController::class, 'store'])
            ->middleware(['permission:attendance.record', 'throttle:20,1'])
            ->name('face');
    });
});
