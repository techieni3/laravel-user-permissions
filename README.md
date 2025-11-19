# Laravel User Permissions

A modern, performance-optimized Laravel package for managing user roles and permissions. This package provides a flexible and type-safe approach to authorization using PHP 8.4 enums, with built-in request-level caching and seamless integration with Laravel's authorization system.

## Features

- **Enum-based Role Management**: Type-safe role definitions using PHP enums
- **Auto-generated Permissions**: Automatically generate CRUD permissions for your models
- **Flexible Permission System**: Support for both direct permissions and role-based permissions
- **Performance Optimized**: Request-level caching to prevent N+1 queries
- **Laravel Gate Integration**: Works seamlessly with Laravel's `can()` method and policies
- **Custom Middleware**: Three specialized middleware types for route protection
- **Query Scopes**: Filter users by roles or permissions in your queries
- **Bulk Operations**: Efficiently sync multiple roles or permissions with transactions
- **Comprehensive Helper Methods**: Check single, any, or all permissions/roles
- **Full Test Coverage**: Thoroughly tested with Pest

## Requirements

- PHP 8.4 or higher
- Laravel 12.0 or higher

---

## Installation

You can install the package via composer:

```bash
composer require techieni3/laravel-user-permissions
```

## Setup

1. Publish the config file and migrations:

```bash
php artisan install:permissions
```

2. Run the migrations:

```bash
php artisan migrate
```


## Configuration

After publishing the config file, you can find it at `config/permissions.php`. Here are the main configuration options:

```php
return [
    // Directory containing all your Eloquent models
    'models_path' => app_path('Models'),

    // Excluded models that should NOT have permissions generated
    'excluded_models' => [
        // App\Models\User::class,
    ],

    // Your application's User model class
    'user_model' => App\Models\User::class,

    // Your application's Role enum class
    'role_enum' => App\Enums\Role::class,
];
```

### Setting Up Your Role Enum

Create a Role enum at `app/Enums/Role.php`:

```php
<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case User = 'user';
}
```

### Adding Traits to Your User Model

Add the `HasRoles` trait to your User model (this also includes `HasPermissions`):

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Techieni3\LaravelUserPermissions\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // ... rest of your User model
}
```

## Usage

### Syncing Roles and Permissions

First, sync your roles and permissions with the database:

```bash
# Sync roles from your Role enum
php artisan sync:roles

# Generate permissions for all models (creates view_any, view, create, update, delete, restore, force_delete permissions)
php artisan sync:permissions
```

### Working with Roles

#### Assigning Roles

```php
use App\Enums\Role;

// Add a single role
$user->addRole(Role::Admin);

// You can also use strings
$user->addRole('editor');

// Sync roles (replaces all existing roles)
$user->syncRoles([Role::Admin, Role::Editor]);

// Remove a role
$user->removeRole(Role::Admin);
```

#### Checking Roles

```php
// Check if user has a specific role
if ($user->hasRole(Role::Admin)) {
    // User is an admin
}

// Check if user has ANY of the given roles
if ($user->hasAnyRole([Role::Admin, Role::Editor])) {
    // User is either an admin or editor
}

// Check if user has ALL of the given roles
if ($user->hasAllRoles([Role::Admin, Role::Editor])) {
    // User is both an admin and editor
}

// Get all user roles
$roles = $user->getAllRoles();
```

### Working with Permissions

#### Understanding Permission Naming

Permissions are automatically generated with the following format: `{action}_{model}`. For example:
- `view_any_post` - View any posts
- `view_post` - View a single post
- `create_post` - Create a post
- `update_post` - Update a post
- `delete_post` - Delete a post
- `restore_post` - Restore a soft-deleted post
- `force_delete_post` - Permanently delete a post

#### Direct vs Role-Based Permissions

- **Direct Permissions**: Assigned directly to a user
- **Role-Based Permissions**: Granted to a user through their roles

Users have both direct permissions and permissions granted through their roles.

#### Assigning Permissions

```php
// Add a direct permission
$user->addPermission('create_post');

// Sync permissions (replaces all existing direct permissions)
$user->syncPermissions(['create_post', 'update_post', 'delete_post']);

