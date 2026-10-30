<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_bearer_token(): void
    {
        $password = 'StrongPass12345';

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0912345678',
            'password' => $password,
            'password_confirmation' => $password,
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
                    'expires_at',
                ],
            ])
            ->assertJsonMissingPath('data.user.password');

        $user = User::where(
            'email',
            'customer@example.com'
        )->firstOrFail();

        $this->assertTrue(
            Hash::check($password, $user->password)
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

        $this->assertNotEmpty(
            $response->json('data.expires_at')
        );
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $password = 'StrongPass12345';

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Another Customer',
            'email' => 'existing@example.com',
            'password' => $password,
            'password_confirmation' => $password,
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

    public function test_registration_rejects_password_below_15_characters(): void
    {
        // 14 characters.
        $password = 'StrongPass1234';

        $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'short-password@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_accepts_a_64_character_password(): void
    {
        // A 64-character ASCII passphrase remains within bcrypt's byte limit.
        $password = str_repeat('A', 64);

        $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'maximum-password@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertCreated();

        $user = User::where(
            'email',
            'maximum-password@example.com'
        )->firstOrFail();

        $this->assertTrue(
            Hash::check($password, $user->password)
        );
    }

    public function test_registration_enforces_the_bcrypt_byte_limit_without_truncation(): void
    {
        // The character "ắ" occupies 3 UTF-8 bytes.
        // 24 characters = 72 bytes and is accepted.
        $passwordAtByteLimit = str_repeat('ắ', 24);

        $this->assertSame(
            72,
            strlen($passwordAtByteLimit)
        );

        $this->assertSame(
            24,
            mb_strlen($passwordAtByteLimit, 'UTF-8')
        );

        $this->postJson('/api/auth/register', [
            'name' => 'Unicode Customer',
            'email' => 'unicode-accepted@example.com',
            'password' => $passwordAtByteLimit,
            'password_confirmation' => $passwordAtByteLimit,
        ])->assertCreated();

        $user = User::where(
            'email',
            'unicode-accepted@example.com'
        )->firstOrFail();

        $this->assertTrue(
            Hash::check(
                $passwordAtByteLimit,
                $user->password
            )
        );

        // 25 characters = 75 bytes. Reject explicitly before hashing.
        $passwordOverByteLimit = str_repeat('ắ', 25);

        $this->assertSame(
            75,
            strlen($passwordOverByteLimit)
        );

        $this->postJson('/api/auth/register', [
            'name' => 'Unicode Customer',
            'email' => 'unicode-rejected@example.com',
            'password' => $passwordOverByteLimit,
            'password_confirmation' => $passwordOverByteLimit,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_assigns_customer_role_when_customer_role_exists(): void
    {
        $role = Role::factory()->create([
            'slug' => 'customer',
            'name' => 'Customer',
        ]);

        $this->postJson('/api/auth/register', [
            'name' => 'Role Customer',
            'email' => 'role-customer@example.com',
            'password' => 'StrongPass12345',
            'password_confirmation' => 'StrongPass12345',
        ])->assertCreated();

        $user = User::where('email', 'role-customer@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('customer'));
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
        ]);
    }

    public function test_registration_creates_and_assigns_customer_role_when_not_seeded(): void
    {
        $this->assertDatabaseMissing('roles', [
            'slug' => 'customer',
        ]);

        $this->postJson('/api/auth/register', [
            'name' => 'Default Role Customer',
            'email' => 'default-role-customer@example.com',
            'password' => 'StrongPass12345',
            'password_confirmation' => 'StrongPass12345',
        ])->assertCreated();

        $role = Role::query()->where('slug', 'customer')->firstOrFail();
        $user = User::where('email', 'default-role-customer@example.com')->firstOrFail();

        $this->assertSame('Customer', $role->name);
        $this->assertTrue($user->hasRole($role));
    }
}
