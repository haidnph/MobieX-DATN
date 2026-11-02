<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_change_password(): void
    {
        $this->patchJson('/api/profile/password', [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword12345',
            'password_confirmation' => 'NewPassword12345',
        ])->assertUnauthorized();
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);

        $currentToken = $user->createToken('current-device');
        $user->createToken('other-device');

        $this->withToken($currentToken->plainTextToken)
            ->patchJson('/api/profile/password', [
                'current_password' => 'WrongPassword123',
                'password' => 'NewPassword12345',
                'password_confirmation' => 'NewPassword12345',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(
            Hash::check('OldPassword123', $user->fresh()->password),
        );

        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_password_change_rejects_a_new_password_that_violates_policy(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);

        $currentToken = $user->createToken('current-device');
        $user->createToken('other-device');

        $this->withToken($currentToken->plainTextToken)
            ->patchJson('/api/profile/password', [
                'current_password' => 'OldPassword123',
                'password' => 'StrongPass1234', // 14 characters.
                'password_confirmation' => 'StrongPass1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(
            Hash::check('OldPassword123', $user->fresh()->password),
        );

        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_password_change_rejects_unsupported_fields(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);

        $token = $user->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile/password', [
                'current_password' => 'OldPassword123',
                'password' => 'NewPassword12345',
                'password_confirmation' => 'NewPassword12345',
                'user_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        $this->assertTrue(
            Hash::check('OldPassword123', $user->fresh()->password),
        );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_password_change_requires_matching_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);

        $token = $user->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile/password', [
                'current_password' => 'OldPassword123',
                'password' => 'NewPassword12345',
                'password_confirmation' => 'DifferentPassword12345',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(
            Hash::check('OldPassword123', $user->fresh()->password),
        );
    }

    public function test_password_change_updates_password_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'OldPassword123',
        ]);

        $currentToken = $user->createToken('current-device');
        $mobileToken = $user->createToken('mobile-device');
        $tabletToken = $user->createToken('tablet-device');

        $anotherUser = User::factory()->create();
        $anotherUserToken = $anotherUser->createToken('unrelated-device');

        $this->withToken($currentToken->plainTextToken)
            ->patchJson('/api/profile/password', [
                'current_password' => 'OldPassword123',
                'password' => 'NewPassword12345',
                'password_confirmation' => 'NewPassword12345',
            ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Password changed successfully.',
            );

        $this->assertTrue(
            Hash::check('NewPassword12345', $user->fresh()->password),
        );

        $this->assertFalse(
            Hash::check('OldPassword123', $user->fresh()->password),
        );

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->accessToken->getKey(),
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $mobileToken->accessToken->getKey(),
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tabletToken->accessToken->getKey(),
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $anotherUserToken->accessToken->getKey(),
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 2);

        // The token used for this request remains valid after the change.
        $this->withToken($currentToken->plainTextToken)
            ->getJson('/api/me')
            ->assertOk();
    }

    public function test_inactive_user_cannot_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);

        $user->forceFill([
            'is_active' => false,
        ])->save();

        $token = $user->createToken('inactive-user-device');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile/password', [
                'current_password' => 'OldPassword123',
                'password' => 'NewPassword12345',
                'password_confirmation' => 'NewPassword12345',
            ])
            ->assertForbidden();

        $this->assertTrue(
            Hash::check('OldPassword123', $user->fresh()->password),
        );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
