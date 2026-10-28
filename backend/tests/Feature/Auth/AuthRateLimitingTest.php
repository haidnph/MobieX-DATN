<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_throttled_by_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
            ])->postJson('/api/auth/register', [
                'name' => 'Test Customer '.$attempt,
                'email' => 'customer'.$attempt.'@example.com',
                'password' => 'StrongPass12345',
                'password_confirmation' => 'StrongPass12345',
            ])->assertCreated();
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
        ])->postJson('/api/auth/register', [
            'name' => 'Sixth Customer',
            'email' => 'customer6@example.com',
            'password' => 'StrongPass12345',
            'password_confirmation' => 'StrongPass12345',
        ])->assertStatus(429);

        $this->assertDatabaseCount('users', 5);
    }

    public function test_login_failed_attempt_limit_is_per_normalized_email_across_ips(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'StrongPass123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '198.51.100.10',
            ])->postJson('/api/auth/login', [
                'email' => 'customer@example.com',
                'password' => 'WrongPass123',
            ])->assertUnauthorized();
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.11',
        ])->postJson('/api/auth/login', [
            'email' => 'CUSTOMER@example.com',
            'password' => 'WrongPass123',
        ])->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_login_also_has_an_independent_ip_limit(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'StrongPass123',
        ]);

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '198.51.100.20',
            ])->postJson('/api/auth/login', [
                'email' => 'customer@example.com',
                'password' => 'StrongPass123',
            ])->assertOk();
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.20',
        ])->postJson('/api/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'StrongPass123',
        ])->assertStatus(429);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            30
        );
    }

    public function test_forgot_password_is_throttled_by_email_and_keeps_a_generic_response(): void
    {
        $genericMessage =
            'If the email address exists, a password reset link will be sent.';

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/auth/forgot-password', [
                'email' => 'unknown@example.com',
            ])->assertOk()
                ->assertJsonPath('message', $genericMessage);
        }

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertStatus(429);
    }

    public function test_forgot_password_also_has_an_independent_ip_limit(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.20',
            ])->postJson('/api/auth/forgot-password', [
                'email' => 'unknown'.$attempt.'@example.com',
            ])->assertOk();
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.20',
        ])->postJson('/api/auth/forgot-password', [
            'email' => 'another-unknown@example.com',
        ])->assertStatus(429);
    }

    public function test_reset_password_is_throttled_by_email(): void
    {
        $payload = [
            'token' => 'invalid-reset-token',
            'email' => 'customer@example.com',
            'password' => 'StrongPass12345',
            'password_confirmation' => 'StrongPass12345',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(
                '/api/auth/reset-password',
                $payload
            )->assertUnprocessable();
        }

        $this->postJson(
            '/api/auth/reset-password',
            $payload
        )->assertStatus(429);
    }

    public function test_reset_password_also_has_an_independent_ip_limit(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.30',
            ])->postJson('/api/auth/reset-password', [
                'token' => 'invalid-reset-token',
                'email' => 'customer'.$attempt.'@example.com',
                'password' => 'StrongPass12345',
                'password_confirmation' => 'StrongPass12345',
            ])->assertUnprocessable();
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.30',
        ])->postJson('/api/auth/reset-password', [
            'token' => 'invalid-reset-token',
            'email' => 'another-customer@example.com',
            'password' => 'StrongPass12345',
            'password_confirmation' => 'StrongPass12345',
        ])->assertStatus(429);
    }
}
