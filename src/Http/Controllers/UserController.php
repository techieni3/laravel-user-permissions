<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of users with their roles and permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $userModel = Config::get('auth.providers.users.model', 'App\\Models\\User');

        $users = $userModel::query()
            ->with(['roles', 'directPermissions'])
            ->when($request->get('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return response()->json($users);
    }

    /**
     * Update user roles.
     */
    public function updateRoles(Request $request, int $userId): JsonResponse
    {
        $userModel = Config::get('auth.providers.users.model', 'App\\Models\\User');

        $validator = Validator::make($request->all(), [
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $userModel::findOrFail($userId);

        // Get role names from IDs
        $roles = \Techieni3\LaravelUserPermissions\Models\Role::whereIn('id', $request->roles)
            ->pluck('name')
            ->toArray();

        $user->syncRoles($roles);

        return response()->json($user->load('roles'));
    }

    /**
     * Update user direct permissions.
     */
    public function updatePermissions(Request $request, int $userId): JsonResponse
    {
        $userModel = Config::get('auth.providers.users.model', 'App\\Models\\User');

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $userModel::findOrFail($userId);

        // Get permission names from IDs
        $permissions = \Techieni3\LaravelUserPermissions\Models\Permission::whereIn('id', $request->permissions)
            ->pluck('name')
            ->toArray();

        $user->syncPermissions($permissions);

        return response()->json($user->load('directPermissions'));
    }
}
