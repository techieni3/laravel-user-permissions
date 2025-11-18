<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Techieni3\LaravelUserPermissions\Models\Permission;

trait HasPermissions
{
    /**
     * Request-level cache for user permissions.
     */
    protected ?Collection $cachedPermissions = null;

    /**
     * Get all permissions for the user, including those granted through roles.
     */
    public function permissions(): BelongsToMany
    {
        $directPermissions = $this->belongsToMany(
            related: Permission::class,
            table: 'users_permissions',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'permission_id'
        )
            ->select(['permissions.*'])
            ->withTimestamps();

        $rolePermissions = $this->belongsToMany(
            related: Permission::class,
            table: 'roles_permissions',
            foreignPivotKey: 'role_id',
            relatedPivotKey: 'permission_id'
        )
            ->join('users_roles', 'roles_permissions.role_id', '=', 'users_roles.role_id')
            ->where('users_roles.user_id', $this->id)
            ->select([
                'permissions.*',
                DB::raw('`users_roles`.`user_id` as `pivot_user_id`'),
                DB::raw('`roles_permissions`.`permission_id` AS `pivot_permission_id`'),
                DB::raw('`users_roles`.`created_at` AS `pivot_created_at`'),
                DB::raw('`users_roles`.`updated_at` AS `pivot_updated_at`'),
            ])
            ->withTimestamps();

        return $directPermissions->union($rolePermissions);
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
     *
     * @throws RuntimeException If the permission is already assigned, or permission is not synced with the database.
     */
    public function addPermission(string $permission): static
    {
        $searchablePermission = $this->makePermissionName($permission);

        // Check if the user already has the permission
        if ($this->hasPermission($permission)) {
            throw new RuntimeException('Permission is already assigned to the user.');
        }

        // Get permission from the database
        $dbPermission = $this->verifyPermissionInDatabase($searchablePermission);

        // Attach the permission to the user
        $this->directPermissions()->attach($dbPermission);

        // Clear cached permissions
        $this->flushPermissionsCache();

        return $this;
    }

    /**
     * Remove a permission from the user.
     *
     * @throws RuntimeException If the permission is not assigned to the user.
     */
    public function removePermission(string $permission): static
    {
        $searchablePermission = $this->makePermissionName($permission);

        // Check if the user has the permission
        if ( ! $this->hasPermission($permission)) {
            throw new RuntimeException('Permission is not assigned to the user.');
        }

        // Get permission from the database
        $dbPermission = $this->verifyPermissionInDatabase($searchablePermission);

        // Detach the permission from the user
        $this->directPermissions()->detach($dbPermission);

        // Clear cached permissions
        $this->flushPermissionsCache();

        return $this;
    }

    /**
     * Sync permissions with the user (removes all existing direct permissions and adds new ones).
     *
     * @param  array<string>  $permissions
     *
     * @throws RuntimeException If any permission is not synced with the database.
     */
    public function syncPermissions(array $permissions): static
    {
        $permissionIds = [];

        foreach ($permissions as $permission) {
            $searchablePermission = $this->makePermissionName($permission);
            $dbPermission = $this->verifyPermissionInDatabase($searchablePermission);
            $permissionIds[] = $dbPermission->id;
        }

        $this->directPermissions()->sync($permissionIds);

        // Clear cached permissions
        $this->flushPermissionsCache();

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
            $this->cachedPermissions = $this->permissions()->get();
        }

        return $this->cachedPermissions;
    }

    private function makePermissionName($permissionString): string
    {
        return mb_strtolower(str_replace([' ', '-'], '_', $permissionString));
    }

    private function verifyPermissionInDatabase(string $searchablePermission): Permission
    {
        $permission = Permission::query()->where('name', '=', $searchablePermission)->first();

        if ( ! $permission) {
            throw new RuntimeException(
                'Permissions are not synced with the database. Please run "php artisan sync:permissions" first.'
            );
        }

        return $permission;
    }
}
