<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_notification_with_frontend_url(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonPath(
                'message',
                'If the email address exists, a password reset link will be sent.'
            );

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (
                ResetPasswordNotification $notification
            ) use ($user): bool {
                $mailMessage = $notification->toMail($user);
                $actionUrl = $mailMessage->actionUrl;

                $this->assertStringStartsWith(
                    rtrim(
                        (string) config('app.frontend_url'),
                        '/'
                    ).'/reset-password?',
                    $actionUrl
                );

                $query = [];

                parse_str(
                    (string) parse_url($actionUrl, PHP_URL_QUERY),
                    $query
                );

                $this->assertNotEmpty($query['token'] ?? null);

                $this->assertSame(
                    $user->email,
                    $query['email'] ?? null
                );

                return true;
            }
        );

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_forgot_password_returns_same_message_for_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertOk()
            ->assertJsonPath(
                'message',
                'If the email address exists, a password reset link will be sent.'
            );

        Notification::assertNothingSent();

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_forgot_password_validates_email(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'OldPassword123',
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The password reset token is invalid or has expired.'
            );

        $this->assertTrue(
            Hash::check('OldPassword123', $user->fresh()->password)
        );
    }

    public function test_reset_password_rejects_expired_token(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'OldPassword123',
        ]);

        $token = Password::createToken($user);

        $expiredAt = now()->subMinutes(
            (int) config('auth.passwords.users.expire') + 1
        );

        DB::table(config('auth.passwords.users.table'))
            ->where('email', $user->email)
            ->update([
                'created_at' => $expiredAt,
            ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The password reset token is invalid or has expired.'
            );

        $this->assertTrue(
            Hash::check('OldPassword123', $user->fresh()->password)
        );
    }

    public function test_reset_password_changes_password_revokes_tokens_and_allows_login_with_new_password(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'OldPassword123',
        ]);

        $user->createToken('desktop-device');
        $user->createToken('mobile-device');

        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk()
            ->assertJsonPath(
                'message',
                'Password reset successful.'
            );

        $this->assertTrue(
            Hash::check('NewPassword123', $user->fresh()->password)
        );

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // A reset token must only work once.
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'AnotherPassword123',
            'password_confirmation' => 'AnotherPassword123',
        ])->assertUnprocessable();

        // The old password must no longer work.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'OldPassword123',
        ])->assertUnauthorized();

        // The new password must work with the existing login endpoint.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'NewPassword123',
        ])->assertOk()
            ->assertJsonPath('message', 'Login successful.');
    }

    public function test_reset_password_validates_password_confirmation(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'email' => 'customer@example.com',
            'token' => 'any-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'DifferentPassword123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
