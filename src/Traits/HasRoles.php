<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Techieni3\LaravelUserPermissions\Events\RoleAdded;
use Techieni3\LaravelUserPermissions\Events\RoleRemoved;
use Techieni3\LaravelUserPermissions\Exceptions\RoleException;
use Techieni3\LaravelUserPermissions\Models\Role;
use Throwable;

/**
 * HasRoles Trait.
 *
 * Provides role management functionality for models (typically User).
 * This trait includes the HasPermissions trait to provide both role
 * and permission management capabilities.
 *
 * Features:
 * - Role assignment and removal
 * - Role verification (hasRole, hasAnyRole, hasAllRoles)
 * - Query scopes for filtering by roles
 * - Request-level caching to prevent N+1 queries
 * - Enum-based role management
 */
trait HasRoles
{
    use HasPermissions;

    /**
     * Request-level cache for user roles.
     * Prevents multiple database queries for the same user's roles within a single request.
     *
     * @var Collection<int, Role>|null
     */
    protected ?Collection $rolesCache = null;

    /**
     * Get all roles for the user.
     * Defines the many-to-many relationship between users and roles.
     *
     * @return BelongsToMany<Role>
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
     * Supports single or multiple roles, and accepts both string and BackedEnum values.
     *
     * @param  Builder<Model>  $query
     * @param  string|array<string|BackedEnum>|BackedEnum  $roles  Single role or array of roles
     * @return Builder<Model>
     */
    public function scopeRole(Builder $query, string|array|BackedEnum $roles): Builder
    {
        $roles = is_array($roles) ? $roles : [$roles];

        // Convert to role names
        $roleNames = array_map(static fn (BackedEnum|string $role): int|string => $role instanceof BackedEnum ? $role->value : $role, $roles);

        return $query->whereHas('roles', function (Builder $q) use ($roleNames): void {
            $q->whereIn('name', $roleNames);
        });
    }

    /**
     * Scope the model query to exclude models with specific role(s).
     * Supports single or multiple roles and accepts both string and BackedEnum values.
     *
     * @param  Builder<Model>  $query
     * @param  string|array<string|BackedEnum>|BackedEnum  $roles  Single role or array of roles
     * @return Builder<Model>
     */
    public function scopeWithoutRole(Builder $query, string|array|BackedEnum $roles): Builder
    {
        $roles = is_array($roles) ? $roles : [$roles];

        // Convert to role names
        $roleNames = array_map(static fn (BackedEnum|string $role): int|string => $role instanceof BackedEnum ? $role->value : $role, $roles);

        return $query->whereDoesntHave('roles', function (Builder $q) use ($roleNames): void {
            $q->whereIn('name', $roleNames);
        });
    }

    /**
     * Add a role to the user.
     * Verifies the role exists in the database and isn't already assigned.
     * Clears both role and permission caches after assignment.
     *
     * @param  string|BackedEnum  $role  The role to add (string or enum instance)
     *
     * @throws RoleException If the role doesn't exist or is already assigned
     */
    public function addRole(string|BackedEnum $role): static
    {
        // Convert the string role to BackedEnum if necessary
        $roleEnum = $this->convertToRoleEnum($role);

        // Verify role exists in the database
        $dbRole = $this->findRoleOrFail($roleEnum);

        try {
            // Attach the role to the user (database constraint handles duplicate check)
            $this->roles()->attach($dbRole);
        } catch (QueryException $queryException) {
            // Duplicate entry error (integrity constraint violation)
            if ($queryException->getCode() === '23000') {
                throw RoleException::alreadyAssigned($roleEnum->value);
            }

            throw $queryException;
        }

        // Dispatch event if events are enabled
        if (Config::boolean('permissions.events_enabled', false)) {
            event(new RoleAdded($this, $dbRole));
        }

        // Clear cached roles and permissions (since roles grant permissions)
        $this->clearRolesCache();
        $this->clearPermissionsCache();

        return $this;
    }

    /**
     * Remove a role from the user.
     * Verifies the role exists and is currently assigned before removal.
     * Clears both role and permission caches after removal.
     *
     * @param  string|BackedEnum  $role  The role to remove (string or enum instance)
     *
     * @throws RoleException If the role doesn't exist or is not assigned
     */
    public function removeRole(string|BackedEnum $role): static
    {
        // Convert the string role to BackedEnum if necessary
        $roleEnum = $this->convertToRoleEnum($role);

        // Verify role exists in the database
        $dbRole = $this->findRoleOrFail($roleEnum);

        $detachedCount = $this->roles()->detach($dbRole->id);
        if ($detachedCount === 0) {
            throw RoleException::notAssigned($roleEnum->value);
        }

        // Dispatch event if events are enabled
        if (Config::boolean('permissions.events_enabled', false)) {
            event(new RoleRemoved($this, $dbRole));
        }

        // Clear cached roles and permissions (since roles grant permissions)
        $this->clearRolesCache();
        $this->clearPermissionsCache();

        return $this;
    }

