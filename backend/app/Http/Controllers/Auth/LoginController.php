<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthRateLimitKey;
use App\Support\AuthTokenExpiration;
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

        // Enforce the failed-login limit per normalized email.
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
         * Keep the same response for invalid credentials and
         * inactive accounts to avoid revealing account status.
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

        // Clear failed attempts after a successful login.
        // The independent IP-based limit from Commit A remains active.
        RateLimiter::clear($failedAttemptsKey);

        $issuedToken = $user->createToken(
            'auth-token',
            ['*'],
            AuthTokenExpiration::expiresAt()
        );

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'token' => $issuedToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $issuedToken
                    ->accessToken
                    ->expires_at
                    ?->toISOString(),
            ],
        ], 200);
    }
}
