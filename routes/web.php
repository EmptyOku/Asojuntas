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
use App\Http\Controllers\Api\Secretary\PlanchaDraftController;
use App\Http\Controllers\Api\Admin\PermissionManagementController;
use App\Http\Controllers\Api\Admin\RoleManagementController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\AuditManagementController;
use App\Http\Controllers\Api\Admin\AuditLogController as SystemAuditLogController;
use App\Http\Controllers\Api\Admin\NeighborhoodController;
use App\Http\Controllers\Api\Admin\PersonController as ApiPersonController;

// EL CONTROLADOR DE BARRIOS UNIFICADO
use App\Http\Controllers\Api\Admin\rectoryController;

// Controlador para la gestión de candidatos OCR
use App\Http\Controllers\Admin\OcrCandidateController;

//Controlador de personas físicas
use App\Http\Controllers\Admin\PersonController;
use App\Http\Controllers\Api\Admin\NeighborhoodDirectoryController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('app');
})->name('login');


// Endpoints para jurados autenticados con sesión web (SPA)
Route::middleware(['auth', 'api.permission:records.upload'])->prefix('api/jury')->name('api.jury.')->group(function (): void {
    Route::get('/context', [JuryIngestController::class, 'context'])->name('context');
    Route::get('/status/{scrutinyRecord}', [JuryIngestController::class, 'status'])->name('status');
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

        Route::prefix('secretary')->middleware('api.permission:records.upload')->group(function (): void {
            Route::post('/planchas/extract-preview', [PlanchaDraftController::class, 'previewExtraction'])
                ->name('secretary.planchas.extract-preview');

            Route::post('/planchas/drafts', [PlanchaDraftController::class, 'storeDrafts'])
                ->name('secretary.planchas.drafts.store');

            Route::get('/planchas/drafts', [PlanchaDraftController::class, 'index'])
                ->name('secretary.planchas.drafts.index');

            Route::put('/planchas/drafts/{candidateDraft}', [PlanchaDraftController::class, 'update'])
                ->name('secretary.planchas.drafts.update');

            Route::post('/planchas/drafts/{candidateDraft}/decision', [PlanchaDraftController::class, 'decide'])
                ->name('secretary.planchas.drafts.decision');

            Route::post('/planchas/drafts/decision/batch', [PlanchaDraftController::class, 'decideBatch'])
                ->name('secretary.planchas.drafts.decision.batch');

            Route::post('/planchas/drafts/promote', [PlanchaDraftController::class, 'promoteApproved'])
                ->name('secretary.planchas.drafts.promote');

            Route::post('/planchas/evidence', [PlanchaDraftController::class, 'uploadDraftFiles'])
                ->name('secretary.planchas.evidence.store');

            Route::get('/planchas/evidence/{captureBatchUuid}', [PlanchaDraftController::class, 'listEvidenceByBatch'])
                ->name('secretary.planchas.evidence.index');

            Route::get('/planchas/evidence/files/{candidateDraftFile}', [PlanchaDraftController::class, 'showEvidenceFile'])
                ->name('secretary.planchas.evidence.show');

            Route::get('/neighborhoods/search', [App\Http\Controllers\Admin\NeighborhoodController::class, 'search']);
            Route::get('/planchas/by-neighborhood', [PlanchaDraftController::class, 'neighborhoodsWithSlates']);
        });

        Route::prefix('admin')->group(function (): void {

            // =========================================================
            // RUTAS DE BARRIOS Y RESULTADOS
            // =========================================================
            Route::get('/neighborhoods', [NeighborhoodDirectoryController::class, 'index'])
                ->name('admin.neighborhoods.index');

            Route::get('/neighborhoods/list-for-forms', [NeighborhoodDirectoryController::class, 'listForForms'])
                ->name('admin.neighborhoods.list-for-forms');

            Route::get('/neighborhoods/communes', [NeighborhoodDirectoryController::class, 'communes'])
                ->name('admin.neighborhoods.communes');

            Route::get('/neighborhoods/search-dropdown', [\App\Http\Controllers\Api\Admin\NeighborhoodDirectoryController::class, 'searchForDropdown'])
                ->name('admin.neighborhoods.search-dropdown');

            Route::get('/neighborhoods/{id}', [NeighborhoodDirectoryController::class, 'show'])
                ->whereNumber('id')
                ->name('admin.neighborhoods.show');

            Route::post('/neighborhoods/{id}/elections', [NeighborhoodDirectoryController::class, 'createElection'])
                ->whereNumber('id')
                ->name('admin.neighborhoods.elections.store');

            Route::post('/neighborhoods/{id}/elections/close', [NeighborhoodDirectoryController::class, 'closeElection'])
                ->whereNumber('id')
                ->name('admin.neighborhoods.elections.close');

            Route::post('/neighborhoods/elections/create-all', [NeighborhoodDirectoryController::class, 'createAllElections'])
                ->name('admin.neighborhoods.elections.create-all');

            Route::post('/neighborhoods/elections/close-all', [NeighborhoodDirectoryController::class, 'closeAllElections'])
                ->name('admin.neighborhoods.elections.close-all');
            // =========================================================
            // RUTAS DE PERSONAS
            // =========================================================
            Route::get('/persons/context', [ApiPersonController::class, 'context'])
                ->name('admin.persons.context');

            Route::get('/persons', [ApiPersonController::class, 'index'])
                ->name('admin.persons.index');

            Route::post('/persons', [ApiPersonController::class, 'store'])
                ->middleware('api.permission:users.create')
                ->name('admin.persons.store');

            Route::get('/persons/without-user', [ApiPersonController::class, 'getPersonsWithoutUser'])
                ->name('admin.persons.without-user');

            Route::get('/persons/{person}', [ApiPersonController::class, 'show'])
                ->name('admin.persons.show');

            Route::put('/persons/{person}', [ApiPersonController::class, 'update'])
                ->middleware('api.permission:users.update')
                ->name('admin.persons.update');
            // =========================================================

            // Legacy route (web controller)
            Route::post('/people', [\App\Http\Controllers\Admin\PersonController::class, 'store'])
                ->name('admin.people.store');

            Route::get('/people/without-users', [\App\Http\Controllers\Api\Admin\UserManagementController::class, 'getAvailablePersons'])
                ->name('admin.people.without-users');

            // =========================================================
            // RUTAS DE USUARIOS
            // =========================================================
            Route::get('/users/context', [UserManagementController::class, 'creationContext'])
                ->middleware('api.permission:users.view')
                ->name('admin.users.context');

            // 🔥 BUSCADOR DE PERSONAS PARA DROPDOWN
            Route::get('/users/search-persons', [UserManagementController::class, 'searchPersonsForDropdown'])
                ->name('admin.users.search-persons');

            Route::get('/users', [UserManagementController::class, 'index'])
                ->middleware('api.permission:users.view')
                ->name('admin.users.index');

            Route::get('/users/assignment-context', [UserManagementController::class, 'assignmentContext'])
                ->middleware('api.permission:users.view')
                ->name('admin.users.assignment-context');

            Route::post('/users', [UserManagementController::class, 'store'])
                ->middleware('api.permission:users.create')
                ->name('admin.users.store');

            Route::put('/users/{user}/roles', [UserManagementController::class, 'syncRoles'])
                ->middleware('api.permission:roles.assign')
                ->name('admin.users.roles.sync');

            Route::put('/users/{user}/neighborhood', [UserManagementController::class, 'syncNeighborhood'])
                ->middleware('api.permission:users.update')
                ->name('admin.users.neighborhood.sync');
            // =========================================================

            Route::get('/roles', [RoleManagementController::class, 'index'])
                ->middleware('api.permission:roles.view')
                ->name('admin.roles.index');

            Route::get('/permissions', [PermissionManagementController::class, 'index'])
                ->middleware('api.permission:roles.view')
                ->name('admin.permissions.index');

            Route::get('/audit-records', [AuditManagementController::class, 'index'])
                ->middleware('api.permission:records.review')
                ->name('admin.audit-records.index');

            Route::get('/audit-logs', [SystemAuditLogController::class, 'index'])
                ->middleware('api.permission:audit.view')
                ->name('admin.audit-logs.index');

            Route::get('/audit-records/files/{scrutinyRecordFile}', [AuditManagementController::class, 'showFile'])
                ->middleware('api.permission:records.review')
                ->name('admin.audit-records.files.show');

            Route::get('/audit-records/{scrutinyRecord}', [AuditManagementController::class, 'show'])
                ->middleware('api.permission:records.review')
                ->name('admin.audit-records.show');

            Route::post('/audit-records/{scrutinyRecord}/decision', [AuditManagementController::class, 'decide'])
                ->middleware('api.permission:records.review')
                ->name('admin.audit-records.decision');

            // Rutas para gestión de candidatos OCR
            Route::post('/ocr/process', [OcrCandidateController::class, 'process'])
                ->middleware('api.permission:ocr.process')
                ->name('admin.ocr.process');
        });
    });
});

// Ruta catch-all para Vue SPA - DEBE ir al final!
// Excluye rutas /api para que lleguen al controlador correcto
Route::get('{any}', function () {
    return view('app');
})->where('any', '^(?!api).*')->name('spa');