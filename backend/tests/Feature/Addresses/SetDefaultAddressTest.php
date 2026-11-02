<?php

namespace Tests\Feature\Addresses;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetDefaultAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_set_default_address(): void
    {
        $this->patchJson('/api/addresses/1/default')
            ->assertUnauthorized();
    }

    public function test_user_can_set_their_own_default_address(): void
    {
        $user = User::factory()->create();

        $firstAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => true,
            ]);

        $secondAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => false,
            ]);

        $token = $user->createToken('set-default-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$secondAddress->getKey().'/default',
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Default address updated successfully.',
            )
            ->assertJsonPath(
                'data.address.id',
                $secondAddress->getKey(),
            )
            ->assertJsonPath(
                'data.address.is_default',
                true,
            )
            ->assertJsonMissingPath('data.address.user_id');

        $this->assertDatabaseHas('user_addresses', [
            'id' => $firstAddress->getKey(),
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $secondAddress->getKey(),
            'is_default' => true,
        ]);

        $this->assertSame(
            1,
            $user->addresses()->where('is_default', true)->count(),
        );
    }

    public function test_setting_another_address_clears_previous_default(): void
    {
        $user = User::factory()->create();

        $firstAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => true,
            ]);

        $secondAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => false,
            ]);

        $thirdAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => false,
            ]);

        $token = $user->createToken('switch-default-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$secondAddress->getKey().'/default',
            )
            ->assertOk();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $firstAddress->getKey(),
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $secondAddress->getKey(),
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $thirdAddress->getKey(),
            'is_default' => false,
        ]);

        $this->assertSame(
            1,
            $user->addresses()->where('is_default', true)->count(),
        );
    }

    public function test_setting_the_current_default_address_is_idempotent(): void
    {
        $user = User::factory()->create();

        $defaultAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => true,
            ]);

        $otherAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => false,
            ]);

        $token = $user->createToken('idempotent-default-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$defaultAddress->getKey().'/default',
            )
            ->assertOk();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $defaultAddress->getKey(),
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $otherAddress->getKey(),
            'is_default' => false,
        ]);

        $this->assertSame(
            1,
            $user->addresses()->where('is_default', true)->count(),
        );
    }

    public function test_setting_default_normalizes_existing_duplicate_defaults(): void
    {
        $user = User::factory()->create();

        $firstAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => true,
            ]);

        $secondAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => true,
            ]);

        $thirdAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => false,
            ]);

        $token = $user->createToken('normalize-default-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$thirdAddress->getKey().'/default',
            )
            ->assertOk();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $firstAddress->getKey(),
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $secondAddress->getKey(),
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $thirdAddress->getKey(),
            'is_default' => true,
        ]);

        $this->assertSame(
            1,
            $user->addresses()->where('is_default', true)->count(),
        );
    }

    public function test_user_cannot_set_another_users_address_as_default(): void
    {
        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $ownerAddress = UserAddress::factory()
            ->for($owner)
            ->create([
                'is_default' => true,
            ]);

        $otherAddress = UserAddress::factory()
            ->for($otherUser)
            ->create([
                'is_default' => false,
            ]);

        $token = $otherUser->createToken('foreign-default-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$ownerAddress->getKey().'/default',
            )
            ->assertNotFound();

        // The actual owner remains unaffected.
        $this->assertDatabaseHas('user_addresses', [
            'id' => $ownerAddress->getKey(),
            'user_id' => $owner->getKey(),
            'is_default' => true,
        ]);

        // The requesting user also remains unaffected.
        $this->assertDatabaseHas('user_addresses', [
            'id' => $otherAddress->getKey(),
            'user_id' => $otherUser->getKey(),
            'is_default' => false,
        ]);
    }

    public function test_setting_missing_address_as_default_returns_not_found(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('missing-default-test');

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/addresses/999999/default')
            ->assertNotFound();

        $this->assertDatabaseCount('user_addresses', 0);
    }

    public function test_deleting_default_address_leaves_no_default_address(): void
    {
        $user = User::factory()->create();

        $defaultAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => true,
            ]);

        $anotherAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => false,
            ]);

        $token = $user->createToken('delete-default-test');

        $this->withToken($token->plainTextToken)
            ->deleteJson(
                '/api/addresses/'.$defaultAddress->getKey(),
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Address deleted successfully.',
            );

        $this->assertDatabaseMissing('user_addresses', [
            'id' => $defaultAddress->getKey(),
        ]);

        // D-14 proposed default: do not promote another address implicitly.
        $this->assertDatabaseHas('user_addresses', [
            'id' => $anotherAddress->getKey(),
            'is_default' => false,
        ]);

        $this->assertSame(
            0,
            $user->addresses()->where('is_default', true)->count(),
        );
    }

    public function test_other_users_default_address_is_not_changed(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $userAddress = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => false,
            ]);

        $otherDefaultAddress = UserAddress::factory()
            ->for($otherUser)
            ->create([
                'is_default' => true,
            ]);

        $token = $user->createToken('independent-default-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$userAddress->getKey().'/default',
            )
            ->assertOk();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $userAddress->getKey(),
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $otherDefaultAddress->getKey(),
            'is_default' => true,
        ]);

        $this->assertSame(
            1,
            $user->addresses()->where('is_default', true)->count(),
        );

        $this->assertSame(
            1,
            $otherUser->addresses()->where('is_default', true)->count(),
        );
    }

    public function test_inactive_user_cannot_set_default_address(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => false,
            ]);

        $user->forceFill([
            'is_active' => false,
        ])->save();

        $token = $user->createToken('inactive-default-test');

        $this->withToken($token->plainTextToken)
            ->patchJson(
                '/api/addresses/'.$address->getKey().'/default',
            )
            ->assertForbidden();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->getKey(),
            'is_default' => false,
        ]);
    }
}
