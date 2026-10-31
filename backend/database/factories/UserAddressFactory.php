<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),

            'label' => fake()->randomElement([
                'Home',
                'Work',
                'Other',
            ]),

            'full_name' => fake()->name(),

            'phone' => fake()->numerify('09########'),

            'city' => fake()->city(),

            'district' => fake()->optional()->city(),

            'ward' => fake()->optional()->streetName(),

            'address_line' => fake()->streetAddress(),

            'is_default' => false,
        ];
    }
}
