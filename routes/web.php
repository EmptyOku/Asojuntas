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
use App\Http\Controllers\Api\JuryIngestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\PermissionManagementController;
use App\Http\Controllers\Api\Admin\RoleManagementController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\AuditManagementController;

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
