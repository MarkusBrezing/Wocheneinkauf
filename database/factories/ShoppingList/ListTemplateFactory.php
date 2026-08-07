<?php

namespace Database\Factories\ShoppingList;

use App\Modules\ShoppingList\Models\ListTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListTemplate>
 */
class ListTemplateFactory extends Factory
{
    /**
     * @var class-string<ListTemplate>
     */
    protected $model = ListTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Vorlage '.ucfirst(fake()->unique()->word()),
            'description' => null,
            'sort' => 0,
        ];
    }

    public function standard(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
