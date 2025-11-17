<?php

use Illuminate\Support\Facades\Route;
use Techieni3\LaravelUserPermissions\Http\Controllers\DashboardController;
use Techieni3\LaravelUserPermissions\Http\Controllers\PermissionController;
use Techieni3\LaravelUserPermissions\Http\Controllers\RoleController;
use Techieni3\LaravelUserPermissions\Http\Controllers\UserController;

Route::prefix(config('permissions.path', 'permissions-manager'))
    ->middleware(config('permissions.middleware', ['web', 'auth']))
    ->group(function () {
        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('permissions.dashboard');

        // Roles API
        Route::prefix('api')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('permissions.api.roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->name('permissions.api.roles.store');
            Route::get('/roles/{role}', [RoleController::class, 'show'])->name('permissions.api.roles.show');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('permissions.api.roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('permissions.api.roles.destroy');
            Route::get('/role-permissions', [RoleController::class, 'permissions'])->name('permissions.api.role-permissions');

            // Permissions API
            Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.api.permissions.index');
            Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.api.permissions.store');
            Route::get('/permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.api.permissions.show');
            Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.api.permissions.update');
            Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.api.permissions.destroy');

            // Users API
            Route::get('/users', [UserController::class, 'index'])->name('permissions.api.users.index');
            Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('permissions.api.users.update-roles');
            Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->name('permissions.api.users.update-permissions');
        });
    });
