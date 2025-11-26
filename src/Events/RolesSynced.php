<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Techieni3\LaravelUserPermissions\Models\Role;

/**
 * RolesSynced Event
 *
 * Dispatched when roles are synced with a model.
 * Provides complete context about the sync operation including what was added and removed.
 */
class RolesSynced
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  Collection<int, Role>  $synced
     * @param  Collection<int, Role>  $previous
     * @param  Collection<int, Role>  $attached
     * @param  Collection<int, Role>  $detached
     */
    public function __construct(
        public Model $model,
        public Collection $synced,
        public Collection $previous,
        public Collection $attached,
        public Collection $detached
    ) {}
}
