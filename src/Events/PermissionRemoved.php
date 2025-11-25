<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Techieni3\LaravelUserPermissions\Models\Permission;

/**
 * PermissionRemoved Event.
 *
 * Dispatched when a permission is removed from a model (typically a user).
 */
class PermissionRemoved
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Model $model, public Permission $permission) {}
}
