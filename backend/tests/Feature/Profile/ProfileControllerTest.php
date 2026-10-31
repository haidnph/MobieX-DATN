<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_retrieve_or_update_profile(): void
    {
        $this->getJson('/api/profile')
            ->assertUnauthorized();

        $this->patchJson('/api/profile', [
            'name' => 'Updated Name',
        ])->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );
    }

    public function test_authenticated_user_can_retrieve_own_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0912345678',
        ]);

        $token = $user->createToken('profile-test');

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Profile retrieved successfully.',
            )
            ->assertJsonPath('data.user.id', $user->getKey())
            ->assertJsonPath('data.user.name', 'Test Customer')
            ->assertJsonPath(
                'data.user.email',
                'customer@example.com',
            )
            ->assertJsonPath(
                'data.user.phone',
                '0912345678',
            )
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertJsonMissingPath('data.user.is_active')
            ->assertJsonMissingPath('data.token');

        $this->assertSame(
            ['id', 'name', 'email', 'phone'],
            array_keys($response->json('data.user')),
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1,
        );
    }

    public function test_authenticated_user_can_update_name_and_phone(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'customer@example.com',
            'phone' => '0911111111',
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'phone' => '0922222222',
        ]);

        $token = $user->createToken('profile-update-test');

        $response = $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile', [
                'name' => 'Updated Name',
                'phone' => '0987654321',
            ]);

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Profile updated successfully.',
            )
            ->assertJsonPath('data.user.id', $user->getKey())
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath(
                'data.user.email',
                'customer@example.com',
            )
            ->assertJsonPath(
                'data.user.phone',
                '0987654321',
            );

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'Updated Name',
            'email' => 'customer@example.com',
            'phone' => '0987654321',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $otherUser->getKey(),
            'name' => 'Other User',
            'email' => 'other@example.com',
            'phone' => '0922222222',
        ]);
    }

    public function test_authenticated_user_can_clear_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
        ]);

        $token = $user->createToken('profile-clear-phone-test');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile', [
                'phone' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.user.phone', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'phone' => null,
        ]);
    }

    public function test_empty_profile_update_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('profile-empty-update-test');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile']);
    }

    public function test_invalid_profile_fields_are_rejected(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('profile-invalid-update-test');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile', [
                'name' => '',
                'phone' => str_repeat('1', 21),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'phone',
            ]);
    }

    public function test_fields_outside_profile_contract_cannot_be_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'customer@example.com',
        ]);

        $token = $user->createToken('profile-protected-fields-test');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile', [
                'name' => 'Changed Name',
                'email' => 'changed@example.com',
                'is_active' => false,
                'user_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'is_active',
                'user_id',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'Original Name',
            'email' => 'customer@example.com',
            'is_active' => true,
        ]);
    }

    public function test_inactive_user_cannot_access_profile_endpoints(): void
    {
        $user = User::factory()->create();

        // is_active is not mass assignable in the current User model.
        $user->forceFill([
            'is_active' => false,
        ])->save();

        $token = $user->createToken('inactive-profile-test');

        $this->withToken($token->plainTextToken)
            ->getJson('/api/profile')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'This account is inactive.',
            );

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/profile', [
                'name' => 'Updated Name',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => $user->name,
            'is_active' => false,
        ]);
    }
}
