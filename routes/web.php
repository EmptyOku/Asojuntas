<?php

use Illuminate\Support\Facades\Route;

// Controladores de Auditoría y Seguridad
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\UserController;

// Controladores de la API SPA
use App\Http\Controllers\Api\JuryIngestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\PermissionManagementController;
use App\Http\Controllers\Api\Admin\RoleManagementController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\AuditManagementController;

// EL CONTROLADOR DE BARRIOS UNIFICADO
use App\Http\Controllers\Api\Admin\NeighborhoodDirectoryController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('app');
})->name('login');

// =========================================================================
// AQUÍ EMPIEZA LO QUE REALMENTE USA TU APLICACIÓN VUE (LA API DE SESIÓN)
// =========================================================================

// Endpoints para jurados autenticados con sesión web (SPA)
Route::middleware(['auth', 'api.permission:records.upload'])->prefix('api/jury')->name('api.jury.')->group(function (): void {
    Route::get('/context', [JuryIngestController::class, 'context'])->name('context');
    Route::post('/extract-preview', [JuryIngestController::class, 'previewExtraction'])->name('extract-preview');
    Route::post('/submit', [JuryIngestController::class, 'submit'])->name('submit');
    Route::get('/scrutiny-files/{scrutinyRecordFile}', [JuryIngestController::class, 'showFile'])->name('scrutiny-files.show');
});

// API de sesión y administración usando middleware web (session-backed)
Route::prefix('api')->name('api.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/check', [AuthController::class, 'check'])->name('check');

    Route::middleware('auth')->group(function (): void {
        Route::get('/user', [AuthController::class, 'user'])->name('user');

        Route::prefix('admin')->group(function (): void {

            // =========================================================
            // RUTAS DE BARRIOS Y RESULTADOS
            // =========================================================
            Route::get('/neighborhoods', [NeighborhoodDirectoryController::class, 'index'])
                ->name('admin.neighborhoods.index');

            Route::get('/neighborhoods/{id}', [NeighborhoodDirectoryController::class, 'show'])
                ->name('admin.neighborhoods.show');

            Route::post('/neighborhoods/{id}/elections', [NeighborhoodDirectoryController::class, 'createElection'])
                ->name('admin.neighborhoods.elections.store');

            Route::post('/neighborhoods/{id}/elections/close', [NeighborhoodDirectoryController::class, 'closeElection'])
                ->name('admin.neighborhoods.elections.close');

            Route::post('/neighborhoods/elections/create-all', [NeighborhoodDirectoryController::class, 'createAllElections'])
                ->name('admin.neighborhoods.elections.create-all');

            Route::post('/neighborhoods/elections/close-all', [NeighborhoodDirectoryController::class, 'closeAllElections'])
                ->name('admin.neighborhoods.elections.close-all');
            // =========================================================

            Route::get('/users', [UserManagementController::class, 'index'])
                ->middleware('api.permission:users.view')
                ->name('admin.users.index');

            Route::post('/users', [UserManagementController::class, 'store'])
                ->middleware('api.permission:users.create')
                ->name('admin.users.store');

            Route::put('/users/{user}/roles', [UserManagementController::class, 'syncRoles'])
                ->middleware('api.permission:roles.assign')
                ->name('admin.users.roles.sync');

            Route::get('/roles', [RoleManagementController::class, 'index'])
                ->middleware('api.permission:roles.view')
                ->name('admin.roles.index');

            Route::get('/permissions', [PermissionManagementController::class, 'index'])
                ->middleware('api.permission:roles.view')
                ->name('admin.permissions.index');

            Route::get('/audit-records', [AuditManagementController::class, 'index'])
                ->middleware('api.permission:records.review')
                ->name('admin.audit-records.index');

            Route::get('/audit-records/files/{scrutinyRecordFile}', [AuditManagementController::class, 'showFile'])
                ->middleware('api.permission:records.review')
                ->name('admin.audit-records.files.show');

            Route::get('/audit-records/{scrutinyRecord}', [AuditManagementController::class, 'show'])
                ->middleware('api.permission:records.review')
                ->name('admin.audit-records.show');

            Route::post('/audit-records/{scrutinyRecord}/decision', [AuditManagementController::class, 'decide'])
                ->middleware('api.permission:records.review')
                ->name('admin.audit-records.decision');
        });
    });
});

// Ruta catch-all para Vue SPA - DEBE ir al final!
Route::get('{any}', function () {
    return view('app');
})->where('any', '.*')->name('spa');
