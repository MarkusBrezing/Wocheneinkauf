<?php

namespace Database\Factories\Pantry;

use App\Modules\Pantry\Models\PantryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PantryItem>
 */
class PantryItemFactory extends Factory
{
    /**
     * @var class-string<PantryItem>
     */
    protected $model = PantryItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location' => 'Vorrat',
            'unit' => 'Pck',
            'target_qty' => 2,
            'min_qty' => 1,
            'current_qty' => 2,
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
