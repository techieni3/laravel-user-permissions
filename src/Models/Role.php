<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Config;

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
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        $userModel = Config::get('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsToMany(
            related: $userModel,
            table: 'users_roles',
            foreignPivotKey: 'role_id',
            relatedPivotKey: 'user_id'
        );
    }

    protected function casts(): array
    {
        if (class_exists(\App\Enums\Role::class)) {
            return [
                'name' => \App\Enums\Role::class,
            ];
        }

        return [];
    }
}
