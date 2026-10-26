<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __invoke(
        LoginRequest $request
    ): JsonResponse {
        $credentials = $request->validated();

        $user = User::where(
            'email',
            $credentials['email']
        )->first();

        /*
         * Proposed D-16 policy:
         * Only active accounts may log in.
         *
         * Return the same error for invalid credentials
         * and inactive accounts to avoid revealing account status.
         */
        if (
            ! $user
            || ! Hash::check(
                $credentials['password'],
                $user->password
            )
            || ! $user->is_active
        ) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $token = $user
            ->createToken('auth-token')
            ->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }
}