// Remove a direct permission
$user->removePermission('create_post');
```

#### Checking Permissions

```php
// Check if user has a specific permission (checks both direct and role-based)
if ($user->hasPermissionTo('create_post')) {
    // User can create a post
}

// Alternative syntax
if ($user->hasPermission('create_post')) {
    // User can create a post
}

// Check if user has ANY of the given permissions
if ($user->hasAnyPermission(['create_post', 'update_post'])) {
    // User can create OR update posts
}

// Check if user has ALL of the given permissions
if ($user->hasAllPermissions(['create_post', 'update_post'])) {
    // User can create AND update posts
}

// Get all user permissions
$permissions = $user->getAllPermissions();
```

### Query Scopes

Filter users by roles or permissions in your database queries:

```php
// Get users with specific role
$admins = User::role(Role::Admin)->get();

// Get users with any of multiple roles
$editors = User::role([Role::Admin, Role::Editor])->get();

// Get users WITHOUT a specific role
$nonAdmins = User::withoutRole(Role::Admin)->get();

// Get users with specific permission
$canCreatePost = User::permission('create_post')->get();

// Get users with any of multiple permissions
$canManagePosts = User::permission(['create_post', 'update_post'])->get();

// Get users WITHOUT a specific permission
$cannotDelete = User::withoutPermission('delete_post')->get();
```

### Laravel Gate Integration

All permissions are automatically registered with [Laravel's Gate](https://laravel.com/docs/authorization):

```php
// Using the can method
if ($user->can('create_post')) {
    // User can create a post
}

// In Blade templates
@can('create_post')
    <button>Create Post</button>
@endcan

// Using the authorize method in controllers
$this->authorize('update_post');

// Using the can middleware
Route::post('/posts', [PostController::class, 'store'])
    ->middleware('can:create_post');
```

### Custom Middleware

The package provides three specialized middleware types for protecting routes:

#### 1. Permission Middleware

Requires the user to have at least one of the specified permissions:

```php
use Techieni3\LaravelUserPermissions\Middlewares\PermissionMiddleware;

// In app/Http/Kernel.php (or bootstrap/app.php for Laravel 11+)
protected $middlewareAliases = [
    'permission' => PermissionMiddleware::class,
];

// In your routes
Route::middleware(['permission:create_post'])->group(function () {
    // User must have 'create_post' permission
});

// Multiple permissions (user needs ANY of them)
Route::middleware(['permission:create_post|update_post'])->group(function () {
    // User must have 'create_post' OR 'update_post' permission
});
```

#### 2. Role Middleware

Requires the user to have at least one of the specified roles:

```php
use Techieni3\LaravelUserPermissions\Middlewares\RoleMiddleware;

// In app/Http/Kernel.php (or bootstrap/app.php for Laravel 11+)
protected $middlewareAliases = [
    'role' => RoleMiddleware::class,
];

// In your routes
Route::middleware(['role:admin'])->group(function () {
    // User must have 'admin' role
});

// Multiple roles (user needs ANY of them)
Route::middleware(['role:admin|editor'])->group(function () {
    // User must have 'admin' OR 'editor' role
});
```

#### 3. Role Or Permission Middleware

Requires the user to have at least one of the specified roles OR permissions:

```php
use Techieni3\LaravelUserPermissions\Middlewares\RoleOrPermissionMiddleware;

// In app/Http/Kernel.php (or bootstrap/app.php for Laravel 11+)
protected $middlewareAliases = [
    'role_or_permission' => RoleOrPermissionMiddleware::class,
];

// In your routes
Route::middleware(['role_or_permission:admin|create_post'])->group(function () {
    // User must have 'admin' role OR 'create_post' permission
});
```

## Performance Features

This package is optimized for performance with several built-in features:

### Request-Level Caching

Permissions and roles are cached for the duration of each request, preventing duplicate database queries:

```php
// These three calls only execute ONE database query total
$user->hasPermission('create_post');
$user->hasPermission('update_post');
$user->getAllPermissions();
```

### Optimized Queries

- Uses UNION queries to combine direct and role-based permissions efficiently
- Bulk operations use database transactions for atomicity
- Single queries for verifying multiple permissions/roles
- Proper indexing on pivot tables

### Cache Management

Caches are automatically cleared when roles or permissions are modified:

```php
// Cache is automatically cleared after these operations
$user->addRole(Role::Admin);
$user->syncPermissions(['create_post']);

