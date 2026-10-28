<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\AuthTokenExpiration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request
    ): JsonResponse {
        return DB::transaction(
            function () use ($request): JsonResponse {
                $user = User::create(
                    $request->validated()
                );

                $issuedToken = $user->createToken(
                    'auth-token',
                    ['*'],
                    AuthTokenExpiration::expiresAt()
                );

                return response()->json([
                    'message' => 'Registration successful.',
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
                ], 201);
            }
        );
    }
}
