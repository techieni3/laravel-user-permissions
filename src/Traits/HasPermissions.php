<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Throwable;

trait HasPermissions
{
    /**
     * Request-level cache for user permissions.
     */
    protected ?Collection $cachedPermissions = null;

    /**
     * Get all permissions those granted through roles.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Permission::class,
            table: 'roles_permissions',
            foreignPivotKey: 'role_id',
            relatedPivotKey: 'permission_id'
        )
            ->withTimestamps();
    }

    /**
     * Model may have multiple direct permissions.
     */
    public function directPermissions(): BelongsToMany
    {

        return $this->belongsToMany(
            related: Permission::class,
            table: 'users_permissions',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'permission_id'
        )
            ->withTimestamps();
    }

    /**
     * Get all permissions for the user, including those granted through roles.
     */
    public function allPermissions(): Collection
    {
        $direct = DB::table('users_permissions')
            ->join('permissions', 'permissions.id', '=', 'users_permissions.permission_id')
            ->where('users_permissions.user_id', $this->id)
            ->select('permissions.*');

        $role = DB::table('users_roles')
            ->join('roles_permissions', 'roles_permissions.role_id', '=', 'users_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'roles_permissions.permission_id')
            ->where('users_roles.user_id', $this->id)
            ->select('permissions.*');

        $rows = $direct->union($role)->get();

        return Permission::hydrate($rows->all())->unique('id')->values();
    }

    /**
     * Scope the model query to only include models with specific permission(s).
     * Checks both direct permissions and permissions via roles.
     */
    public function scopePermission(Builder $query, string|array $permissions): Builder
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        // Normalize permission names
        $permissionNames = array_map(fn ($p) => $this->makePermissionName($p), $permissions);

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
     */
    public function scopeWithoutPermission(Builder $query, string|array $permissions): Builder
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        // Normalize permission names
        $permissionNames = array_map(fn ($p) => $this->makePermissionName($p), $permissions);

        return $query->whereDoesntHave('directPermissions', function (Builder $query) use ($permissionNames): void {
            $query->whereIn('name', $permissionNames);
        })
            ->whereDoesntHave('roles.permissions', function (Builder $query) use ($permissionNames): void {
                $query->whereIn('permissions.name', $permissionNames);
            });
    }

    /**
     * Add a permission to the user.
     * Only adds direct permissions. Prevents adding permissions already granted via roles.
     *
     * @throws RuntimeException If the permission doesn't exist, is already directly assigned, or is granted via role.
     */
    public function addPermission(string $permission): static
    {
        $searchablePermission = $this->makePermissionName($permission);

        // Get permission from the database
        $dbPermission = $this->verifyPermissionInDatabase($searchablePermission);

        // Check if the user already has the permission
        if ($this->hasPermission($permission)) {
            throw new RuntimeException('Permission is already assigned to the user.');
        }

        // Attach the permission to the user
        $this->directPermissions()->attach($dbPermission);

        // Clear cached permissions
        $this->flushPermissionsCache();

        return $this;
    }

    /**
     * Remove a permission from the user.
     * Only removes direct permissions, not permissions granted through roles.
     *
     * @throws RuntimeException If the permission doesn't exist, is not directly assigned, or is granted via role.
     */
    public function removePermission(string $permission): static
    {
        $searchablePermission = $this->makePermissionName($permission);

        // Get permission from the database
        $dbPermission = $this->verifyPermissionInDatabase($searchablePermission);

        $directPermissionExists = $this->directPermissions()->where('permission_id', $dbPermission->id)->exists();

        // Check if the user has the permission
        if ( ! $directPermissionExists) {
            throw new RuntimeException('Permission is not assigned to the user.');
        }

        // Detach the permission from the user
        $this->directPermissions()->detach($dbPermission->id);

        // Clear cached permissions
        $this->flushPermissionsCache();

        return $this;
    }

    /**
     * Sync permissions with the user (removes all existing direct permissions and adds new ones).
     *
     * @param  array<string>  $permissions
     *
     * @throws RuntimeException|Throwable If any permission is not synced with the database.
     */
    public function syncPermissions(array $permissions): static
    {
        // Normalize all permission names
        $searchablePermissions = array_map(
            fn ($permission) => $this->makePermissionName($permission),
            $permissions
        );

        // Verify all permissions in a single query
        $dbPermissions = $this->verifyPermissionInDatabase($searchablePermissions);
        $permissionIds = $dbPermissions->pluck('id');

        $rolePermissionExists = $this->permissions()->pluck('permissions.id')->all();

        $idsToSync = $permissionIds->diff($rolePermissionExists)->all();

        DB::transaction(function () use ($idsToSync): void {
            // Detach all existing permissions
            $this->directPermissions()->detach();
            // Attach all new permissions
            if (count($idsToSync) > 0) {
                $this->directPermissions()->attach($idsToSync);
            }
        });

        DB::afterCommit(function (): void {
            // Clear cached permissions
            $this->flushPermissionsCache();
        });

        return $this;
    }

    /**
     * Check if the user has a specific permission.
     * Uses request-level cache to avoid multiple DB queries within the same request.
     */
    public function hasPermission(string $permissionString): bool
    {
        return $this->getCachedPermissions()
            ->map(fn (Permission $permission) => $permission->name)
            ->contains($this->makePermissionName($permissionString));
    }

    /**
     * Alias for hasPermission.
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
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has all of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ( ! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all permissions for the user as a collection.
     * Uses request-level cache to avoid multiple DB queries within the same request.
     */
    public function getAllPermissions(): Collection
    {
        return $this->getCachedPermissions();
    }

    /**
     * Clear the request-level permissions cache.
     * Should be called after adding/removing/syncing permissions.
     */
    public function flushPermissionsCache(): void
    {
        $this->cachedPermissions = null;
    }

    /**
     * Get cached permissions or load them from database.
     * This ensures permissions are only queried once per request.
     */
    protected function getCachedPermissions(): Collection
    {
        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = $this->allPermissions();
        }

        return $this->cachedPermissions;
    }

    private function makePermissionName($permissionString): string
    {
        return mb_strtolower(str_replace([' ', '-'], '_', $permissionString));
    }

    /**
     * Verify that permission(s) exist in the database.
     *
     * @param  string|array<string>  $searchablePermission
     * @return Permission|Collection<int, Permission>
     *
     * @throws RuntimeException If any permission is not synced with the database.
     */
    private function verifyPermissionInDatabase(string|array $searchablePermission): Permission|Collection
    {
        // Handle single permission
        if (is_string($searchablePermission)) {
            $permission = Permission::query()->where('name', '=', $searchablePermission)->first();

            if ( ! $permission) {
                throw new RuntimeException(
                    "Permission '{$searchablePermission}' is not synced with the database. Please run \"php artisan sync:permissions\" first."
                );
            }

            return $permission;
        }

        // Handle multiple permissions (bulk)
        $permissions = Permission::query()->whereIn('name', $searchablePermission)->get();

        // Verify all permissions were found
        $foundNames = $permissions->pluck('name')->all();
        $missingPermissions = array_diff($searchablePermission, $foundNames);

        if (count($missingPermissions) > 0) {
            $missingList = implode(', ', $missingPermissions);
            throw new RuntimeException(
                "Permissions [{$missingList}] are not synced with the database. Please run \"php artisan sync:permissions\" first."
            );
        }

        return $permissions;
    }
}
