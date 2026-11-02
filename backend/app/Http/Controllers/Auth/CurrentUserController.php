<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->roles()
            ->orderBy('roles.slug')
            ->get(['roles.id', 'roles.name', 'roles.slug']);

        return response()->json([
            'message' => 'Current user retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'roles' => $roles
                        ->map(static fn (Role $role): array => [
                            'slug' => $role->slug,
                            'name' => $role->name,
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }
}
