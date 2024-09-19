# Laravel User Permissions

## Overview

`techieni3/laravel-user-permissions` is a Laravel package that simplifies role and permission management for users. It leverages enums for role generation and integrates with existing models to handle permissions seamlessly. With its built-in traits and commands, it provides an easy way to manage user roles and permissions in your Laravel application.

### Features

1. **Role Generation**: Roles can be auto-generated based on a `Role.php`.

2. **Permission Generation**: Permissions can be auto-generated using your existing models from the `app/Models` directory.

3. **Traits for User Model**:
    - `HasRoles`: Assign roles to the user.
    - `HasPermissions`: Assign and check user permissions.

4. **Artisan Commands**:
    - `install:permissions`: Installs and publishes config and stub files.
    - `sync:roles`: Syncs the `Role.php` enum with the roles table in the database.
    - `sync:permissions`: Generates permissions for models from `app/Models` directory.

5. **Helper Methods**:
    - `hasPermissionTo`: Check if the user has a given permission.
    - `hasRole`: Check if the user has a given role.

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

- `user_model_file`: Sets the path to your `User` Model file. This file is used to add HasRoles Trait automatically.

- `models_directory`: Specifies the path to your application's models directory. The package will scan this directory to generate permissions automatically.

- `role_enum_file`: Sets the path to your `Role.php` enum file. This file is used to generate roles automatically.

- `excluded_models`: An array of model classes that should be excluded from automatic permission generation. By default, the `User` model is excluded.

You can customize these settings to fit your application's structure and requirements.

## Usage

### Syncing Roles

To sync roles based on your `Role.php` Enum file:

```bash
php artisan sync:roles
```

### Syncing Permissions

To generate and sync permissions based on your models:

```bash
php artisan sync:permissions
```

### Checking Permissions

You can check if a user has a specific permission:

```php
if ($user->hasPermissionTo('create-post')) {
    // User can create a post
}
```

### Checking Roles

You can check if a user has a specific role:

```php
if ($user->hasRole(Role::Admin)) {
    // User is an admin
}
```

### With Laravel's Gate
All permissions will be registered on [Laravel's gate](https://laravel.com/docs/authorization), you can check if a user has a permission with Laravel's default `can` function:

```php
$user->can('create-post');
```

### With Laravel's Middleware

You can protect routes based on permissions by utilizing the can middleware provided by Laravel. This package integrates seamlessly with the can middleware, allowing you to restrict access to routes based on the permissions assigned to users.
```php
Route::group(['middleware' => ['can:create-post']], function () { ... });
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you've found a bug regarding security please mail [niteen1593@gmail.com](mailto:niteen1593@gmail.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see License File for more information.
