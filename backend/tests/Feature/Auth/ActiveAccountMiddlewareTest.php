<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ActiveAccountMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_access_current_user_endpoint(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $token = $user->createToken(
            'active-account-test',
            ['*'],
            now()->addDays(30)
        );

        $this->withToken($token->plainTextToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $user->getKey()
            );
    }

    public function test_inactive_user_cannot_access_current_user_with_an_existing_token(): void
    {
        $user = User::factory()->create();

        // is_active is deliberately not mass assignable.
        // Change it explicitly in the test to simulate an inactive account.
        $user->forceFill([
            'is_active' => false,
        ])->save();

        $token = $user->createToken(
            'inactive-account-test',
            ['*'],
            now()->addDays(30)
        );

        $this->withToken($token->plainTextToken)
            ->getJson('/api/me')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'This account is inactive.'
            );

        // The inactive-account check must not delete the token itself.
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->getKey(),
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'StrongPass123',
        ]);

        $user->forceFill([
            'is_active' => false,
        ])->save();

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'StrongPass123',
        ])->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_inactive_user_can_logout_and_revoke_current_token(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_active' => false,
        ])->save();

        $token = $user->createToken(
            'inactive-logout-test',
            ['*'],
            now()->addDays(30)
        );

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Logout successful.'
            );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            ['id' => $token->accessToken->getKey()]
        );

        // Reset cached authentication guards before the next request.
        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
