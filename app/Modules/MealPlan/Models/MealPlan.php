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
     * Die Mahlzeit, die ein Tag kennt.
     *
     * Der Wochenplan plant das Abendessen -- sonst nichts. Frühstück, Mittag
     * und Snack standen früher zur Wahl und sind entfernt: Das Frühstück
     * wiederholt sich, mittags ist die halbe Familie außer Haus. Übrig blieb
     * eine Liste mit dreimal so vielen leeren Zeilen.
     *
     * Die Datenbankspalte lässt die alten Werte weiterhin zu -- eine
     * Enum-Änderung wäre eine Migration für nichts. Vorhandene Zeilen dieser
     * Arten zeigen ihren Rohwert an, statt spurlos zu verschwinden.
     *
     * @var array<string, string>
     */
    public const MAHLZEITEN = [
        'dinner' => 'Abendessen',
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
        /*
         * Nur die Mahlzeiten, die der Plan heute kennt.
         *
         * Wochen, die vor der Umstellung auf reines Abendessen angelegt
         * wurden, haben noch Frühstücks- und Mittagszeilen in der Datenbank.
         * Ohne diese Einschränkung stünden sie weiter im Plan -- seit der
         * Mahlzeit-Aufkleber weg ist, als drei namenlose Zeilen je Tag.
         *
         * Die Einschränkung sitzt bewusst an der Beziehung und nicht in der
         * View: So sehen auch die Druckansicht, der Übertrag auf die
         * Einkaufsliste und die Vorschläge dasselbe. Eine Filterung nur in
         * der Anzeige hätte bedeutet, dass ein unsichtbares Mittagessen
         * trotzdem Zutaten auf die Einkaufsliste schiebt.
         *
         * Gelöscht wird nichts: Die Zeilen bleiben stehen, falls die
         * Entscheidung je zurückgenommen wird.
         */
        return $this->hasMany(MealPlanSlot::class)
            ->whereIn('meal', array_keys(self::MAHLZEITEN))
            ->orderBy('plan_date')
            ->orderBy('meal');
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
