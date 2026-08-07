<?php

namespace App\Modules\MealPlan\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Core\Concerns\HasPublicId;
use App\Modules\ShoppingList\Models\ShoppingList;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Der Wochenplan einer Kalenderwoche (F-01).
 *
 * Je Familie und Woche gibt es genau einen -- das erzwingt der UNIQUE auf
 * (family_id, week_start). Welcher Wochentag den Anfang macht, sagt die
 * Familieneinstellung week_starts_on; in dieser Familie faengt die Woche
 * vielleicht am Samstag an, weil dann eingekauft wird.
 */
#[Fillable(['week_start', 'status'])]
class MealPlan extends Model
{
    use BelongsToFamily, HasPublicId;

    /**
     * Die Mahlzeiten, die ein Tag kennt.
     *
     * @var array<string, string>
     */
    public const MAHLZEITEN = [
        'breakfast' => 'Frühstück',
        'lunch' => 'Mittag',
        'dinner' => 'Abend',
        'snack' => 'Snack',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['week_start' => 'date'];
    }

    /**
     * @return HasMany<MealPlanSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(MealPlanSlot::class)->orderBy('plan_date')->orderBy('meal');
    }

    /**
     * @return HasMany<MealPlanSuggestion, $this>
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(MealPlanSuggestion::class)->orderByDesc('created_at');
    }

    /**
     * @return BelongsTo<ShoppingList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'list_id');
    }

    /**
     * Die sieben Tage der Planwoche.
     *
     * @return array<int, Carbon>
     */
    public function tage(): array
    {
        return collect(range(0, 6))->map(fn (int $i) => $this->week_start->copy()->addDays($i))->all();
    }

    public function anzeigename(): string
    {
        return 'Woche ab '.$this->week_start->format('d.m.Y');
    }

    public function kalenderwoche(): int
    {
        return (int) $this->week_start->isoWeek();
    }
}
