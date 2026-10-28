<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'authentication.token_expiration_days' => 30,
        ]);
    }

    public function test_registration_issues_a_token_with_a_30_day_expiration(): void
    {
        $this->travelTo(now()->startOfSecond());

        $expectedExpiresAt = now()->addDays(30);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertCreated()
            ->assertJsonPath(
                'data.expires_at',
                $expectedExpiresAt->toISOString()
            )
            ->assertJsonPath('data.token_type', 'Bearer');

        $token = PersonalAccessToken::query()->sole();

        $this->assertSame(
            $expectedExpiresAt->timestamp,
            $token->expires_at->timestamp
        );
    }

    public function test_login_issues_a_token_with_a_30_day_expiration(): void
    {
        $this->travelTo(now()->startOfSecond());

        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'StrongPass123',
        ]);

        $expectedExpiresAt = now()->addDays(30);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'StrongPass123',
        ]);

        $response->assertOk()
            ->assertJsonPath(
                'data.expires_at',
                $expectedExpiresAt->toISOString()
            )
            ->assertJsonPath('data.token_type', 'Bearer');

        $token = PersonalAccessToken::query()->sole();

        $this->assertSame(
            $expectedExpiresAt->timestamp,
            $token->expires_at->timestamp
        );
    }

    public function test_expired_token_is_rejected_by_protected_routes(): void
    {
        $this->travelTo(now()->startOfSecond());

        $user = User::factory()->create();

        $expiresAt = now()->addDays(30);

        $issuedToken = $user->createToken(
            'expiration-test',
            ['*'],
            $expiresAt
        );

        $this->withToken($issuedToken->plainTextToken)
            ->getJson('/api/me')
            ->assertOk();

        // Move the test clock beyond the token's expiry.
        $this->travelTo($expiresAt->copy()->addSecond());

        // Ensure the next request authenticates the token again.
        Auth::forgetGuards();

        $this->withToken($issuedToken->plainTextToken)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_prune_command_only_removes_tokens_expired_for_at_least_24_hours(): void
    {
        $this->travelTo(now()->startOfSecond());

        $user = User::factory()->create();

        $expiredMoreThan24HoursAgo = $user->createToken(
            'expired-old-token',
            ['*'],
            now()->subHours(25)
        );

        $expiredLessThan24HoursAgo = $user->createToken(
            'expired-recent-token',
            ['*'],
            now()->subHours(12)
        );

        $validToken = $user->createToken(
            'valid-token',
            ['*'],
            now()->addDays(30)
        );

        $this->artisan('sanctum:prune-expired', [
            '--hours' => 24,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $expiredMoreThan24HoursAgo->accessToken->getKey(),
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $expiredLessThan24HoursAgo->accessToken->getKey(),
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $validToken->accessToken->getKey(),
        ]);
    }
}
