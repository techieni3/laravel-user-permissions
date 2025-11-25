<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Exceptions;

use RuntimeException;

/**
 * PermissionException
 *
 * Custom exception for permission-related errors.
 * Provides static factory methods for common permission error scenarios.
 */
class PermissionException extends RuntimeException
{
    /**
     * Create an exception for permission(s) not found in a database.
     *
     * @param  string|array<string>  $permissions
     */
    public static function notFound(string|array $permissions): self
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $list = implode(', ', $permissions);
        $plural = count($permissions) > 1 ? 'Permissions' : 'Permission';
        $verb = count($permissions) > 1 ? 'are' : 'is';

        return new self(
            "{$plural} '{$list}' {$verb} not synced with the database. ".
            "Please run 'php artisan sync:permissions' first."
        );
    }

    /**
     * Create an exception for permission already assigned to the user.
     */
    public static function alreadyAssigned(string $permission): self
    {
        return new self("Permission '{$permission}' is already assigned to the user.");
    }

    /**
     * Create an exception for permission not assigned to the user.
     */
    public static function notAssigned(string $permission): self
    {
        return new self("Permission '{$permission}' is not assigned to the user.");
    }
}
