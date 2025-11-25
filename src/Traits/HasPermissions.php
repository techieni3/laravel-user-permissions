<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Techieni3\LaravelUserPermissions\Events\PermissionAdded;
use Techieni3\LaravelUserPermissions\Events\PermissionRemoved;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Throwable;

/**
 * HasPermissions Trait.
 *
 * Provides permission management functionality for models (typically User).
 * This trait is included by the HasRoles trait but can also be used independently.
 *
 * Features:
 * - Direct permission assignment and removal
 * - Permission verification (hasPermission, hasAnyPermission, hasAllPermissions)
 * - Query scopes for filtering by permissions
 * - Request-level caching to prevent N+1 queries
 * - Integration with role-based permissions
 * - Bulk permission operations with transactions
 */
trait HasPermissions
{
    /**
     * Request-level cache for user permissions.
     * Prevents multiple database queries for the same user's permissions within a single request.
     *
     * @var Collection<int, Permission>|null
     */
    protected ?Collection $permissionsCache = null;

    /**
     * Get all direct permissions assigned to the model.
     * Direct permissions are those explicitly assigned to the user,
     * not granted through roles.
     *
     * @return BelongsToMany<Permission>
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Permission::class,
            table: 'users_permissions',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'permission_id'
        )->withTimestamps();
    }

    /**
     * Get all permissions for the user, including those granted through roles.
     * Uses a union query to combine direct permissions and role-based permissions.
     * Results are deduplicated by permission ID.
     *
     * @return Collection<int, Permission>
     */
    public function getPermissionsWithRoles(): Collection
    {
        $direct = DB::table('users_permissions')
            ->join(
                table: 'permissions',
                first: 'permissions.id',
                operator: '=',
                second: 'users_permissions.permission_id'
            )
            ->where('users_permissions.user_id', $this->id)
            ->select('permissions.*');

        $role = DB::table('users_roles')
            ->join(
                table: 'roles_permissions',
                first: 'roles_permissions.role_id',
                operator: '=',
                second: 'users_roles.role_id'
            )
            ->join(
                table: 'permissions',
                first: 'permissions.id',
                operator: '=',
                second: 'roles_permissions.permission_id'
            )
            ->where('users_roles.user_id', $this->id)
            ->select('permissions.*');

        $rows = $direct->union($role)->get();

        return Permission::hydrate($rows->all())->unique('id')->values();
    }

    /**
     * Scope the model query to only include models with specific permission(s).
     * Checks both direct permissions and permissions via roles.
     *
     * @param  Builder<Model>  $query
     * @param  string|array<string>  $permissions
     * @return Builder<Model>
     */
    public function scopePermission(Builder $query, string|array $permissions): Builder
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        // Normalize permission names
        $permissionNames = array_map($this->normalizePermissionName(...), $permissions);

