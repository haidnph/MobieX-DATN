<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials_and_receive_a_bearer_token(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0912345678',
            'password' => 'StrongPass123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'StrongPass123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Test Customer')
            ->assertJsonPath('data.user.email', 'customer@example.com')
            ->assertJsonPath('data.user.phone', '0912345678')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                    ],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'name' => 'auth-token',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_when_password_is_incorrect(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'StrongPass123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'WrongPass123',
        ])->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'The provided credentials are incorrect.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_when_email_does_not_exist(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'StrongPass123',
        ])->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'The provided credentials are incorrect.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'StrongPass123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
