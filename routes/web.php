<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Techieni3\LaravelUserPermissions\Http\Controllers\Api\RoleController;
use Techieni3\LaravelUserPermissions\Http\Controllers\Api\RolePermissionController;
use Techieni3\LaravelUserPermissions\Http\Controllers\Api\UserAccessController;
use Techieni3\LaravelUserPermissions\Http\Controllers\Api\UserController;
use Techieni3\LaravelUserPermissions\Http\Controllers\Web\DashboardController;

Route::prefix(config('permissions.dashboard.prefix', 'permissions-manager'))
    ->middleware(
        array_merge(config('permissions.dashboard.middleware', ['web', 'auth']), ['permissions.dashboard'])
    )->group(function (): void {
        // API Routes
        Route::prefix('api')->group(function (): void {
            // Roles
            Route::get('/roles', [RoleController::class, 'index'])
                ->name('permissions.api.roles.index');

            // Role Permissions
            Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'show'])
                ->name('permissions.api.roles.permissions.show');

            Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])
                ->name('permissions.api.roles.permissions.update');

            // Users
            Route::get('/users', [UserController::class, 'index'])
                ->name('permissions.api.users.index');

            // User Access
            Route::get('/users/{user}/access', [UserAccessController::class, 'show'])
                ->name('permissions.api.user-access.show');

            Route::put('/users/{user}/access', [UserAccessController::class, 'update'])
                ->name('permissions.api.user-access.update');

        });

        // Dashboard (catch-all for SPA)
        Route::get('/{view?}', [DashboardController::class, 'index'])
            ->where('view', '(.*)')
            ->name('permissions.dashboard');
    });
