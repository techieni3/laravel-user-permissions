<?php

declare(strict_types=1);

use Workbench\App\Enums\ModelActions;
use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

use function Orchestra\Testbench\workbench_path;

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | These values control the permissions manager dashboard UI, including
    | whether it's enabled and the URL path where it will be accessible.
    |
     */

    // Enable or disable the permissions manager UI
    'dashboard_enabled' => env('PERMISSIONS_MANAGER_DASHBOARD_ENABLED', true),

    // The path where the permissions manager UI will be available
    'path' => env('PERMISSIONS_MANAGER_DASHBOARD_PATH', 'permissions-manager'),

    // The column to use for displaying username on the permission manager dashboard
    // (e.g., 'name', 'email', 'username')
    'user_name_column' => 'name',

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    |
    | These values control how the package discovers models in the application
    | to generate permissions dynamically.
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

    'model_actions_enum' => ModelActions::class,
];
