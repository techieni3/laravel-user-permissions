<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Techieni3\LaravelUserPermissions\Events\RolesAndPermissionsDeleted;

/**
 * CascadeDeletesRolesAndPermissions Trait
 *
 * Automatically detaches roles and permissions when a model is deleted.
 * Supports both hard deletes and soft deletes (forceDelete).
 */
trait CascadeDeletesRolesAndPermissions
{
    /**
     * Boot the cascade deletes trait.
     */
    public static function bootCascadeDeletesRolesAndPermissions(): void
    {
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive(static::class), true);

        if ($usesSoftDeletes) {
            static::forceDeleting(function (Model $model): void {
                static::performCascadeDelete($model);
            });
        } else {
            static::deleting(function (Model $model): void {
                static::performCascadeDelete($model);
            });
        }
    }

    /**
     * Detach roles and permissions from the model.
     */
    protected static function performCascadeDelete(Model $model): void
    {
        $isEventsEnabled = Config::boolean('permissions.events_enabled', false);

        $deletedRoles = $isEventsEnabled ? $model->roles()->get() : collect();

        $deletedPermissions = $isEventsEnabled ? $model->directPermissions()->get() : collect();

        $model->roles()->detach();
        $model->directPermissions()->detach();

        if ($isEventsEnabled) {
            event(new RolesAndPermissionsDeleted(
                model: $model,
                roles: $deletedRoles,
                permissions: $deletedPermissions
            ));
        }
    }
}
