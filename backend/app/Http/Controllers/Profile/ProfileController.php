<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{
    /**
     * Retrieve the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->profileResponse(
            $user,
            'Profile retrieved successfully.',
        );
    }

    /**
     * Update the authenticated user's editable profile fields.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill($request->validated());
        $user->save();

        return $this->profileResponse(
            $user->refresh(),
            'Profile updated successfully.',
        );
    }

    /**
     * Change the authenticated user's password and revoke other API tokens.
     */
    public function changePassword(
        ChangePasswordRequest $request
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $currentAccessToken = $user->currentAccessToken();

        if (! ($currentAccessToken instanceof PersonalAccessToken)) {
            return response()->json([
                'message' => 'Password change requires a Bearer access token.',
            ], 401);
        }

        $validated = $request->validated();

        DB::transaction(function () use (
            $user,
            $currentAccessToken,
            $validated,
        ): void {
            // The User model's "hashed" cast hashes the new password.
            $user->forceFill([
                'password' => $validated['password'],
            ])->save();

            // Keep the token used for this request; revoke every other device token.
            $user->tokens()
                ->whereKeyNot($currentAccessToken->getKey())
                ->delete();
        });

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    private function profileResponse(
        User $user,
        string $message
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ],
        ]);
    }
}