        return $query->where(function (Builder $q) use ($permissionNames): void {
            // Check direct permissions
            $q->whereHas('directPermissions', function (Builder $query) use ($permissionNames): void {
                $query->whereIn('name', $permissionNames);
            })
                // OR permissions via roles
                ->orWhereHas('roles.permissions', function (Builder $query) use ($permissionNames): void {
                    $query->whereIn('permissions.name', $permissionNames);
                });
        });
    }

    /**
     * Scope the model query to exclude models with specific permission(s).
     * Checks both direct permissions and permissions via roles.
     * Supports single or multiple permissions.
     *
     * @param  Builder<Model>  $query
     * @param  string|array<string>  $permissions  Single permission or array of permissions
     * @return Builder<Model>
     */
    public function scopeWithoutPermission(Builder $query, string|array $permissions): Builder
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        // Normalize permission names
        $permissionNames = array_map($this->normalizePermissionName(...), $permissions);

        return $query
            ->whereDoesntHave('directPermissions', function (Builder $query) use ($permissionNames): void {
                $query->whereIn('name', $permissionNames);
            })
            ->whereDoesntHave('roles.permissions', function (Builder $query) use ($permissionNames): void {
                $query->whereIn('permissions.name', $permissionNames);
            });
    }

    /**
     * Add a permission to the user.
     * Only adds direct permissions. Prevents adding permissions already granted via roles.
     * Clears permission cache after assignment.
     *
     * @param  string  $permission  The permission name to add
     *
     * @throws RuntimeException If the permission doesn't exist, is already directly assigned, or is granted via role
     */
    public function addPermission(string $permission): static
    {
        $permissionName = $this->normalizePermissionName($permission);

        // Get permission from the database
        $dbPermission = $this->findPermissionOrFail($permissionName);

        // Check if the user already has the permission
        if ($this->hasPermission($permission)) {
            throw new RuntimeException('Permission is already assigned to the user.');
        }

        // Attach the permission to the user
        $this->directPermissions()->attach($dbPermission);

        // Dispatch event if events are enabled
        if (Config::boolean('permissions.events_enabled', false)) {
            event(new PermissionAdded($this, $dbPermission));
        }

        // Clear cached permissions
        $this->clearPermissionsCache();

        return $this;
    }

    /**
     * Remove a permission from the user.
     * Only removes direct permissions, not permissions granted through roles.
     * Clears permission cache after removal.
     *
     * @param  string  $permission  The permission name to remove
     *
     * @throws RuntimeException If the permission doesn't exist or is not directly assigned
     */
    public function removePermission(string $permission): static
    {
        $searchablePermission = $this->normalizePermissionName($permission);

        // Get permission from the database
        $dbPermission = $this->findPermissionOrFail($searchablePermission);

        // Detach the permission from the user
        $detached = $this->directPermissions()->detach($dbPermission->id);

        if ($detached === 0) {
            throw new RuntimeException('Permission is not assigned to the user.');
        }

        // Dispatch event if events are enabled
        if (Config::boolean('permissions.events_enabled', false)) {
            event(new PermissionRemoved($this, $dbPermission));
        }

        // Clear cached permissions
        $this->clearPermissionsCache();

        return $this;
    }

    /**
     * Sync permissions with the user (removes all existing direct permissions and adds new ones).
     * Performs the operation in a database transaction for atomicity.
     * Uses bulk operations for optimal performance when syncing multiple permissions.
     * Excludes permissions already granted via roles to avoid duplicates.
     *
     * @param  array<string>  $permissions  Array of permission names to sync
     *
     * @throws RuntimeException|Throwable If any permission is not synced with the database
     */
    public function syncPermissions(array $permissions): static
    {
        // Normalize all permission names
        $searchablePermissions = array_map($this->normalizePermissionName(...), $permissions);

        // Verify all permissions in a single query
        $dbPermissions = $this->findPermissionOrFail($searchablePermissions);
        $permissionIds = $dbPermissions->pluck('id');

        // Get permission IDs already granted via roles to avoid duplicates
        $rolePermissionIds = $this->getRolePermissionIds();

        $permissionIdsToSync = $permissionIds->diff($rolePermissionIds)->all();

        DB::transaction(function () use ($permissionIdsToSync): void {
            // Detach all existing permissions
            $this->directPermissions()->detach();
            // Attach all new permissions
            if (count($permissionIdsToSync) > 0) {
                $this->directPermissions()->attach($permissionIdsToSync);
            }
        });

        DB::afterCommit(function (): void {
            // Clear cached permissions
            $this->clearPermissionsCache();
        });

        return $this;
    }

    /**
     * Check if the user has a specific permission.
     * Uses request-level cache to avoid multiple DB queries within the same request.
     *
     * @param  string  $permission  The permission name to check
     * @return bool True if the user has the permission, false otherwise
     */
    public function hasPermission(string $permission): bool
    {
        return $this->getPermissions()
            ->map(static fn (Permission $permission) => $permission->name)
            ->contains($this->normalizePermissionName($permission));
    }

    /**
     * Alias for hasPermission.
     *
     * @param  string  $permission  The permission name to check
     * @return bool True if the user has the permission, false otherwise
     */
    public function hasPermissionTo(string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    /**
     * Check if the user has any of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return array_any($permissions, fn ($permission) => $this->hasPermission($permission));
    }

    /**
     * Check if the user has all the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return array_all($permissions, fn ($permission) => $this->hasPermission($permission));
    }

    /**
     * Get cached permissions or load them from a database.
     * This ensures permissions are only queried once per request for optimal performance.
     *
     * @return Collection<int, Permission>
     */
    public function getPermissions(): Collection
    {
        if ($this->permissionsCache === null) {
            $this->permissionsCache = $this->getPermissionsWithRoles();
        }

        return $this->permissionsCache;
    }

    /**
     * Clear the request-level permissions cache.
     * Should be called after adding/removing/syncing permissions to ensure fresh data.
     */
    private function clearPermissionsCache(): void
    {
        $this->permissionsCache = null;
    }

    /**
     * Normalize permission name to lowercase with underscores.
     * Converts spaces and hyphens to underscores for consistent permission naming.
     *
     * @param  string  $permissionString  The permission name to normalize
     * @return string The normalized permission name
     */
    private function normalizePermissionName(string $permissionString): string
    {
        $normalized = mb_strtolower(str_replace([' ', '-', '_'], '.', $permissionString));

        if (in_array(mb_trim($normalized), ['', '0'], true)) {
            throw new InvalidArgumentException('Permission name cannot be empty.');
        }

        if ( ! preg_match('/^[a-z0-9_.]+$/', $normalized)) {
            throw new InvalidArgumentException(
                "Permission name '{$normalized}' contains invalid characters. Only lowercase letters, numbers, underscores, and dots are allowed."
            );
        }

        return $normalized;
    }

    /**
     * Get permission IDs that the user has through their roles.
     * This is used internally by syncPermissions() to prevent duplicate direct permissions.
     *
     * @return array<int, mixed> Array of permission IDs
     */
    private function getRolePermissionIds(): array
    {
        return DB::table('users_roles')
            ->join(
                table: 'roles_permissions',
                first: 'roles_permissions.role_id',
                operator: '=',
                second: 'users_roles.role_id'
            )
            ->where('users_roles.user_id', $this->id)
            ->pluck('roles_permissions.permission_id')
            ->all();
    }

    /**
     * Verify that permission(s) exist in the database.
     * Handles both single permission and bulk permission verification.
     *
     * @param  string|array<string>  $searchablePermission  Single permission name or array of permission names
     * @return Permission|Collection<int, Permission> Single Permission model or Collection of Permissions
     *
     * @throws RuntimeException If any permission is not synced with the database
     */
    private function findPermissionOrFail(string|array $searchablePermission): Permission|Collection
    {
        // Handle single permission
        if (is_string($searchablePermission)) {
            $permission = Permission::query()
                ->where('name', '=', $searchablePermission)
                ->first();

            if ( ! $permission) {
                throw new RuntimeException(
                    "Permission '{$searchablePermission}' is not synced with the database. Please run \"php artisan sync:permissions\" first."
                );
            }

            return $permission;
        }

        // Handle multiple permissions (bulk)
        $permissions = Permission::query()
            ->whereIn('name', $searchablePermission)
            ->get();

        // Verify all permissions were found
        $foundNames = $permissions->pluck('name')->all();
        $missingPermissions = array_diff($searchablePermission, $foundNames);

        if ($missingPermissions !== []) {
            $missingList = implode(', ', $missingPermissions);
            throw new RuntimeException("Permissions [{$missingList}] are not synced with the database. Please run \"php artisan sync:permissions\" first.");
        }

        return $permissions;
    }
}
