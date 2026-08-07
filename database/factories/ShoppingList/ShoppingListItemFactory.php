<?php

namespace Database\Factories\ShoppingList;

use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingListItem>
 */
class ShoppingListItemFactory extends Factory
{
    /**
     * @var class-string<ShoppingListItem>
     */
    protected $model = ShoppingListItem::class;

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
            'is_checked' => false,
            'source' => 'manual',
            'sort' => 0,
        ];
    }

    public function abgehakt(): static
    {
        return $this->state(fn () => [
            'is_checked' => true,
            'checked_at' => now(),
        ]);
    }
}
