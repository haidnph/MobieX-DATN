<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => 'attribute_'.strtolower(
                fake()->unique()->lexify('????')
            ),
            'type' => 'select',
            'sort_order' => fake()->numberBetween(0, 100),
            'status' => true,
        ];
    }
}
