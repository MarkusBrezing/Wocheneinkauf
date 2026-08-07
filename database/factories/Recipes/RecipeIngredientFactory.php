<?php

namespace Database\Factories\Recipes;

use App\Modules\Recipes\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    /**
     * @var class-string<RecipeIngredient>
     */
    protected $model = RecipeIngredient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'raw_name' => ucfirst(fake()->unique()->word()),
            'amount' => null,
            'unit' => null,
            'is_pantry' => false,
            'is_optional' => false,
            'sort' => 0,
        ];
    }
}
