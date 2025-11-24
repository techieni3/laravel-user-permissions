<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Techieni3\LaravelUserPermissions\Models\Role;

class RoleController
{
    /**
     * Display a listing of roles.
     */
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->select(['id', 'display_name', 'updated_at'])
            ->withCount('permissions')
            ->get()
            ->map(
                static fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->display_name,
                    'updated_at' => $role->updated_at->toDateTimeString(),
                    'permissions_count' => $role->permissions_count ?? 0,
                ],
            );

        return response()->json(['data' => $roles]);
    }
}
