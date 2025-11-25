<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class UserController
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var class-string<Model> $userModel */
        $userModel = Config::string('permissions.classes.user');
        $displayColumn = Config::string('permissions.dashboard.user_display_column', 'name');

        $query = $userModel::query()
            ->with(['roles:id,display_name'])
            ->withCount(['directPermissions'])
            ->select(['id', $displayColumn]);

        // Apply search filter if provided
        if ($request->has('search') && $search = $request->input('search')) {
            $query->whereLike($displayColumn, '%'.$search.'%');
        }

        $users = $query
            ->latest()
            ->paginate(50);

        $users
            ->getCollection()
            ->transform(static fn (Model $user): array => [
                'id' => $user->id,
                'name' => $user->{$displayColumn},
                'roles' => $user->roles
                    ->map(
                        static fn ($role): array => [
                            'name' => $role->display_name,
                        ],
                    )
                    ->toArray(),
                'permissions_count' => $user->direct_permissions_count ?? 0,
            ]);

        return response()->json($users);
    }
}
