<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout_and_only_current_token_is_revoked(): void
    {
        // Arrange: create a user with two separate access tokens.
        $user = User::factory()->create();

        $currentToken = $user->createToken('auth-token');
        $otherToken = $user->createToken('other-device');

        // Act: logout using the current Bearer token.
        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        // Assert: the current token has been deleted.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->getKey(),
        ]);

        // Assert: the other device's token remains valid in the database.
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->getKey(),
        ]);

        // Reset cached authentication guards before the next request.
        Auth::forgetGuards();

        // Assert: the revoked token can no longer authenticate.
        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertUnauthorized();

        // An unauthenticated request must not create or revoke tokens.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
