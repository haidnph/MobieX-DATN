<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLink(
        ForgotPasswordRequest $request
    ): JsonResponse {
        Password::sendResetLink($request->validated());

        // Avoid revealing whether an email exists.
        return response()->json([
            'message' => 'If the email address exists, a password reset link will be sent.',
        ]);
    }

    public function reset(
        ResetPasswordRequest $request
    ): JsonResponse {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                DB::transaction(function () use (
                    $user,
                    $password
                ): void {
                    // The User model's "hashed" cast hashes the password.
                    $user->forceFill([
                        'password' => $password,
                    ])->setRememberToken(Str::random(60));

                    $user->save();

                    // Revoke existing API tokens after password reset.
                    $user->tokens()->delete();
                });

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successful.',
            ]);
        }

        return response()->json([
            'message' => 'The password reset token is invalid or has expired.',
        ], 422);
    }
}
