<?php

// Rutas de administración de usuarios, roles y permisos.
// Dueño: nomina-backend. Registrar aquí; no editar routes/web.php.

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
        ->middleware('permission:users.manage')
        ->name('users.create');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:users.manage')
        ->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:users.manage')
        ->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.manage')
        ->name('users.update');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.manage')
        ->name('roles.index');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.manage')
        ->name('roles.update');
});
