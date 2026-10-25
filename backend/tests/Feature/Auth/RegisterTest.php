<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_bearer_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0912345678',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'is_active' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Registration successful.'
            )
            ->assertJsonPath(
                'data.user.name',
                'Test Customer'
            )
            ->assertJsonPath(
                'data.user.email',
                'customer@example.com'
            )
            ->assertJsonPath(
                'data.user.phone',
                '0912345678'
            )
            ->assertJsonPath(
                'data.token_type',
                'Bearer'
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
                    'token',
                    'token_type',
                ],
            ])
            ->assertJsonMissingPath('data.user.password');

        $user = User::where(
            'email',
            'customer@example.com'
        )->firstOrFail();

        $this->assertTrue(
            Hash::check(
                'StrongPass123',
                $user->password
            )
        );

        $this->assertTrue($user->is_active);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'customer@example.com',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => $user->getMorphClass(),
            'name' => 'auth-token',
        ]);

        $this->assertNotEmpty(
            $response->json('data.token')
        );
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Another Customer',
            'email' => 'existing@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_invalid_input(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);

        $this->assertDatabaseCount('users', 0);
    }
}
