<?php

declare(strict_types=1);

use Workbench\App\Enums\ModelActions;
use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

use function Orchestra\Testbench\workbench_path;

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
        'gate' => null, // Disabled for tests

        // Column used to display usernames on the dashboard (e.g., name, email)
        'user_display_column' => 'name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    |
    | These values control how the package discovers models in the application
    | to generate permissions dynamically.
    |
     */
    'models' => [
        // Directory containing your application's Eloquent models
        'directory' => workbench_path('app/Models'),

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
        'user' => User::class,

        'role_enum' => Role::class,

        'model_actions_enum' => ModelActions::class,
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
