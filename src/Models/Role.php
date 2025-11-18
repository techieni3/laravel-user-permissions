<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Role extends Model
{
    protected $guarded = ['id'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'roles_permissions', 'role_id', 'permission_id');
    }

    protected function casts(): array
    {
        if (class_exists(config('permissions.role_enum'))) {
            return [
                'name' => config('permissions.role_enum'),
            ];
        }

        return [];
    }
}
