<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Techieni3\LaravelUserPermissions\Models\Role;

trait HasRoles
{
    use HasPermissions;

    /**
     * Get all roles for the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Role::class,
            table: 'users_roles',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'role_id'
        )->withTimestamps();
    }

    /**
     * Get all roles for the user as a collection.
     */
    public function getAllRoles(): Collection
    {
        return $this->roles()->get();
    }

    /**
     * Get all roles for the user.
     */
    public function hasRole(string|BackedEnum $role): bool
    {
        $this->loadMissing('roles');

        $roleString = $role;

        if ($role instanceof BackedEnum) {
            $roleString = $role->value;
        }

        return $this->roles
            ?->map(fn (Role $role) => $role->name->value)
            ?->contains($roleString);
    }

    /**
     * Add a role to the user.
     *
     * @throws RuntimeException If the role is already assigned, enum file is missing, or role is not synced with the database.
     */
    public function addRole(string|BackedEnum $role): void
    {
        // Check if the user already has the role
        if ($this->hasRole($role)) {
            throw new RuntimeException('Role is already assigned to the user.');
        }

        // Convert string role to BackedEnum if necessary
        $roleEnum = $this->convertToRoleEnum($role);

        // Verify role exists in the database
        $dbRole = $this->verifyRoleInDatabase($roleEnum);

        // Attach the role to the user
        $this->attachRole($dbRole);
    }

    /**
     * Remove a role from the user.
     *
     * @throws RuntimeException If the role is not assigned to the user.
     */
    public function removeRole(string|BackedEnum $role): void
    {
        // Check if the user has the role
        if ( ! $this->hasRole($role)) {
            throw new RuntimeException('Role is not assigned to the user.');
        }

        // Convert string role to BackedEnum if necessary
        $roleEnum = $this->convertToRoleEnum($role);

        // Verify role exists in the database
        $dbRole = $this->verifyRoleInDatabase($roleEnum);

        // Detach the role from the user
        $this->roles()->detach($dbRole);
    }

    /**
     * Sync roles with the user (removes all existing roles and adds new ones).
     *
     * @param  array<string|BackedEnum>  $roles
     * @throws RuntimeException If any role is not synced with the database.
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = [];

        foreach ($roles as $role) {
            $roleEnum = $this->convertToRoleEnum($role);
            $dbRole = $this->verifyRoleInDatabase($roleEnum);
            $roleIds[] = $dbRole->id;
        }

        $this->roles()->sync($roleIds);
    }

    /**
     * Check if the user has any of the given roles.
     *
     * @param  array<string|BackedEnum>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has all of the given roles.
     *
     * @param  array<string|BackedEnum>  $roles
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if ( ! $this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert a string role to a BackedEnum instance.
     *
     * @throws RuntimeException If the enum file is missing or the role is invalid.
     */
    private function convertToRoleEnum(string|BackedEnum $role): BackedEnum
    {
        if ($role instanceof BackedEnum) {
            return $role;
        }

        $roleEnumPath = Config::string('permissions.role_enum_file');
        $this->verifyRoleEnumFile($roleEnumPath);

        $roleEnumClass = $this->getRoleEnumClass($roleEnumPath);
        $roleEnumInstance = $roleEnumClass::tryFrom($role);

        if ($roleEnumInstance === null) {
            throw new RuntimeException(
                "The role '{$role}' is not valid for the {$roleEnumClass} enum."
            );
        }

        return $roleEnumInstance;
    }

    /**
     * Verify that the role enum file exists.
     *
     * @throws RuntimeException If the enum file is missing.
     */
    private function verifyRoleEnumFile(string $roleEnumPath): void
    {
        if ( ! File::exists($roleEnumPath)) {
            throw new RuntimeException(
                'Role enum not found in app/Enums folder. Please run "php artisan permissions:install" first.'
            );
        }
    }

    /**
     * Get the fully qualified class name of the role enum.
     *
     * @throws RuntimeException If the enum class is not found.
     */
    private function getRoleEnumClass(string $roleEnumPath): string
    {
        $enumClassName = basename($roleEnumPath, '.php');
        $roleEnumClass = 'App\\Enums\\' . $enumClassName;

        if ( ! class_exists($roleEnumClass)) {
            throw new RuntimeException(
                'Role enum class not found. Please make sure it\'s defined correctly.'
            );
        }

        return $roleEnumClass;
    }

    /**
     * Verify that the role exists in the database.
     *
     * @throws RuntimeException If the role is not synced with the database.
     */
    private function verifyRoleInDatabase(BackedEnum $roleEnum): Role
    {
        $dbRole = Role::where('name', $roleEnum)->first();

        if ( ! $dbRole) {
            throw new RuntimeException(
                'Role enum is not synced with the database. Please run "php artisan sync:roles" first.'
            );
        }

        return $dbRole;
    }

    /**
     * Attach the role to the user.
     */
    private function attachRole(Role $role): void
    {
        $this->roles()->attach($role);
    }
}
