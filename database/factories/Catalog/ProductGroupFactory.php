<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\ProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductGroup>
 *
 * family_id setzt der BelongsToFamily-Trait aus dem Familienkontext. Wer im
 * Test eine Gruppe anlegt, muss ihn also gesetzt haben -- genau das ist die
 * Absicht.
 */
class ProductGroupFactory extends Factory
{
    /**
     * @var class-string<ProductGroup>
     */
    protected $model = ProductGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->word();

        return [
            'code' => Str::upper(Str::ascii($label)),
            'label' => Str::ucfirst($label),
            'sort' => fake()->numberBetween(0, 100),
        ];
    }
}
