<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Techieni3\LaravelUserPermissions\Models\Permission;

/**
 * PermissionsSynced Event
 *
 * Dispatched when permissions are synced with a model.
 * Provides complete context about the sync operation including what was added and removed.
 */
class PermissionsSynced
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  Collection<int, Permission>  $synced
     * @param  Collection<int, Permission>  $previous
     * @param  Collection<int, Permission>  $attached
     * @param  Collection<int, Permission>  $detached
     */
    public function __construct(
        public Model $model,
        public Collection $synced,
        public Collection $previous,
        public Collection $attached,
        public Collection $detached
    ) {}
}
