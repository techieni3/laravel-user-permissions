<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Techieni3\LaravelUserPermissions\Models\Permission;

/**
 * PermissionAdded Event
 *
 * Dispatched when a permission is added to a model.
 */
class PermissionAdded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Model $model, public Permission $permission) {}
}
