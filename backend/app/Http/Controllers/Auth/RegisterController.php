<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
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

                $token = $user
                    ->createToken('auth-token')
                    ->plainTextToken;

                return response()->json([
                    'message' => 'Registration successful.',
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
                ], 201);
            }
        );
    }
}
