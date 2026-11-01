<?php

namespace Tests\Feature\Addresses;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_address_endpoints(): void
    {
        $this->getJson('/api/addresses')
            ->assertUnauthorized();

        $this->postJson('/api/addresses', $this->validPayload())
            ->assertUnauthorized();

        $this->patchJson('/api/addresses/1', [
            'city' => 'Ho Chi Minh City',
        ])->assertUnauthorized();

        $this->deleteJson('/api/addresses/1')
            ->assertUnauthorized();
    }

    public function test_user_can_list_only_their_own_addresses(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $firstAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'full_name' => 'First Customer',
            ]);

        $secondAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'full_name' => 'Second Customer',
            ]);

        UserAddress::factory()
            ->for($otherUser)
            ->create([
                'full_name' => 'Other Customer',
            ]);

        $token = $user->createToken('address-list-test');

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/addresses');

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Addresses retrieved successfully.',
            )
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath(
                'data.0.id',
                $secondAddress->getKey(),
            )
            ->assertJsonPath(
                'data.0.full_name',
                'Second Customer',
            )
            ->assertJsonPath(
                'data.1.id',
                $firstAddress->getKey(),
            )
            ->assertJsonMissing([
                'full_name' => 'Other Customer',
            ])
            ->assertJsonMissingPath('data.0.user_id');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_create_an_address_for_themselves(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('address-create-test');

        $payload = $this->validPayload();

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/addresses', $payload);

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Address created successfully.',
            )
            ->assertJsonPath(
                'data.address.full_name',
                'Test Customer',
            )
            ->assertJsonPath(
                'data.address.phone',
                '0912345678',
            )
            ->assertJsonPath(
                'data.address.city',
                'Ha Noi',
            )
            ->assertJsonPath(
                'data.address.is_default',
                false,
            )
            ->assertJsonMissingPath('data.address.user_id');

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->getKey(),
            'full_name' => 'Test Customer',
            'phone' => '0912345678',
            'city' => 'Ha Noi',
            'district' => 'Cau Giay',
            'ward' => 'Dich Vong',
            'address_line' => '123 Example Street',
            'is_default' => false,
        ]);

        $this->assertDatabaseCount('user_addresses', 1);
    }

    public function test_user_can_create_an_address_with_nullable_fields(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('address-null-fields-test');

        $payload = $this->validPayload();

        $payload['label'] = null;
        $payload['district'] = null;
        $payload['ward'] = null;

        $this->withToken($token->plainTextToken)
            ->postJson('/api/addresses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.address.label', null)
            ->assertJsonPath('data.address.district', null)
            ->assertJsonPath('data.address.ward', null);
    }

    public function test_address_creation_requires_mandatory_fields(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('address-required-fields-test');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/addresses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'full_name',
                'phone',
                'city',
                'address_line',
            ]);

        $this->assertDatabaseCount('user_addresses', 0);
    }

    public function test_address_creation_validates_column_lengths(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('address-length-test');

        $payload = [
            'label' => str_repeat('a', 51),
            'full_name' => str_repeat('a', 256),
            'phone' => str_repeat('1', 21),
            'city' => str_repeat('a', 101),
            'district' => str_repeat('a', 101),
            'ward' => str_repeat('a', 101),
            'address_line' => str_repeat('a', 256),
        ];

        $this->withToken($token->plainTextToken)
            ->postJson('/api/addresses', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'label',
                'full_name',
                'phone',
                'city',
                'district',
                'ward',
                'address_line',
            ]);

        $this->assertDatabaseCount('user_addresses', 0);
    }

    public function test_address_creation_rejects_user_id_and_is_default(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('address-protected-fields-test');

        $payload = $this->validPayload();

        $payload['user_id'] = 999999;
        $payload['is_default'] = true;

        $this->withToken($token->plainTextToken)
            ->postJson('/api/addresses', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id',
                'is_default',
            ]);

        $this->assertDatabaseCount('user_addresses', 0);
    }

    public function test_user_can_update_their_own_address(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()
            ->for($user)
            ->create([
                'full_name' => 'Old Name',
                'city' => 'Ha Noi',
                'district' => 'Ba Dinh',
                'ward' => 'Old Ward',
            ]);

        $token = $user->createToken('address-update-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$address->getKey(),
                [
                    'full_name' => 'Updated Name',
                    'city' => 'Ho Chi Minh City',
                    'district' => null,
                    'ward' => null,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Address updated successfully.',
            )
            ->assertJsonPath(
                'data.address.id',
                $address->getKey(),
            )
            ->assertJsonPath(
                'data.address.full_name',
                'Updated Name',
            )
            ->assertJsonPath(
                'data.address.city',
                'Ho Chi Minh City',
            )
            ->assertJsonPath('data.address.district', null)
            ->assertJsonPath('data.address.ward', null);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->getKey(),
            'user_id' => $user->getKey(),
            'full_name' => 'Updated Name',
            'city' => 'Ho Chi Minh City',
            'district' => null,
            'ward' => null,
        ]);
    }

    public function test_user_cannot_update_another_users_address(): void
    {
        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $address = UserAddress::factory()
            ->for($owner)
            ->create([
                'full_name' => 'Original Owner',
                'city' => 'Ha Noi',
            ]);

        $token = $otherUser->createToken('address-ownership-update-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$address->getKey(),
                [
                    'full_name' => 'Unauthorized Update',
                    'city' => 'Da Nang',
                ],
            )
            ->assertNotFound();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->getKey(),
            'user_id' => $owner->getKey(),
            'full_name' => 'Original Owner',
            'city' => 'Ha Noi',
        ]);
    }

    public function test_address_update_rejects_empty_payload(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()
            ->for($user)
            ->create();

        $token = $user->createToken('address-empty-update-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$address->getKey(),
                [],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address']);
    }

    public function test_address_update_validates_fields_and_rejects_unsupported_fields(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()
            ->for($user)
            ->create([
                'full_name' => 'Original Name',
            ]);

        $token = $user->createToken('address-update-validation-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$address->getKey(),
                [
                    'full_name' => '',
                    'phone' => str_repeat('1', 21),
                    'is_default' => true,
                    'user_id' => 999999,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'full_name',
                'phone',
                'is_default',
                'user_id',
            ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->getKey(),
            'full_name' => 'Original Name',
        ]);
    }

    public function test_user_can_delete_their_own_address(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()
            ->for($user)
            ->create();

        $token = $user->createToken('address-delete-test');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/addresses/'.$address->getKey())
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Address deleted successfully.',
            );

        $this->assertDatabaseMissing('user_addresses', [
            'id' => $address->getKey(),
        ]);
    }

    public function test_user_cannot_delete_another_users_address(): void
    {
        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $address = UserAddress::factory()
            ->for($owner)
            ->create();

        $token = $otherUser->createToken('address-ownership-delete-test');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/addresses/'.$address->getKey())
            ->assertNotFound();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->getKey(),
            'user_id' => $owner->getKey(),
        ]);
    }

    public function test_update_and_delete_return_not_found_for_missing_addresses(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('address-not-found-test');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/addresses/999999', [
                'city' => 'Da Nang',
            ])
            ->assertNotFound();

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/addresses/999999')
            ->assertNotFound();
    }

    public function test_inactive_user_cannot_access_address_endpoints(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_active' => false,
        ])->save();

        $token = $user->createToken('inactive-address-test');

        $this->withToken($token->plainTextToken)
            ->getJson('/api/addresses')
            ->assertForbidden();

        $this->withToken($token->plainTextToken)
            ->postJson('/api/addresses', $this->validPayload())
            ->assertForbidden();

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/addresses/1', [
                'city' => 'Da Nang',
            ])
            ->assertForbidden();

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/addresses/1')
            ->assertForbidden();

        $this->assertDatabaseCount('user_addresses', 0);
    }

    /**
     * Return a valid address payload for feature tests.
     *
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'label' => 'Home',
            'full_name' => 'Test Customer',
            'phone' => '0912345678',
            'city' => 'Ha Noi',
            'district' => 'Cau Giay',
            'ward' => 'Dich Vong',
            'address_line' => '123 Example Street',
        ];
    }
}
