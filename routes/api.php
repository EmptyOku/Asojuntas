<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExtractionIngestController;
use App\Http\Controllers\Api\Admin\NeighborhoodDirectoryController;

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

// NINGUNA RUTA DE VUE/ADMIN VA AQUÍ. ESTE ARCHIVO QUEDA ESTRICTAMENTE ASÍ.
