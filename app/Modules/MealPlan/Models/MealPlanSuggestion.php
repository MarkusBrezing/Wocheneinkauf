<?php

namespace App\Modules\MealPlan\Models;

use App\Core\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Vorschlagslauf (F-06 … F-08).
 *
 * Das Protokoll ist der Grund, warum "nochmal wuerfeln" nicht denselben
 * Vorschlag liefert: Was schon einmal vorgeschlagen und verworfen wurde, ist
 * bekannt.
 */
#[Fillable(['strategy', 'params', 'result'])]
class MealPlanSuggestion extends Model
{
    use BelongsToFamily;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'result' => 'array',
            'accepted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MealPlan, $this>
     */
    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function istAngenommen(): bool
    {
        return $this->accepted_at !== null;
    }
}
