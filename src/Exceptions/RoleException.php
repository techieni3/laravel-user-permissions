<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Exceptions;

use RuntimeException;

/**
 * RoleException
 *
 * Custom exception for role-related errors.
 * Provides static factory methods for common role error scenarios.
 */
class RoleException extends RuntimeException
{
    /**
     * Create an exception for role(s) not found in a database.
     *
     * @param  string|array<string>  $roles  The role name(s) that were not found
     */
    public static function notFound(string|array $roles): self
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $list = implode(', ', $roles);
        $plural = count($roles) > 1 ? 'Roles' : 'Role';
        $verb = count($roles) > 1 ? 'are' : 'is';

        return new self(
            "{$plural} '{$list}' {$verb} not synced with the database. ".
            "Please run 'php artisan sync:roles' first."
        );
    }

    /**
     * Create an exception for a role already assigned to the user.
     */
    public static function alreadyAssigned(string $role): self
    {
        return new self("Role '{$role}' is already assigned to the user.");
    }

    /**
     * Create an exception for a role not assigned to the user.
     */
    public static function notAssigned(string $role): self
    {
        return new self("Role '{$role}' is not assigned to the user.");
    }

    /**
     * Create exception for missing role enum.
     */
    public static function enumNotFound(): self
    {
        return new self('Role enum not found. Please run "php artisan permissions:install" first.');
    }
}
