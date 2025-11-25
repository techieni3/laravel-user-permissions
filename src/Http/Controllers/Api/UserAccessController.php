<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Throwable;

class UserAccessController
{
    /**
     * Display user roles and available roles for assignment.
     */
    public function show(int $userId): JsonResponse
    {
        /** @var class-string<Model> $userModel */
        $userModel = Config::string('permissions.user_model');
        $displayColumn = Config::get('permissions.user_name_column', 'name');

        $user = $userModel::query()->findOrFail($userId);

        $allRoles = Role::query()
            ->with('permissions:id')
            ->select(['id', 'display_name'])
            ->get()
            ->map(
                static fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->display_name,
                    'permission_ids' => $role->permissions
                        ->pluck('id')
                        ->toArray(),
                ],
            );

        $allPermissions = Permission::query()
            ->select(['id', 'display_name'])
            ->get()
            ->map(
                static fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->display_name,
                ],
            );

        $userRoleIds = $user->roles()->pluck('roles.id')->toArray();
        $userPermissionIds = $user
            ->directPermissions()
            ->pluck('permissions.id')
            ->toArray();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->{$displayColumn},
            ],
            'roles' => $allRoles,
            'permissions' => $allPermissions,
            'user_role_ids' => $userRoleIds,
            'user_permission_ids' => $userPermissionIds,
        ]);
    }

    /**
     * Update user roles and permissions.
     */
    public function update(Request $request, int $userId): JsonResponse
    {
        try {
            /** @var class-string<Model> $userModel */
            $userModel = Config::string('permissions.user_model');
            $user = $userModel::query()->findOrFail($userId);

            $validated = $request->validate([
                'roles' => ['array'],
                'roles.*' => 'integer',
                'permissions' => ['array'],
                'permissions.*' => 'integer',
            ]);

            $roles = $validated['roles'] ?? [];
            $permissions = $validated['permissions'] ?? [];

            if ($roles !== []) {
                $availableRoles = Role::query()
                    ->whereIntegerInRaw('id', $roles)
                    ->pluck('id')
                    ->toArray();

                if (count($availableRoles) !== count($roles)) {
                    throw ValidationException::withMessages([
                        'roles' => 'Invalid role IDs provided.',
                    ]);
                }
            }

            if ($permissions !== []) {
                $availablePermissions = Permission::query()
                    ->whereIntegerInRaw('id', $permissions)
                    ->pluck('id')
                    ->toArray();

                if (count($availablePermissions) !== count($permissions)) {
                    throw ValidationException::withMessages([
                        'permissions' => 'Invalid permission IDs provided.',
                    ]);
                }
            }

            DB::transaction(static function () use (
                $user,
                $roles,
                $permissions,
            ): void {
                $user->roles()->detach();
                $user->directPermissions()->detach();

                if ($roles !== []) {
                    $user->roles()->attach($roles);
                }

                if ($permissions !== []) {
                    $user->directPermissions()->attach($permissions);
                }
            });

            return response()->json([
                'message' => 'Access updated successfully',
            ]);
        } catch (ValidationException) {
            return response()->json(
                [
                    'message' => 'Validation failed',
                ],
                422,
            );
        } catch (ModelNotFoundException) {
            return response()->json(
                [
                    'message' => 'User not found.',
                ],
                422,
            );
        } catch (Throwable) {
            return response()->json(
                [
                    'message' => 'Failed to update access',
                ],
                500,
            );
        }
    }
}
