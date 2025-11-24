<?php

declare(strict_types=1);

return [

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
    'models_path' => app_path('Models'),

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

    /** @phpstan-ignore class.notFound */
    'user_model' => App\Models\User::class,

    /** @phpstan-ignore class.notFound */
    'role_enum' => App\Enums\Role::class,

    /** @phpstan-ignore class.notFound */
    'model_actions_enum' => App\Enums\ModelActions::class,
];
