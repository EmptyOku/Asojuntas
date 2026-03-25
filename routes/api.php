<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\PermissionManagementController;
use App\Http\Controllers\Api\Admin\RoleManagementController;
use App\Http\Controllers\Api\Admin\UserManagementController;

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

    Route::prefix('admin')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])
            ->middleware('api.permission:users.view')
            ->name('api.admin.users.index');

        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('api.permission:users.create')
            ->name('api.admin.users.store');

        Route::put('/users/{user}/roles', [UserManagementController::class, 'syncRoles'])
            ->middleware('api.permission:roles.assign')
            ->name('api.admin.users.roles.sync');

        Route::get('/roles', [RoleManagementController::class, 'index'])
            ->middleware('api.permission:roles.view')
            ->name('api.admin.roles.index');

        Route::get('/permissions', [PermissionManagementController::class, 'index'])
            ->middleware('api.permission:roles.view')
            ->name('api.admin.permissions.index');
    });
});
