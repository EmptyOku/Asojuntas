<?php

use Illuminate\Support\Facades\Route;

// Controladores de Auditoría y Seguridad
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\UserController;

// Controladores Geográficos
use App\Http\Controllers\Admin\StateController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CommuneController;
use App\Http\Controllers\Admin\NeighborhoodController;

// Controladores de Identidad
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\Admin\PersonController;

// Controladores de Configuración Electoral
use App\Http\Controllers\Admin\ElectionController;
use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\ElectionBlockController;
use App\Http\Controllers\Admin\ElectionBlockPositionController;
use App\Http\Controllers\Admin\PollingTableController;

// Controladores de Planchas y Candidatos
use App\Http\Controllers\Admin\SlateController;
use App\Http\Controllers\Admin\SlateBlockController;
use App\Http\Controllers\Admin\CandidateController;

// Controladores de IA y Borradores
use App\Http\Controllers\Admin\CandidateDraftController;
use App\Http\Controllers\Admin\ScrutinyExtractionController;

// Controladores de Escrutinio y Votación
use App\Http\Controllers\Admin\ScrutinyRecordController;
use App\Http\Controllers\Admin\ScrutinyRecordFileController;
use App\Http\Controllers\Admin\ScrutinyReviewController;
use App\Http\Controllers\Admin\ScrutinyBlockResultController;
use App\Http\Controllers\Admin\ScrutinyElectedPersonController;

// Controladores de Consolidación Matemática
use App\Http\Controllers\Admin\ConsolidationRunController;
use App\Http\Controllers\Admin\ConsolidatedBlockResultController;
use App\Http\Controllers\Admin\SeatAllocationController;

Route::get('/', function () {
    return view('welcome');
});

/* Panel de Administración (Middleware: Auth + Admin)*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    /* --- 1. MÓDULO DE SEGURIDAD Y ACCESO --- */
    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class)->except(['show']);
    Route::resource('user-roles', UserRoleController::class)->only(['index', 'destroy']);
    Route::resource('role-permissions', RolePermissionController::class)->only(['index', 'destroy']);

    /* --- 2. MÓDULO GEOGRÁFICO --- */
    Route::resource('states', StateController::class)->except(['show']);
    Route::resource('cities', CityController::class)->except(['show']);
    Route::resource('communes', CommuneController::class)->except(['show']);
    Route::resource('neighborhoods', NeighborhoodController::class)->except(['show']);

    /* --- 3. MÓDULO DE IDENTIDAD --- */
    Route::resource('document-types', DocumentTypeController::class)->except(['show']);
    Route::resource('people', PersonController::class)->except(['show']);

    /* --- 4. CONFIGURACIÓN ELECTORAL MAESTRA --- */
    Route::resource('elections', ElectionController::class)->except(['show']);
    Route::resource('blocks', BlockController::class)->except(['show']);
    Route::resource('positions', PositionController::class)->except(['show']);
    Route::resource('election-blocks', ElectionBlockController::class);
    Route::resource('election-block-positions', ElectionBlockPositionController::class)->except(['show']);
    Route::resource('polling-tables', PollingTableController::class)->except(['show']);

    /* --- 5. ESTRUCTURA DE PLANCHAS Y CANDIDATOS --- */
    Route::resource('slates', SlateController::class)->except(['show']);
    Route::resource('slate-blocks', SlateBlockController::class)->except(['show']);
    Route::resource('candidates', CandidateController::class)->except(['show']);

    /* --- 6. INTEGRACIÓN CON IA (PYTHON) --- */
    Route::resource('candidate-drafts', CandidateDraftController::class)->only(['index', 'edit', 'update']);
    Route::resource('extractions', ScrutinyExtractionController::class)->only(['index', 'show', 'store', 'destroy']);

    /* --- 7. ESCRUTINIO Y ACTAS (OPERACIÓN) --- */
    Route::resource('scrutiny-records', ScrutinyRecordController::class)->except(['edit']);
    Route::resource('scrutiny-record-files', ScrutinyRecordFileController::class)->only(['store', 'show', 'destroy']);
    Route::resource('scrutiny-reviews', ScrutinyReviewController::class)->only(['index', 'show', 'store']);
    Route::resource('scrutiny-block-results', ScrutinyBlockResultController::class)->only(['index', 'show', 'update']);
    Route::resource('scrutiny-elected', ScrutinyElectedPersonController::class)->only(['index', 'show', 'update']);

    /* --- 8. MOTOR DE CONSOLIDACIÓN Y CURULES --- */
    Route::resource('consolidation-runs', ConsolidationRunController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::resource('consolidated-results', ConsolidatedBlockResultController::class)->only(['index', 'show']);
    Route::resource('seat-allocations', SeatAllocationController::class)->only(['index', 'show']);

    /* --- 9. AUDITORÍA GENERAL DEL SISTEMA --- */
    Route::get('/logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');

});

// Ruta catch-all para Vue SPA - DEBE ir al final!
Route::get('{any}', function () {
    return view('app');
})->where('any', '.*')->name('spa');
