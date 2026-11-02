<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_phone_and_defaults_to_active(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
        ]);

        $user->refresh();

        $this->assertSame('0912345678', $user->phone);
        $this->assertTrue($user->is_active);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '0912345678',
            'is_active' => 1,
        ]);
    }

    public function test_phone_is_optional(): void
    {
        $user = User::factory()->create([
            'phone' => null,
        ]);

        $user->refresh();

        $this->assertNull($user->phone);
        $this->assertTrue($user->is_active);
    }

    public function test_account_status_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isFillable('is_active'));
    }
}
