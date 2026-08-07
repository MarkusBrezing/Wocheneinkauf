<?php

namespace Database\Factories\Markets;

use App\Modules\Markets\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    /**
     * @var class-string<Market>
     */
    protected $model = Market::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'sort' => 0,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function standard(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
