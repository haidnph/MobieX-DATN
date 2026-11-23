<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Permission;
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

    /**
     * Update permissions assigned to a role.
     */
    public function syncPermissions(Request $request, string $role): JsonResponse
    {
        $roleModel = is_numeric($role)
            ? Role::where('id', (int) $role)->first()
            : Role::where('slug', $role)->first();

        if (! $roleModel) {
            return response()->json([
                'message' => 'Vai trò không tồn tại trong hệ thống.',
            ], 404);
        }

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', 'exists:permissions,slug'],
        ], [
            'permissions.array' => 'Danh sách quyền phải là một mảng.',
        ]);

        $currentUser = $request->user();

        // Security Guard 1: Non-super-admin cannot modify the super-admin role
        if ($roleModel->slug === 'super-admin' && (! $currentUser || ! $currentUser->hasRole('super-admin'))) {
            return response()->json([
                'message' => 'Chỉ tài khoản Quản trị cấp cao (Super Admin) mới có thể chỉnh sửa quyền của vai trò này.',
            ], 403);
        }

        $inputSlugs = $validated['permissions'];

        // Security Guard 2: Super Admin role must retain critical permissions
        if ($roleModel->slug === 'super-admin') {
            $requiredCore = ['roles.manage', 'users.manage', 'dashboard.view'];
            $missingCore = array_diff($requiredCore, $inputSlugs);
            if (! empty($missingCore)) {
                return response()->json([
                    'message' => 'Không thể tước các quyền quản trị tối cao (roles.manage, users.manage, dashboard.view) khỏi vai trò Super Admin.',
                ], 422);
            }
        }

        // Resolve permission IDs from slugs
        $permissionIds = Permission::query()
            ->whereIn('slug', $inputSlugs)
            ->pluck('id')
            ->all();

        $roleModel->permissions()->sync($permissionIds);
        $roleModel->load(['permissions' => static fn ($q) => $q->orderBy('slug')]);

        return response()->json([
            'message' => "Cập nhật quyền hạn cho vai trò {$roleModel->name} thành công.",
            'data' => [
                'role' => RoleResource::make($roleModel)->resolve($request),
            ],
        ]);
    }
}
