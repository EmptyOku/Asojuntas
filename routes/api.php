<?php

use App\Http\Controllers\Api\ExtractionIngestController;
use Illuminate\Support\Facades\Route;

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

// NINGUNA RUTA DE VUE/ADMIN VA AQUÍ. ESTE ARCHIVO QUEDA ESTRICTAMENTE ASÍ.
