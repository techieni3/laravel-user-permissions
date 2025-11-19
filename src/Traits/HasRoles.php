<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use BackedEnum;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Techieni3\LaravelUserPermissions\Models\Role;
use Throwable;

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
     * @throws RuntimeException If the role doesn't exist or is already assigned.
     */
    public function addRole(string|BackedEnum $role): static
    {
        // Convert string role to BackedEnum if necessary
        $roleEnum = $this->convertToRoleEnum($role);

        // Verify role exists in the database
        $dbRole = $this->verifyRoleInDatabase($roleEnum);

        try {
            // Attach the role to the user (database constraint handles duplicate check)
            $this->roles()->attach($dbRole);
        } catch (QueryException $e) {
            // Duplicate entry error (integrity constraint violation)
            if ($e->getCode() === '23000') {
                throw new RuntimeException("Role '{$roleEnum->value}' is already assigned to the user.");
            }
            throw $e;
        }

        // Clear cached roles and permissions (since roles grant permissions)
        $this->flushRolesCache();
        $this->flushPermissionsCache();

        return $this;
    }

    /**
     * Remove a role from the user.
     *
     * @throws RuntimeException If the role doesn't exist or is not assigned.
     */
    public function removeRole(string|BackedEnum $role): static
    {
        // Convert string role to BackedEnum if necessary
        $roleEnum = $this->convertToRoleEnum($role);

        // Verify role exists in the database
        $dbRole = $this->verifyRoleInDatabase($roleEnum);

        try {
            // Detach and check if any rows were affected
            $detached = $this->roles()->detach($dbRole->id);
            if ($detached === 0) {
                throw new RuntimeException("Role '{$roleEnum->value}' is not assigned to the user.");
            }
        } catch (Exception $e) {
            throw new RuntimeException(
                "Role '{$roleEnum->value}' is not synced with the database. Please run \"php artisan sync:roles\" first."
            );
        }

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
     * @throws RuntimeException|Throwable If any role is not synced with the database.
     */
    public function syncRoles(array $roles): static
    {
        DB::transaction(function () use ($roles): void {
            // Convert all roles to enums
            $roleEnums = array_map(
                fn ($role) => $this->convertToRoleEnum($role),
                $roles
            );

            // Verify all roles in a single query
            $dbRoles = $this->verifyRoleInDatabase($roleEnums);
            $roleIds = $dbRoles->pluck('id')->all();

            // Detach all existing roles
            $this->roles()->detach();
            // Bulk attach all new roles
            if (count($roleIds) > 0) {
                $this->roles()->attach($roleIds);
            }
        });

        DB::afterCommit(function (): void {
            // Clear cached roles and permissions (since roles grant permissions)
            $this->flushRolesCache();
            $this->flushPermissionsCache();
        });

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
            ->map(fn (Role $role) => $role->name->value)
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

    /**
     * Convert a string role to a BackedEnum instance.
     *
     * @throws RuntimeException If the enum class is missing or the role is invalid.
     */
    private function convertToRoleEnum(string|BackedEnum $role): BackedEnum
    {
        if ($role instanceof BackedEnum) {
            return $role;
        }

        /** @var class-string $roleEnumClass */
        $roleEnumClass = Config::string('permissions.role_enum');
        $this->verifyRoleEnumFile($roleEnumClass);

        $roleEnumInstance = $roleEnumClass::tryFrom($role);

        if ($roleEnumInstance === null) {
            throw new RuntimeException(
                "The role '{$role}' is not valid for the {$roleEnumClass} enum."
            );
        }

        return $roleEnumInstance;
    }

    /**
     * Verify that the role enum exists.
     *
     * @throws RuntimeException If the enum class is missing.
     */
    private function verifyRoleEnumFile(string $roleEnum): void
    {
        if ( ! class_exists($roleEnum) || ! enum_exists($roleEnum)) {
            throw new RuntimeException(
                'Role enum not found. Please run "php artisan permissions:install" first.'
            );
        }
    }

    /**
     * Verify that role(s) exist in the database.
     *
     * @param  BackedEnum|array<BackedEnum>  $roleEnum
     * @return Role|Collection<int, Role>
     *
     * @throws RuntimeException If any role is not synced with the database.
     */
    private function verifyRoleInDatabase(BackedEnum|array $roleEnum): Role|Collection
    {
        // Handle single role
        if ($roleEnum instanceof BackedEnum) {
            $dbRole = Role::query()->where('name', $roleEnum->value)->first();

            if ( ! $dbRole) {
                throw new RuntimeException(
                    "Role '{$roleEnum->value}' is not synced with the database. Please run \"php artisan sync:roles\" first."
                );
            }

            return $dbRole;
        }

        // Handle multiple roles (bulk)
        $roleValues = array_map(fn ($role) => $role->value, $roleEnum);
        $dbRoles = Role::query()->whereIn('name', $roleValues)->get();

        // Verify all roles were found
        $foundNames = $dbRoles->pluck('name')->map(fn ($role) => $role->value)->all();
        $missingRoles = array_diff($roleValues, $foundNames);

        if (count($missingRoles) > 0) {
            $missingList = implode(', ', $missingRoles);
            throw new RuntimeException(
                "Roles [{$missingList}] are not synced with the database. Please run \"php artisan sync:roles\" first."
            );
        }

        return $dbRoles;
    }
}
