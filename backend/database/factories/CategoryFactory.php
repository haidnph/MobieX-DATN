<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,

            'name' => fake()->unique()->words(3, true),

            'slug' => fake()->unique()->slug(),

            'description' => fake()->optional()->sentence(),

            'image' => null,

            'sort_order' => fake()->numberBetween(0, 100),

            'status' => true,
        ];
    }
}
