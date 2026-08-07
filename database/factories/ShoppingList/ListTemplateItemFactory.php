<?php

namespace Database\Factories\ShoppingList;

use App\Modules\ShoppingList\Models\ListTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListTemplateItem>
 */
class ListTemplateItemFactory extends Factory
{
    /**
     * @var class-string<ListTemplateItem>
     */
    protected $model = ListTemplateItem::class;

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
            'sort' => 0,
        ];
    }
}
