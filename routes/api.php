<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExtractionIngestController;
use App\Http\Controllers\Api\Admin\NeighborhoodDirectoryController;
use App\Http\Controllers\Api\Admin\CompleteUserManagementController;
use App\Http\Controllers\Api\Admin\RoleManagementController;
use App\Http\Controllers\Api\Admin\PermissionManagementController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| requires the "api" middleware group to be applied to every request.
|
*/

// Ingestión desde servicio externo OCR/Visión.
Route::middleware('ingest.token')->prefix('ingest')->group(function (): void {
    Route::post('/scrutiny-files', [ExtractionIngestController::class, 'uploadFile'])
        ->name('api.ingest.scrutiny-files.store');

    Route::post('/scrutiny-extractions', [ExtractionIngestController::class, 'ingestExtraction'])
        ->name('api.ingest.scrutiny-extractions.store');
});
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/neighborhoods', [NeighborhoodDirectoryController::class, 'index'])
        ->name('api.admin.neighborhoods.index');

    Route::get('/neighborhoods/{id}', [NeighborhoodDirectoryController::class, 'show'])
        ->name('api.admin.neighborhoods.show');
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function (): void {
    Route::post('/users-complete', [CompleteUserManagementController::class, 'store'])
        ->middleware('permission:users.create');
    Route::get('/roles', [RoleManagementController::class, 'index'])
        ->middleware('permission:roles.view');
    Route::get('/permissions', [PermissionManagementController::class, 'index'])
        ->middleware('permission:roles.view');
    Route::post('/roles', [RoleManagementController::class, 'store'])
        ->middleware('permission:roles.manage');
    Route::put('/roles/{id}', [RoleManagementController::class, 'update'])
        ->middleware('permission:roles.manage');
    Route::put('/users/{user}/roles', [CompleteUserManagementController::class, 'updateRoles'])
        ->middleware('permission:roles.assign');
    Route::patch('/users/{user}/toggle-status', [CompleteUserManagementController::class, 'toggleStatus'])
        ->middleware('permission:users.update');
});

// NINGUNA RUTA DE VUE/ADMIN VA AQUÍ. ESTE ARCHIVO QUEDA ESTRICTAMENTE ASÍ.