    /**
     * Sync roles with the user (removes all existing roles and adds new ones).
     * Performs the operation in a database transaction for atomicity.
     * Uses bulk operations for optimal performance when syncing multiple roles.
     *
     * @param  array<string|BackedEnum>  $roles  Array of roles to sync
     *
     * @throws RoleException|Throwable If any role is not synced with the database
     */
    public function syncRoles(array $roles): static
    {
        DB::transaction(function () use ($roles): void {
            // Convert all roles to enums
            $roleEnums = array_map($this->convertToRoleEnum(...), $roles);

            // Verify all roles in a single query
            $dbRoles = $this->findRoleOrFail($roleEnums);
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
            $this->clearRolesCache();
            $this->clearPermissionsCache();
        });

        return $this;
    }

    /**
     * Check if the user has a specific role.
     * Uses request-level cache to avoid multiple DB queries within the same request.
     *
     * @param  string|BackedEnum  $role  The role to check (string or enum instance)
     * @return bool True if the user has the role, false otherwise
     */
    public function hasRole(string|BackedEnum $role): bool
    {
        $roleString = $role;

        if ($role instanceof BackedEnum) {
            $roleString = $role->value;
        }

        return $this->getRoles()
            ->map(static fn (Role $role) => $role->name->value)
            ->contains($roleString);
    }

    /**
     * Check if the user has any of the given roles.
     * Returns true if the user has at least one of the specified roles.
     *
     * @param  array<string|BackedEnum>  $roles  Array of roles to check
     * @return bool True if user has at least one role, false otherwise
     */
    public function hasAnyRole(array $roles): bool
    {
        return array_any($roles, fn ($role) => $this->hasRole($role));
    }

    /**
     * Check if the user has all the given roles.
     * Returns true only if the user has every specified role.
     *
     * @param  array<string|BackedEnum>  $roles  Array of roles to check
     * @return bool True if a user has all roles, false otherwise
     */
    public function hasAllRoles(array $roles): bool
    {
        return array_all($roles, fn ($role) => $this->hasRole($role));
    }

    /**
     * Get cached roles or load them from database.
     * This ensures roles are only queried once per request for optimal performance.
     *
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        if ($this->rolesCache === null) {
            $this->rolesCache = $this->roles()->get();
        }

        return $this->rolesCache;
    }

    /**
     * Clear the request-level roles cache.
     * Should be called after adding/removing/syncing roles to ensure fresh data.
     */
    private function clearRolesCache(): void
    {
        $this->rolesCache = null;
    }

    /**
     * Convert a string role to a BackedEnum instance.
     * If already a BackedEnum, returns it unchanged.
     * Validates that the role exists in the configured role enum.
     *
     * @param  string|BackedEnum  $role  The role to convert
     * @return BackedEnum The role as a BackedEnum instance
     *
     * @throws RoleException If the enum class is missing or the role is invalid
     */
    private function convertToRoleEnum(string|BackedEnum $role): BackedEnum
    {
        if ($role instanceof BackedEnum) {
            return $role;
        }

        /** @var class-string $roleEnumClass */
        $roleEnumClass = Config::string('permissions.role_enum');
        $this->ensureRoleEnumExists($roleEnumClass);

        $roleEnumInstance = $roleEnumClass::tryFrom($role);

        if ($roleEnumInstance === null) {
            throw RoleException::notFound($role);
        }

        return $roleEnumInstance;
    }

    /**
     * Verify that the role enum class exists and is a valid enum.
     * Checks both class existence and that it's actually an enum type.
     *
     * @param  string  $roleEnum  The fully qualified class name of the role enum
     *
     * @throws RoleException If the enum class is missing or not a valid enum
     */
    private function ensureRoleEnumExists(string $roleEnum): void
    {
        if ( ! class_exists($roleEnum) || ! enum_exists($roleEnum)) {
            throw RoleException::enumNotFound();
        }
    }

    /**
     * Verify that role(s) exist in the database.
     * Handles both single role and bulk role verification.
     *
     * @param  BackedEnum|array<BackedEnum>  $roleEnum  Single role or array of roles
     * @return Role|Collection<int, Role> Single Role model or Collection of Roles
     *
     * @throws RoleException If any role is not synced with the database
     */
    private function findRoleOrFail(BackedEnum|array $roleEnum): Role|Collection
    {
        // Handle single role
        if ($roleEnum instanceof BackedEnum) {
            $dbRole = Role::query()
                ->select(['id', 'name'])
                ->where('name', $roleEnum->value)
                ->first();

            if ( ! $dbRole) {
                throw RoleException::notFound($roleEnum->value);
            }

            return $dbRole;
        }

        // Handle multiple roles (bulk)
        $roleValues = array_map(static fn (BackedEnum $role): int|string => $role->value, $roleEnum);
        $dbRoles = Role::query()
            ->select(['id', 'name'])
            ->whereIn('name', $roleValues)
            ->get();

        // Verify all roles were found
        $foundNames = $dbRoles->pluck('name')->map(static fn ($role) => $role->value)->all();
        $missingRoles = array_diff($roleValues, $foundNames);

        if ($missingRoles !== []) {
            throw RoleException::notFound($missingRoles);
        }

        return $dbRoles;
    }
}
