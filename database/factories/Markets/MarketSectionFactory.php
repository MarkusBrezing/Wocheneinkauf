<?php

namespace Database\Factories\Markets;

use App\Modules\Markets\Models\MarketSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketSection>
 */
class MarketSectionFactory extends Factory
{
    /**
     * @var class-string<MarketSection>
     */
    protected $model = MarketSection::class;

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
        ];
    }
}
