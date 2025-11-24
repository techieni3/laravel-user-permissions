<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission Model.
 *
 * Represents a permission that can be assigned to users directly
 * or through roles.
 *
 * @property int $id The unique identifier for the permission
 * @property string $name The unique name of the permission (lowercase with underscores)
 * @property string $display_name The human-readable display name
 * @property Carbon $created_at Timestamp when the permission was created
 * @property Carbon $updated_at Timestamp when the permission was last updated
 */
class Permission extends Model
{
    /**
     * The attributes that aren't mass-assignable.
     *
     * @var array<string>
     */
    protected $guarded = ['id'];

    /**
     * Get the roles that have this permission.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Role::class,
            table: 'roles_permissions',
            foreignPivotKey: 'permission_id',
            relatedPivotKey: 'role_id'
        );
    }
}
