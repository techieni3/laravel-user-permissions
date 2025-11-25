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

    // Enable or disable the permissions manager dashboard
    'dashboard_enabled' => env('PERMISSIONS_MANAGER_DASHBOARD_ENABLED', true),

    // URL path for accessing the dashboard
    'path' => env('PERMISSIONS_MANAGER_DASHBOARD_PATH', 'permissions-manager'),

    // Column used to display usernames on the dashboard (e.g., name, email)
    'user_name_column' => 'name',

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    |
    | Controls how models are discovered when generating permissions.
    |
    */

    // Directory containing your application’s Eloquent models
    'models_path' => app_path('Models'),

    // Models to exclude from permission generation
    'excluded_models' => [
        // App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Core Application Classes
    |--------------------------------------------------------------------------
    |
    | Classes used by the permissions system (user model, role enum, etc.).
    |
    */

    /** @phpstan-ignore class.notFound */
    'user_model' => App\Models\User::class,

    /** @phpstan-ignore class.notFound */
    'role_enum' => App\Enums\Role::class,

    /** @phpstan-ignore class.notFound */
    'model_actions_enum' => App\Enums\ModelActions::class,

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
