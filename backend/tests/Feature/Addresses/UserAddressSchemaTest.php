<?php

namespace Tests\Feature\Addresses;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserAddressSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_addresses_table_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasTable('user_addresses'),
        );

        $this->assertTrue(
            Schema::hasColumns('user_addresses', [
                'id',
                'user_id',
                'label',
                'full_name',
                'phone',
                'city',
                'district',
                'ward',
                'address_line',
                'is_default',
                'created_at',
                'updated_at',
            ]),
        );
    }

    public function test_user_address_has_required_fields(): void
    {
        $user = User::factory()->create();

        // full_name is required by the database schema.
        $this->expectException(QueryException::class);

        DB::table('user_addresses')->insert([
            'user_id' => $user->getKey(),
            'phone' => '0912345678',
            'city' => 'Ha Noi',
            'address_line' => '123 Example Street',
        ]);
    }

    public function test_user_address_requires_an_existing_user(): void
    {
        $this->expectException(QueryException::class);

        DB::table('user_addresses')->insert([
            'user_id' => 999999,
            'full_name' => 'Test Customer',
            'phone' => '0912345678',
            'city' => 'Ha Noi',
            'address_line' => '123 Example Street',
        ]);
    }

    public function test_user_can_have_multiple_addresses(): void
    {
        $user = User::factory()->create();

        $addresses = UserAddress::factory()
            ->count(2)
            ->for($user)
            ->create();

        $this->assertCount(2, $user->addresses()->get());

        $this->assertSame(
            $user->getKey(),
            $addresses->first()->user->getKey(),
        );

        $this->assertSame(
            $user->getKey(),
            $addresses->last()->user_id,
        );
    }

    public function test_deleting_user_cascades_to_owned_addresses(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()
            ->for($user)
            ->create();

        $addressId = $address->getKey();

        $user->delete();

        $this->assertDatabaseMissing('user_addresses', [
            'id' => $addressId,
        ]);
    }

    public function test_optional_columns_are_nullable_and_database_default_is_false(): void
    {
        $user = User::factory()->create();

        // Do not supply label, district, ward or is_default.
        // The database must apply the nullable/default constraints itself.
        $addressId = DB::table('user_addresses')->insertGetId([
            'user_id' => $user->getKey(),
            'full_name' => 'Test Customer',
            'phone' => '0912345678',
            'city' => 'Ha Noi',
            'address_line' => '123 Example Street',
        ]);

        $address = UserAddress::query()->findOrFail($addressId);

        $this->assertNull($address->label);
        $this->assertNull($address->district);
        $this->assertNull($address->ward);

        $this->assertFalse($address->is_default);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $addressId,
            'is_default' => 0,
        ]);
    }

    public function test_is_default_is_cast_to_boolean(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()
            ->for($user)
            ->create([
                'is_default' => true,
            ]);

        $this->assertIsBool($address->is_default);
        $this->assertTrue($address->is_default);
    }

    public function test_user_id_is_not_mass_assignable_on_address_model(): void
    {
        $address = new UserAddress;

        $this->assertFalse(
            $address->isFillable('user_id'),
        );
    }
}
