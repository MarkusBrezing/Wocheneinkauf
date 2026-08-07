<?php

namespace Database\Factories\Recipes;

use App\Modules\Recipes\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * @var class-string<Recipe>
     */
    protected $model = Recipe::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'servings' => 4,
            'effort' => 'normal',
            'instructions' => null,
            'plant_points' => 0,
        ];
    }

    public function favorit(): static
    {
        return $this->state(fn () => ['is_favorite' => true]);
    }
}
