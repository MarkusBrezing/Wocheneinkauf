<?php

namespace App\Modules\MealPlan\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Modules\Recipes\Models\Recipe;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Platz im Wochenplan: ein Tag, eine Mahlzeit (F-02, F-03).
 *
 * Belegt ist er entweder mit einem Rezept oder mit Freitext -- beides
 * gleichzeitig waere widerspruechlich, deshalb loescht das eine das andere.
 */
#[Fillable(['recipe_id', 'custom_text', 'servings', 'is_locked'])]
class MealPlanSlot extends Model
{
    use BelongsToFamily;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'servings' => 'integer',
            'is_locked' => 'boolean',
            'is_cooked' => 'boolean',
            'cooked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MealPlan, $this>
     */
    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @param  Builder<MealPlanSlot>  $query
     * @return Builder<MealPlanSlot>
     */
    public function scopeBelegt(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNotNull('recipe_id')->orWhereNotNull('custom_text'));
    }

    public function istBelegt(): bool
    {
        return $this->recipe_id !== null || ($this->custom_text !== null && $this->custom_text !== '');
    }

    public function anzeige(): string
    {
        return $this->recipe?->name ?? ($this->custom_text ?: '');
    }

    public function mahlzeitLabel(): string
    {
        return MealPlan::MAHLZEITEN[$this->meal] ?? $this->meal;
    }
}
