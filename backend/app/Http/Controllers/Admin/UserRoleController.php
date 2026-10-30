<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * Assign a role to the specified user.
     */
    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        $roleSlug = (string) $request->validated('role');

        $user->assignRole($roleSlug);

        $user->load('roles:id,name,slug');

        return response()->json([
            'message' => 'User role assigned successfully.',
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
            ],
        ]);
    }

    /**
     * Remove a role from the specified user.
     */
    public function destroy(Request $request, User $user, string $roleSlug): JsonResponse
    {
        $role = Role::query()->where('slug', $roleSlug)->first();

        if (! $role) {
            return response()->json([
                'message' => 'Role not found.',
            ], 404);
        }

        $currentUser = $request->user();

        if ($roleSlug === 'admin' && $currentUser && (int) $currentUser->getKey() === (int) $user->getKey()) {
            return response()->json([
                'message' => 'You cannot remove your own admin role.',
            ], 409);
        }

        if ($roleSlug === 'admin' && $user->is_active && $user->hasRole('admin')) {
            $activeAdminCount = User::query()
                ->where('is_active', true)
                ->whereHas('roles', static function ($query): void {
                    $query->where('slug', 'admin');
                })
                ->count();

            if ($activeAdminCount <= 1) {
                return response()->json([
                    'message' => 'Cannot remove the admin role from the last active administrator.',
                ], 409);
            }
        }

        $user->removeRole($role);

        $user->load('roles:id,name,slug');

        return response()->json([
            'message' => 'User role removed successfully.',
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
            ],
        ]);
    }
}
