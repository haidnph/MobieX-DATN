<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthRateLimitKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const FAILED_ATTEMPTS_DECAY_SECONDS = 300;

    public function __invoke(
        LoginRequest $request
    ): JsonResponse {
        $credentials = $request->validated();

        $failedAttemptsKey = AuthRateLimitKey::forEmail(
            'auth:login:failed',
            $credentials['email']
        );

        // Block further attempts after five failures.
        if (RateLimiter::tooManyAttempts(
            $failedAttemptsKey,
            self::MAX_FAILED_ATTEMPTS
        )) {
            return response()->json([
                'message' =>
                'Too many login attempts. Please try again later.',
            ], 429)->header(
                'Retry-After',
                (string) RateLimiter::availableIn(
                    $failedAttemptsKey
                )
            );
        }

        $user = User::where(
            'email',
            $credentials['email']
        )->first();

        /*
         * Invalid credentials and inactive accounts use the same
         * response to avoid revealing account status.
         */
        if (
            ! $user
            || ! Hash::check(
                $credentials['password'],
                $user->password
            )
            || ! $user->is_active
        ) {
            RateLimiter::hit(
                $failedAttemptsKey,
                self::FAILED_ATTEMPTS_DECAY_SECONDS
            );

            return response()->json([
                'message' =>
                'The provided credentials are incorrect.',
            ], 401);
        }

        // A successful login clears only the account failure counter.
        // The independent IP-based request limit remains in effect.
        RateLimiter::clear($failedAttemptsKey);

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
