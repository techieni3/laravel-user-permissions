<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role Model.
 *
 * Represents a user role that groups multiple permissions together.
 * Roles can be assigned to users to grant them a set of permissions.
 *
 * @property int $id The unique identifier for the role
 * @property string $name The unique name of the role (lowercase)
 * @property string $display_name The human-readable display name
 * @property Carbon $created_at Timestamp when the role was created
 * @property Carbon $updated_at Timestamp when the role was last updated
 */
class Role extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = ['id'];

    /**
     * Get the permissions associated with this role.
     *
     * @return BelongsToMany<Permission>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Permission::class,
            table: 'roles_permissions',
            foreignPivotKey: 'role_id',
            relatedPivotKey: 'permission_id'
        );
    }

    /**
     * Get the attributes that should be cast.
     * Casts the name attribute to the configured role enum if it exists.
     *
     * @return array<string, string>
     */
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
