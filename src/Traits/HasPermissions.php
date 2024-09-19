<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Techieni3\LaravelUserPermissions\Models\Permission;

trait HasPermissions
{
    /**
     * Add a permission to the user.
     *
     * @throws RuntimeException If the permission is already assigned, or permission is not synced with the database.
     */
    public function addPermission(string $permission): void
    {
        $searchablePermission = $this->makePermissionName($permission);

        // Check if the user already has the permission
        if ($this->hasPermission($permission)) {
            throw new RuntimeException('Permission is already assigned to the user.');
        }

        // Get permission from the database
        $dbPermission = $this->verifyPermissionInDatabase($searchablePermission);

        // Attach the permission to the user
        $this->permissions()->attach($dbPermission);
    }

    /**
     * Convert a string permission to a BackedEnum instance.
     *
     * @throws RuntimeException If the enum file is missing or the permission is invalid.
     */

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
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permissionString): bool
    {
        $this->loadMissing('permissions');

        return $this->permissions
            ?->map(fn (Permission $permission) => $permission->name)
            ?->contains($this->makePermissionName($permissionString));
    }

    /**
     * Alias for hasPermission.
     */
    public function hasPermissionTo(string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    /**
     * Get all permissions for the user as a collection.
     */
    public function getAllPermissions()
    {
        return $this->permissions()->get();
    }

    private function makePermissionName($permissionString): string
    {
        return mb_strtolower(str_replace([' ', '-'], '_', $permissionString));
    }

    private function verifyPermissionInDatabase(string $searchablePermission): Permission
    {
        $permission = Permission::where('name', $searchablePermission)->first();

        if ( ! $permission) {
            throw new RuntimeException(
                'Permissions are not synced with the database. Please run "php artisan sync:permissions" first.'
            );
        }

        return $permission;
    }
}
