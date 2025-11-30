<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;

/**
 * RolesAndPermissionsDeleted Event
 *
 * Dispatched when a model's roles and permissions are cascade deleted.
 * This event is triggered by the CascadeDeletesRolesAndPermissions trait.
 */
class RolesAndPermissionsDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  Collection<int, Role>  $roles
     * @param  Collection<int, Permission>  $permissions
     */
    public function __construct(
        public Model $model,
        public Collection $roles,
        public Collection $permissions
    ) {}
}
