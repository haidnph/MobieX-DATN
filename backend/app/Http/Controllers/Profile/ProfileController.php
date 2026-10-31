<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
