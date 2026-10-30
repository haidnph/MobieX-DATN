<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Retrieve all registered system permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('slug')
            ->get();

        return response()->json([
            'message' => 'Permissions retrieved successfully.',
            'data' => [
                'permissions' => PermissionResource::collection($permissions)
                    ->resolve($request),
            ],
        ]);
    }
}
