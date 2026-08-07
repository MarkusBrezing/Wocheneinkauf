<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @var class-string<Product>
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * name_normalized fuellt das Model beim Speichern selbst.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'default_unit' => fake()->randomElement([null, 'Stk', 'g', 'kg', 'l']),
            'is_pantry_staple' => false,
        ];
    }

    public function staple(): static
    {
        return $this->state(fn () => ['is_pantry_staple' => true]);
    }
}
