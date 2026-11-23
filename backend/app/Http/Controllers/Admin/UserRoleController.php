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
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        if (! $request->user()->canAssignRole($role)) {
            return response()->json([
                'message' => 'You cannot assign a role with permissions you do not hold.',
            ], 403);
        }

        $user->assignRole($role);

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

        if ($currentUser && ! $currentUser->canManageUserAccount($user)) {
            return response()->json([
                'message' => 'Only a super-admin may modify a super-admin account.',
            ], 403);
        }

        if ($roleSlug === 'super-admin' && $currentUser && ! $currentUser->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Only a super-admin may remove the super-admin role.',
            ], 403);
        }

        if (in_array($roleSlug, ['admin', 'super-admin'], true) && $currentUser && (int) $currentUser->getKey() === (int) $user->getKey()) {
            return response()->json([
                'message' => 'You cannot remove your own admin role.',
            ], 409);
        }

        if (in_array($roleSlug, ['admin', 'super-admin'], true) && $user->is_active && $user->hasRole($roleSlug)) {
            $activeAdminCount = User::query()
                ->where('is_active', true)
                ->whereHas('roles', static function ($query) use ($roleSlug): void {
                    $query->where('slug', $roleSlug);
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
