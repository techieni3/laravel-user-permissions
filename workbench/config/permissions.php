<?php

declare(strict_types=1);

use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

use function Orchestra\Testbench\workbench_path;

return [
    // Enable or disable the permissions manager UI
    'dashboard_enabled' => env('PERMISSIONS_MANAGER_DASHBOARD_ENABLED', true),

    // The path where the permissions manager UI will be available
    'path' => env('PERMISSIONS_MANAGER_PATH', 'permissions-manager'),

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    |
    | These values control how the package discovers models in the application
    | in order to generate permissions dynamically.
    |
     */

    // Directory containing all your Eloquent models
    'models_path' => workbench_path('app/Models'),

    // Excluded models that should NOT have permissions generated
    'excluded_models' => [
        // App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Core Application Classes
    |--------------------------------------------------------------------------
    |
    | These references allow the package to know which classes represent the
    | user entity and the role enumeration.
    |
     */

    'user_model' => User::class,

    'role_enum' => Role::class,
];
