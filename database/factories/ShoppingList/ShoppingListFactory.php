<?php

namespace Database\Factories\ShoppingList;

use App\Modules\ShoppingList\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingList>
 */
class ShoppingListFactory extends Factory
{
    /**
     * @var class-string<ShoppingList>
     */
    protected $model = ShoppingList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => null,
            'status' => 'active',
            'version' => 1,
        ];
    }

    public function archiviert(): static
    {
        return $this->state(fn () => [
            'status' => 'archived',
            'archived_at' => now(),
        ]);
    }
}
