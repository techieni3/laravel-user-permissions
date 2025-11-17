<?php

declare(strict_types=1);

return [
    // Enable or disable the permissions manager UI
    'enabled' => env('PERMISSIONS_MANAGER_ENABLED', true),

    // The path where the permissions manager UI will be available
    'path' => env('PERMISSIONS_MANAGER_PATH', 'permissions-manager'),

    // Middleware to apply to the permissions manager routes
    'middleware' => ['web', 'auth'],

    // Path to the User model file
    'user_model_file' => app_path('Models/User.php'),

    // Path to the application's models directory
    'models_directory' => app_path('Models'),

    // Path to the Role enum file
    'role_enum_file' => app_path('Enums/Role.php'),

    // List of model classes to exclude from automatic permissions generation
    'excluded_models' => [
        App\Models\User::class,
    ],
];
