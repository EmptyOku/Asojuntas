<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

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

// Rutas públicas (sin autenticación)
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
Route::get('/check', [AuthController::class, 'check'])->name('api.check');

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:web')->group(function () {
    Route::get('/user', [AuthController::class, 'user'])->name('api.user');
    
    // Aquí puedes agregar más rutas de API protegidas
    // Por ejemplo:
    // Route::get('/elections', [ElectionController::class, 'index']);
    // Route::get('/users', [UserController::class, 'index']);
});
