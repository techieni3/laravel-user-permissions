<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | Controls the permissions manager dashboard: whether it’s enabled
    | and the URL where it can be accessed.
    |
    */
    'dashboard' => [
        // Enable or disable the permissions manager dashboard
        'enabled' => env('PERMISSIONS_MANAGER_DASHBOARD_ENABLED', true),

        // URL path for accessing the dashboard
        'prefix' => env('PERMISSIONS_MANAGER_DASHBOARD_PATH', 'permissions-manager'),

        // Middleware to protect the dashboard routes
        'middleware' => ['web', 'auth'],

        // Gate ability to authorize dashboard access (null = no additional authorization)
        // Define this gate in your AppServiceProvider to control who can access the dashboard
        // Example: Gate::define('viewPermissionsDashboard', fn($user) => $user->hasRole('admin'));
        'gate' => env('PERMISSIONS_MANAGER_GATE', 'viewPermissionsDashboard'),

        // Column used to display usernames on the dashboard (e.g., name, email)
        'user_display_column' => 'name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    |
    | Controls how models are discovered when generating permissions.
    |
    */
    'models' => [
        // Directories and/or specific model classes to include for permission generation
        // Can contain directory paths (e.g., app_path('Models')) or specific model class strings
        'included' => [
            app_path('Models'),
            // app_path('Modules/Blog/Models'),
            // App\External\CustomModel::class,
        ],

        // Models to exclude from permission generation
        'excluded' => [
            // App\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Core Application Classes
    |--------------------------------------------------------------------------
    |
    | Classes used by the permissions system (user model, role enum, etc.).
    |
    */
    'classes' => [
        /** @phpstan-ignore class.notFound */
        'user' => App\Models\User::class,

        /** @phpstan-ignore class.notFound */
        'role_enum' => App\Enums\Role::class,

        /** @phpstan-ignore class.notFound */
        'model_actions_enum' => App\Enums\ModelActions::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Enable or disable events fired when roles or permissions change.
    |
    */

    'events_enabled' => env('PERMISSIONS_EVENTS_ENABLED', true),
];
