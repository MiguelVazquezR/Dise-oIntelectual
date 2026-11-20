<?php

// Rutas de AWS Rekognition, consentimiento biométrico, consumo, diagnóstico y
// auditoría. Dueño: nomina-backend. Registrar aquí; no editar routes/web.php.
//
// El dashboard (`GET /dashboard`) se registra en routes/web.php para conservar
// el nombre de ruta histórico `dashboard`.

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AwsUsageController;
use App\Http\Controllers\EmployeeFaceController;
use App\Http\Controllers\Settings\AwsSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Consumo interno de AWS (cargo interno, no tarifa oficial).
    Route::get('aws/usage', [AwsUsageController::class, 'index'])
        ->middleware('permission:aws.usage.view')
        ->name('aws.usage');

    // Configuración y diagnóstico: nunca expone claves ni escribe .env.
    Route::get('settings/aws', [AwsSettingsController::class, 'index'])
        ->middleware('permission:aws.settings.view')
        ->name('aws.settings');
    Route::post('settings/aws/test', [AwsSettingsController::class, 'test'])
        ->middleware('permission:aws.settings.manage')
        ->name('aws.settings.test');

    // Consentimiento y enrolamiento biométrico por colaborador.
    Route::get('employees/{employee}/face/enrollment', [EmployeeFaceController::class, 'show'])
        ->middleware('permission:employees.view')
        ->name('employees.face.enrollment.show');
    Route::post('employees/{employee}/face/enrollment', [EmployeeFaceController::class, 'store'])
        ->middleware('permission:employees.manage,aws.settings.manage')
        ->name('employees.face.enrollment.store');
    Route::delete('employees/{employee}/face/enrollment', [EmployeeFaceController::class, 'destroy'])
        ->middleware('permission:employees.manage,aws.settings.manage')
        ->name('employees.face.enrollment.destroy');

    // Auditoría: solo metadatos, sin payloads sensibles.
    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');
});
