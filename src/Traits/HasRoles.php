<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
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
     * Request-level cache for user roles.
     */
    protected ?Collection $cachedRoles = null;

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
     * Scope the model query to only include models with specific role(s).
     */
    public function scopeRole(Builder $query, string|array|BackedEnum $roles): Builder
    {
        $roles = is_array($roles) ? $roles : [$roles];

        // Convert to role values
        $roleValues = array_map(function ($role) {
            if ($role instanceof BackedEnum) {
                return $role->value;
            }

            return $role;
        }, $roles);

        return $query->whereHas('roles', function (Builder $q) use ($roleValues): void {
            $q->whereIn('name', $roleValues);
        });
    }

    /**
     * Scope the model query to exclude models with specific role(s).
     */
    public function scopeWithoutRole(Builder $query, string|array|BackedEnum $roles): Builder
    {
        $roles = is_array($roles) ? $roles : [$roles];

        // Convert to role values
        $roleValues = array_map(function ($role) {
            if ($role instanceof BackedEnum) {
                return $role->value;
            }

            return $role;
        }, $roles);

        return $query->whereDoesntHave('roles', function (Builder $q) use ($roleValues): void {
            $q->whereIn('name', $roleValues);
        });
    }

    /**
     * Add a role to the user.
     *
     * @throws RuntimeException If the role is already assigned, enum file is missing, or role is not synced with the database.
     */
    public function addRole(string|BackedEnum $role): static
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
        $this->roles()->attach($dbRole);

        // Clear cached roles and permissions (since roles grant permissions)
        $this->flushRolesCache();
        $this->flushPermissionsCache();

        return $this;
    }

    /**
     * Remove a role from the user.
     *
     * @throws RuntimeException If the role is not assigned to the user.
     */
    public function removeRole(string|BackedEnum $role): static
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

        // Clear cached roles and permissions (since roles grant permissions)
        $this->flushRolesCache();
        $this->flushPermissionsCache();

        return $this;
    }

    /**
     * Sync roles with the user (removes all existing roles and adds new ones).
     *
     * @param  array<string|BackedEnum>  $roles
     *
     * @throws RuntimeException If any role is not synced with the database.
     */
    public function syncRoles(array $roles): static
    {
        $roleIds = [];

        foreach ($roles as $role) {
            $roleEnum = $this->convertToRoleEnum($role);
            $dbRole = $this->verifyRoleInDatabase($roleEnum);
            $roleIds[] = $dbRole->id;
        }

        $this->roles()->sync($roleIds);

        // Clear cached roles and permissions (since roles grant permissions)
        $this->flushRolesCache();
        $this->flushPermissionsCache();

        return $this;
    }

    /**
     * Check if the user has a specific role.
     * Uses request-level cache to avoid multiple DB queries within the same request.
     */
    public function hasRole(string|BackedEnum $role): bool
    {
        $roleString = $role;

        if ($role instanceof BackedEnum) {
            $roleString = $role->value;
        }

        return $this->getCachedRoles()
            ->map(fn (Role $role) => $role->name)
            ->contains($roleString);
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
     * Get all roles for the user as a collection.
     * Uses request-level cache to avoid multiple DB queries within the same request.
     */
    public function getAllRoles(): Collection
    {
        return $this->getCachedRoles();
    }

    /**
     * Clear the request-level roles cache.
     * Should be called after adding/removing/syncing roles.
     */
    public function flushRolesCache(): void
    {
        $this->cachedRoles = null;
    }

    // 6. Protected Methods (helpers)
    /**
     * Get cached roles or load them from database.
     * This ensures roles are only queried once per request.
     */
    protected function getCachedRoles(): Collection
    {
        if ($this->cachedRoles === null) {
            $this->cachedRoles = $this->roles()->get();
        }

        return $this->cachedRoles;
    }

    // 7. Private Methods (utilities)
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

        $roleEnumPath = Config::string('permissions.role_enum');
        $this->verifyRoleEnumFile($roleEnumPath);

        /** @var class-string $roleEnumClass */
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
        $dbRole = Role::query()->where('name', $roleEnum)->first();

        if ( ! $dbRole) {
            throw new RuntimeException(
                'Role enum is not synced with the database. Please run "php artisan sync:roles" first.'
            );
        }

        return $dbRole;
    }
}
