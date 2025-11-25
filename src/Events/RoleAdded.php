<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Techieni3\LaravelUserPermissions\Models\Role;

/**
 * RoleAdded Event
 *
 * Dispatched when a role is added to a model.
 */
class RoleAdded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Model $model, public Role $role) {}
}
