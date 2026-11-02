<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserStatusController extends Controller
{
    /**
     * Update the active status of the specified user.
     */
    public function update(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $newStatus = $request->boolean('is_active');

        if (! $newStatus) {
            $currentUser = $request->user();

            if ($currentUser && (int) $currentUser->getKey() === (int) $user->getKey()) {
                return response()->json([
                    'message' => 'You cannot deactivate your own account.',
                ], 409);
            }

            if ($user->is_active && $user->hasRole('admin')) {
                $activeAdminCount = User::query()
                    ->where('is_active', true)
                    ->whereHas('roles', static function ($query): void {
                        $query->where('slug', 'admin');
                    })
                    ->count();

                if ($activeAdminCount <= 1) {
                    return response()->json([
                        'message' => 'Cannot deactivate the last active administrator.',
                    ], 409);
                }
            }
        }

        $user->is_active = $newStatus;
        $user->save();

        $user->load('roles:id,name,slug');

        return response()->json([
            'message' => 'User status updated successfully.',
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
            ],
        ]);
    }
}