// Manually clear caches if needed
$user->flushPermissionsCache();
$user->flushRolesCache();
```

## API Reference

### HasRoles Trait

Methods available when using the `HasRoles` trait:

| Method | Parameters | Description |
|--------|------------|-------------|
| `addRole()` | `string\|BackedEnum $role` | Add a role to the user |
| `removeRole()` | `string\|BackedEnum $role` | Remove a role from the user |
| `syncRoles()` | `array $roles` | Sync roles (replaces all existing roles) |
| `hasRole()` | `string\|BackedEnum $role` | Check if user has a specific role |
| `hasAnyRole()` | `array $roles` | Check if user has ANY of the given roles |
| `hasAllRoles()` | `array $roles` | Check if user has ALL of the given roles |
| `getAllRoles()` | - | Get all user roles as a collection |
| `flushRolesCache()` | - | Clear the roles cache |

### HasPermissions Trait

Methods available when using the `HasPermissions` trait (included in `HasRoles`):

| Method | Parameters | Description |
|--------|------------|-------------|
| `addPermission()` | `string $permission` | Add a direct permission to the user |
| `removePermission()` | `string $permission` | Remove a direct permission from the user |
| `syncPermissions()` | `array $permissions` | Sync permissions (replaces all direct permissions) |
| `hasPermission()` | `string $permission` | Check if user has a specific permission |
| `hasPermissionTo()` | `string $permission` | Alias for hasPermission() |
| `hasAnyPermission()` | `array $permissions` | Check if user has ANY of the given permissions |
| `hasAllPermissions()` | `array $permissions` | Check if user has ALL of the given permissions |
| `getAllPermissions()` | - | Get all user permissions as a collection |
| `flushPermissionsCache()` | - | Clear the permissions cache |

### Query Scopes

| Scope | Parameters | Description |
|-------|------------|-------------|
| `role()` | `string\|array\|BackedEnum` | Filter users with specific role(s) |
| `withoutRole()` | `string\|array\|BackedEnum` | Filter users without specific role(s) |
| `permission()` | `string\|array` | Filter users with specific permission(s) |
| `withoutPermission()` | `string\|array` | Filter users without specific permission(s) |

## Troubleshooting

### Permission/Role not found errors

If you get errors like "Permission 'xxx' is not synced with the database":

```bash
# Make sure to sync roles and permissions after setting up
php artisan sync:roles
php artisan sync:permissions

# Or run both at once
php artisan sync:roles && php artisan sync:permissions
```

### Role enum not found

Ensure your Role enum is created and properly configured:

1. Create the enum at `app/Enums/Role.php`
2. Update the config at `config/permissions.php` to point to your enum
3. Run `php artisan sync:roles`

### Middleware not working

Make sure to register the middleware aliases in your application:

**For Laravel 11+**, add to `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \Techieni3\LaravelUserPermissions\Middlewares\RoleMiddleware::class,
        'permission' => \Techieni3\LaravelUserPermissions\Middlewares\PermissionMiddleware::class,
        'role_or_permission' => \Techieni3\LaravelUserPermissions\Middlewares\RoleOrPermissionMiddleware::class,
    ]);
})
```

**For Laravel 10 and below**, add to `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    'role' => \Techieni3\LaravelUserPermissions\Middlewares\RoleMiddleware::class,
    'permission' => \Techieni3\LaravelUserPermissions\Middlewares\PermissionMiddleware::class,
    'role_or_permission' => \Techieni3\LaravelUserPermissions\Middlewares\RoleOrPermissionMiddleware::class,
];
```

### Permission naming conventions

Permissions use underscores, not hyphens:
- ✅ Correct: `create_post`, `update_user`
- ❌ Incorrect: `create-post`, `update-user`

The package automatically normalizes spaces and hyphens to underscores internally.

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse

# Format code
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Security

If you discover any security-related issues, please email [niteen1593@gmail.com](mailto:niteen1593@gmail.com) instead of using the issue tracker.

## Credits

- [Nitin Gaikwad](https://github.com/techieni3)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
