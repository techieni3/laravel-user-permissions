<?php

declare(strict_types=1);

return [
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
