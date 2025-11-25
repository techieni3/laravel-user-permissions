<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Throwable;

class RolePermissionController
{
    /**
     * Get a role with all permissions for editing.
     */
    public function show(Role $role): JsonResponse
    {
        // Get role's current permissions
        $rolePermissionIds = $role
            ->permissions()
            ->pluck('permissions.id')
            ->toArray();

        // Get all permissions from DB and group by model
        /** @var Collection<Permission> $permissions */
        $permissions = Permission::query()
            ->select(['id', 'name'])
            ->get();

        $groupedPermissions = [];
        $models = [];

        foreach ($permissions as $permission) {
            // Split permission name to extract action and model
            // Format: model-name.action (e.g., user.view, post.create)
            $parts = explode('.', $permission->name);

            if (count($parts) >= 2) {
                // The last part is the model name, everything before is the action
                $model = ucfirst(array_shift($parts));
                $action = implode(' ', $parts);
            } else {
                // Fallback for permissions without underscore
                $model = 'Other';
                $action = $permission->name;
            }

            if ( ! isset($groupedPermissions[$model])) {
                $groupedPermissions[$model] = [];
                $models[] = $model;
            }

            $groupedPermissions[$model][] = [
                'action' => ucwords($action),
                'permission_id' => $permission->id,
                'assigned' => in_array($permission->id, $rolePermissionIds, true),
            ];
        }

        // Sort models alphabetically
        sort($models);

        // Sort permissions within each model by action
        foreach ($groupedPermissions as $model => $modelPermissions) {
            usort(
                $modelPermissions,
                static fn (array $a, array $b): int => strcmp($a['action'], $b['action']),
            );
            $groupedPermissions[$model] = $modelPermissions;
        }

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->display_name,
            ],
            'models' => $models,
            'available_permissions' => $groupedPermissions,
        ]);
    }

    /**
     * Update role permissions.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        try {
            $validated = $request->validate([
                'permissions' => ['array'],
                'permissions.*' => 'integer',
            ]);

            $permissions = $validated['permissions'] ?? [];

            if ($permissions !== []) {
                $availablePermissions = Permission::query()
                    ->whereIntegerInRaw('id', $permissions)
                    ->pluck('id')
                    ->toArray();

                if (count($availablePermissions) !== count($permissions)) {
                    throw ValidationException::withMessages(['permissions' => 'Invalid permission IDs provided.']);
                }
            }

            DB::transaction(static function () use ($role, $permissions): void {
                $role->permissions()->detach();

                if ($permissions !== []) {
                    $role->permissions()->attach($permissions);
                }
            });

            return response()->json([
                'message' => 'Permissions updated successfully',
            ]);
        } catch (ValidationException) {
            return response()->json(
                [
                    'message' => 'Validation failed',
                ],
                422,
            );
        } catch (Throwable) {
            return response()->json(
                [
                    'message' => 'Failed to update permissions',
                ],
                500,
            );
        }
    }
}
