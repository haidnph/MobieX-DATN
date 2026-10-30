<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

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
}
