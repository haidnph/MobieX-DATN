<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_retrieve_current_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0912345678',
        ]);

        $token = $user->createToken('current-user-test');

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Current user retrieved successfully.'
            )
            ->assertJsonPath('data.user.id', $user->getKey())
            ->assertJsonPath('data.user.name', 'Test Customer')
            ->assertJsonPath(
                'data.user.email',
                'customer@example.com'
            )
            ->assertJsonPath(
                'data.user.phone',
                '0912345678'
            )
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                    ],
                ],
            ]);

        // Only explicitly approved user fields may be exposed.
        $this->assertSame(
            ['id', 'name', 'email', 'phone'],
            array_keys($response->json('data.user'))
        );

        $response->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertJsonMissingPath('data.token');

        // Reading the current user must not issue another token.
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_current_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_current_user_is_resolved_from_token_not_query_parameter(): void
    {
        $authenticatedUser = User::factory()->create([
            'email' => 'authenticated@example.com',
        ]);

        $otherUser = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $token = $authenticatedUser->createToken(
            'current-user-test'
        );

        $this->withToken($token->plainTextToken)
            ->getJson('/api/me?user_id='.$otherUser->getKey())
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $authenticatedUser->getKey()
            )
            ->assertJsonPath(
                'data.user.email',
                'authenticated@example.com'
            );
    }
}
