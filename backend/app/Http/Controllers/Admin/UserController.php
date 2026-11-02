<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Retrieve a paginated list of users.
     */
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles:id,name,slug')
            ->orderByDesc('id')
            ->paginate(15);

        return UserResource::collection($users)->additional([
            'message' => 'Admin users retrieved successfully.',
        ]);
    }

    /**
     * Retrieve details of a specific user.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $user->load('roles:id,name,slug');

        return response()->json([
            'message' => 'Admin user retrieved successfully.',
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
            ],
        ]);
    }
}
