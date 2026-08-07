<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\ProductGroupRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductGroupRule>
 */
class ProductGroupRuleFactory extends Factory
{
    /**
     * @var class-string<ProductGroupRule>
     */
    protected $model = ProductGroupRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pattern' => fake()->unique()->word(),
            'is_regex' => false,
            'priority' => 100,
        ];
    }
}
