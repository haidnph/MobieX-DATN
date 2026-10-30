<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Retrieve all system roles with their assigned permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with([
                'permissions' => static fn ($query) => $query
                    ->orderBy('permissions.slug'),
            ])
            ->orderBy('slug')
            ->get();

        return response()->json([
            'message' => 'Roles retrieved successfully.',
            'data' => [
                'roles' => RoleResource::collection($roles)
                    ->resolve($request),
            ],
        ]);
    }
}
