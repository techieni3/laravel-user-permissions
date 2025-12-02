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
use Techieni3\LaravelUserPermissions\Events\PermissionAdded;
use Techieni3\LaravelUserPermissions\Events\PermissionRemoved;
use Techieni3\LaravelUserPermissions\Events\PermissionsSynced;
use Techieni3\LaravelUserPermissions\Exceptions\PermissionException;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Throwable;

/**
 * HasPermissions Trait
 *
 * Provides permission management with caching, bulk operations, and role integration.
 * Can be used independently or through HasRoles trait.
 */
trait HasPermissions
{
    /**
     * Request-level permission cache.
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
     * Get all permissions for the user, direct and those granted through roles (deduplicated).
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
     *
     * @param  Builder<Model>  $query
     * @param  string|array<string>  $permissions
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
     *
     * @throws PermissionException If invalid, already assigned, or granted via role
     */
    public function addPermission(string $permission): static
    {
        $permissionName = $this->normalizePermissionName($permission);

        // Get permission from the database
        $dbPermission = $this->findPermissionOrFail($permissionName);

        // Check if the user already has the permission
        if ($this->hasPermission($permission)) {
            throw PermissionException::alreadyAssigned($permissionName);
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
     *
     * @throws PermissionException If not found or not assigned
     */
    public function removePermission(string $permission): static
    {
        $searchablePermission = $this->normalizePermissionName($permission);

        // Get permission from the database
        $dbPermission = $this->findPermissionOrFail($searchablePermission);

        // Detach the permission from the user
        $detached = $this->directPermissions()->detach($dbPermission->id);

        if ($detached === 0) {
            throw PermissionException::notAssigned($searchablePermission);
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
     * Sync permissions with the user.
     * Excludes permissions already granted via roles to avoid duplicates.
     *
     * @param  array<string>  $permissions
     *
     * @throws PermissionException|Throwable If any permission is not synced with the database
     */
    public function syncPermissions(array $permissions): static
    {
        // Capture previous direct permissions before sync (if events enabled)
        $previousPermissions = Config::boolean('permissions.events_enabled', false)
            ? $this->directPermissions()->get()
            : collect();

        // Normalize all permission names
        $searchablePermissions = array_map($this->normalizePermissionName(...), $permissions);

        // Verify all permissions in a single query
        /** @var \Illuminate\Database\Eloquent\Collection<int, Permission> $dbPermissions */
        $dbPermissions = $this->findPermissionOrFail($searchablePermissions);
        $permissionIds = $dbPermissions->pluck('id');

        // Get permission IDs already granted via roles to avoid duplicates
        $rolePermissionIds = $this->getRolePermissionIds();

        $permissionIdsToSync = $permissionIds->diff($rolePermissionIds)->all();

        // Store synced permissions for event (only those actually synced)
        $syncedPermissions = $dbPermissions->filter(
            static fn (Permission $permission): bool => in_array($permission->id, $permissionIdsToSync, true)
        );

        DB::transaction(function () use ($permissionIdsToSync): void {
            // Detach all existing permissions
            $this->directPermissions()->detach();
            // Attach all new permissions
            if (count($permissionIdsToSync) > 0) {
                $this->directPermissions()->attach($permissionIdsToSync);
            }
        });

        DB::afterCommit(function () use ($previousPermissions, $syncedPermissions): void {
            // Dispatch event if events are enabled
            if (Config::boolean('permissions.events_enabled', false)) {
                $attached = $syncedPermissions->diff($previousPermissions);
                $detached = $previousPermissions->diff($syncedPermissions);

                event(new PermissionsSynced(
                    model: $this,
                    synced: $syncedPermissions,
                    previous: $previousPermissions,
                    attached: $attached,
                    detached: $detached
                ));
            }

            // Clear cached permissions
            $this->clearPermissionsCache();
        });

        return $this;
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->getPermissions()
            ->map(static fn (Permission $permission) => $permission->name)
            ->contains($this->normalizePermissionName($permission));
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
     * Clear permission cache.
     */
    private function clearPermissionsCache(): void
    {
        $this->permissionsCache = null;
    }

    /**
     * Normalize to lowercase with dots (spaces/hyphens → dots).
     */
    private function normalizePermissionName(string $permissionString): string
    {
        $normalized = mb_strtolower(str_replace(' ', '.', $permissionString));

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
     *
     * @return array<int, mixed>
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
     * Retrieve one or multiple permissions from the database.
     *
     * @param  string|array<string>  $permissionName
     * @return Permission|Collection<int, Permission>
     *
     * @throws PermissionException If one or more permissions are not found
     */
    private function findPermissionOrFail(string|array $permissionName): Permission|Collection
    {
        // Handle single permission
        if (is_string($permissionName)) {
            $permission = Permission::query()
                ->select(['id', 'name'])
                ->where('name', '=', $permissionName)
                ->first();

            if ( ! $permission) {
                throw PermissionException::notFound($permissionName);
            }

            return $permission;
        }

        // Handle multiple permissions (bulk)
        $permissions = Permission::query()
            ->select(['id', 'name'])
            ->whereIn('name', $permissionName)
            ->get();

        // Verify all permissions were found
        $foundNames = $permissions->pluck('name')->all();
        $missingPermissions = array_diff($permissionName, $foundNames);

        if ($missingPermissions !== []) {
            throw PermissionException::notFound($missingPermissions);
        }

        return $permissions;
    }
}
