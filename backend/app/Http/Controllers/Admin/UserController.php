<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->with('roles:id,name,slug');

        // Server-side search
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($role = $request->string('role')->trim()->value()) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $role));
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->value() === 'active');
        }

        // Date range filter
        if ($fromDate = $request->string('from_date')->trim()->value()) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = $request->string('to_date')->trim()->value()) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Sort
        $sortable = ['name', 'created_at', 'id'];
        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $order);

        $perPage = max(5, min(100, (int) $request->input('per_page', 15)));
        $users = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'blocked' => User::where('is_active', false)->count(),
        ];

        return UserResource::collection($users)->additional([
            'message' => 'Admin users retrieved successfully.',
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $user->load([
            'roles:id,name,slug',
            'orders' => static fn ($q) => $q->select('id', 'user_id', 'order_code', 'total_amount', 'status', 'created_at')->latest('id')->take(5),
        ]);

        return response()->json([
            'message' => 'Admin user retrieved successfully.',
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
                'permissions' => $user->getAllPermissions()->map(static fn ($p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'description' => $p->description,
                ])->values(),
                'recent_orders' => $user->orders->map(static fn ($o) => [
                    'id' => $o->id,
                    'order_code' => $o->order_code ?? "#{$o->id}",
                    'total_amount' => $o->total_amount,
                    'status' => $o->status,
                    'created_at' => $o->created_at?->toISOString(),
                ]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            'role' => ['nullable', 'string', 'exists:roles,slug'],
        ], [
            'name.required' => 'Họ tên không được để trống.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng bởi tài khoản khác.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải có tối thiểu 8 ký tự.',
            'role.exists' => 'Vai trò được chọn không tồn tại trong hệ thống.',
        ]);

        if (! empty($validated['role'])) {
            $role = Role::query()->where('slug', $validated['role'])->firstOrFail();

            if (! $request->user()->canAssignRole($role)) {
                return response()->json([
                    'message' => 'You cannot assign a role with permissions you do not hold.',
                ], 403);
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);
        $user->forceFill(['is_active' => true])->save();

        if (! empty($validated['role'])) {
            $user->assignRole($validated['role']);
        }

        $user->load('roles:id,name,slug');

        return response()->json([
            'message' => 'Tài khoản mới đã được tạo thành công.',
            'data' => ['user' => UserResource::make($user)->resolve($request)],
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->canManageUserAccount($user)) {
            return response()->json([
                'message' => 'Only a super-admin may modify a super-admin account.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', "unique:users,email,{$user->id}"],
            'phone' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'Họ tên không được để trống.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng bởi tài khoản khác.',
        ]);

        $user->update($validated);
        $user->load('roles:id,name,slug');

        return response()->json([
            'message' => 'Thông tin tài khoản đã được cập nhật thành công.',
            'data' => ['user' => UserResource::make($user)->resolve($request)],
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->canManageUserAccount($user)) {
            return response()->json([
                'message' => 'Only a super-admin may modify a super-admin account.',
            ], 403);
        }

        $validated = $request->validate([
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
        ], [
            'password.required' => 'Mật khẩu mới không được để trống.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường và số.',
        ]);

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Mật khẩu tài khoản đã được đặt lại thành công.',
        ]);
    }
}
