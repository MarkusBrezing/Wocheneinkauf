<?php

namespace App\Modules\MealPlan\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\MealPlan\Models\MealPlan;
use App\Modules\MealPlan\Models\MealPlanSlot;
use App\Modules\Pantry\Models\PantryItem;
use App\Modules\Recipes\Models\Recipe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Der Vorschlagsgenerator (F-06, F-07).
 *
 * Bewusst regelbasiert und deterministisch: Sieben Abendessen auszuwaehlen ist
 * kein Problem, das eine KI braucht -- es ist eine Punktevergabe. Das hat drei
 * Vorteile, die im Alltag mehr wiegen als Raffinesse: Es laeuft in
 * Millisekunden, es ist testbar, und man kann erklaeren, warum ein Gericht
 * vorgeschlagen wurde.
 *
 * Die Kriterien stammen aus Abschnitt 4.4 des Architekturkonzepts:
 *
 *   Letzte Zubereitung   je laenger her, desto besser
 *   Vorratsdeckung       was da ist, muss nicht gekauft werden
 *   Protein-Abwechslung  nicht zweimal hintereinander dasselbe
 *   Kuechen-Abwechslung  hoechstens zwei Gerichte einer Kueche je Woche
 *   Pflanzenpunkte       die Woche soll bunt werden
 *   Aufwand              werktags schnell, am Wochenende darf es dauern
 *   Ablaufdatum          was bald schlecht wird, kommt zuerst auf den Tisch
 */
class SuggestionEngine
{
    /**
     * Gewichte der Kriterien. Als Konstante und nicht als Zufallszahl, damit
     * derselbe Bestand denselben Vorschlag ergibt -- ausser man wuerfelt
     * bewusst neu.
     *
     * @var array<string, float>
     */
    public const GEWICHTE = [
        'letzte_zubereitung' => 30.0,
        'vorratsdeckung' => 20.0,
        'protein' => 25.0,
        'kueche' => 15.0,
        'pflanzenpunkte' => 10.0,
        'aufwand' => 20.0,
        'ablaufdatum' => 15.0,
        'favorit' => 5.0,
    ];

    /**
     * Vorschlag fuer die offenen Slots eines Plans.
     *
     * Gesperrte und bereits belegte Slots bleiben unangetastet -- sie gehen
     * aber in die Bewertung ein, damit die Abwechslung ueber die ganze Woche
     * stimmt und nicht nur ueber den vorgeschlagenen Teil.
     *
     * @param  array<int, int>  $ausgeschlossen  Rezept-IDs, die nicht kommen sollen
     * @return array<int, array{slot_id: int, recipe_id: int, score: float}>
     */
    public function suggest(MealPlan $plan, string $mahlzeit = 'dinner', array $ausgeschlossen = []): array
    {
        $rezepte = $this->rezepte($ausgeschlossen);

        if ($rezepte->isEmpty()) {
            return [];
        }

        $zuletzt = $this->letzteZubereitung();
        $vorrat = $this->vorratsdeckung();
        $belegung = $this->belegung($plan);

        $vorschlag = [];
        // Was in dieser Woche schon steht, ist vergeben -- auch wenn es jemand
        // von Hand eingetragen hat. Sonst schlaegt der Generator ausgerechnet
        // das Gericht vor, das ohnehin am Dienstag ansteht.
        $vergeben = $plan->slots()->whereNotNull('recipe_id')->pluck('recipe_id')->all();

        foreach ($this->offeneSlots($plan, $mahlzeit) as $slot) {
            $bewertet = $rezepte
                ->reject(fn (Recipe $r) => in_array($r->getKey(), $vergeben, true))
                ->map(fn (Recipe $r) => [
                    'rezept' => $r,
                    'punkte' => $this->bewerte($r, $slot->plan_date, $zuletzt, $vorrat, $belegung),
                ])
                ->sortByDesc('punkte')
                ->values();

            if ($bewertet->isEmpty()) {
                break;
            }

            $treffer = $bewertet->first();
            $rezept = $treffer['rezept'];

            $vorschlag[] = [
                'slot_id' => $slot->getKey(),
                'recipe_id' => $rezept->getKey(),
                'score' => round($treffer['punkte'], 2),
            ];

            // Innerhalb einer Woche kommt jedes Gericht nur einmal, und die
            // Belegung waechst mit -- sonst stuenden am Ende sieben Currys da.
            $vergeben[] = $rezept->getKey();
            $belegung->push([
                'datum' => $slot->plan_date,
                'protein' => $this->tag($rezept, 'protein'),
                'kueche' => $this->tag($rezept, 'cuisine'),
            ]);
        }

        return $vorschlag;
    }

    /**
     * Ein einzelner Slot neu gewuerfelt (F-07).
     *
     * Das bereits vorgeschlagene Rezept wird ausgeschlossen -- sonst waere
     * "nochmal" ohne Wirkung.
     *
     * @param  array<int, int>  $ausgeschlossen
     * @return array{recipe_id: int, score: float}|null
     */
    public function reroll(MealPlanSlot $slot, array $ausgeschlossen = []): ?array
    {
        $plan = $slot->mealPlan;

        // Ausgeschlossen ist alles, was diese Woche schon irgendwo steht --
        // einschliesslich dessen, was gerade im Slot liegt.
        $belegt = $plan->slots()->whereNotNull('recipe_id')->pluck('recipe_id')->all();
        $rezepte = $this->rezepte(array_merge($ausgeschlossen, $belegt, array_filter([$slot->recipe_id])));

        if ($rezepte->isEmpty()) {
            return null;
        }

        $bewertet = $rezepte
            ->map(fn (Recipe $r) => [
                'rezept' => $r,
                'punkte' => $this->bewerte(
                    $r,
                    $slot->plan_date,
                    $this->letzteZubereitung(),
                    $this->vorratsdeckung(),
                    $this->belegung($plan, $slot->getKey()),
                ),
            ])
            ->sortByDesc('punkte')
            ->first();

        return [
            'recipe_id' => $bewertet['rezept']->getKey(),
            'score' => round($bewertet['punkte'], 2),
        ];
    }

    /**
     * Die Punktzahl eines Rezepts fuer einen bestimmten Tag.
     *
     * @param  Collection<int, int>  $zuletzt  Rezept-ID => Tage seit der letzten Zubereitung
     * @param  Collection<string, float>  $vorrat  Normalform => vorhandene Menge
     * @param  Collection<int, array{datum: Carbon, protein: ?string, kueche: ?string}>  $belegung
     */
    protected function bewerte(Recipe $rezept, Carbon $datum, Collection $zuletzt, Collection $vorrat, Collection $belegung): float
    {
        $punkte = 0.0;

        // Je laenger her, desto besser -- ab einem halben Jahr macht es keinen
        // Unterschied mehr.
        $tage = $zuletzt->get($rezept->getKey(), 999);
        $punkte += self::GEWICHTE['letzte_zubereitung'] * min(1.0, $tage / 180);

        // Was im Vorrat liegt, muss nicht gekauft werden.
        $punkte += self::GEWICHTE['vorratsdeckung'] * $this->deckung($rezept, $vorrat);

        // Kein gleiches Protein an zwei aufeinanderfolgenden Tagen.
        $protein = $this->tag($rezept, 'protein');
        $nachbar = $belegung->first(fn (array $b) => $protein !== null
            && $b['protein'] === $protein
            && abs($b['datum']->diffInDays($datum)) <= 1);
        $punkte += $nachbar === null ? self::GEWICHTE['protein'] : 0.0;

        // Hoechstens zwei Gerichte derselben Kueche je Woche.
        $kueche = $this->tag($rezept, 'cuisine');
        $gleiche = $kueche === null ? 0 : $belegung->where('kueche', $kueche)->count();
        $punkte += $gleiche < 2 ? self::GEWICHTE['kueche'] : 0.0;

        // Pflanzenpunkte: mehr ist besser, gedeckelt bei zehn.
        $punkte += self::GEWICHTE['pflanzenpunkte'] * min(1.0, $rezept->plant_points / 10);

        // Werktags schnell, am Wochenende darf es dauern.
        $punkte += self::GEWICHTE['aufwand'] * $this->aufwandsPassung($rezept, $datum);

        // Was bald abläuft, kommt zuerst auf den Tisch.
        $punkte += self::GEWICHTE['ablaufdatum'] * $this->ablaufdruck($rezept);

        if ($rezept->is_favorite) {
            $punkte += self::GEWICHTE['favorit'];
        }

        return $punkte;
    }

    /**
     * Anteil der Zutaten, die im Vorrat liegen (0 bis 1).
     *
     * @param  Collection<string, float>  $vorrat
     */
    protected function deckung(Recipe $rezept, Collection $vorrat): float
    {
        $zutaten = $rezept->ingredients->reject(fn ($z) => $z->is_pantry || $z->is_optional);

        if ($zutaten->isEmpty()) {
            return 0.0;
        }

        $gedeckt = $zutaten->filter(function ($zutat) use ($vorrat) {
            $menge = $vorrat->get($zutat->zusammenfassungsschluessel(), 0.0);

            return $menge > 0.0;
        })->count();

        return $gedeckt / $zutaten->count();
    }

    /**
     * Passt der Aufwand zum Wochentag? Werktags zaehlt "schnell", am
     * Wochenende darf es aufwendig sein.
     */
    protected function aufwandsPassung(Recipe $rezept, Carbon $datum): float
    {
        $wochenende = $datum->isWeekend();

        return match ($rezept->effort) {
            'quick' => $wochenende ? 0.5 : 1.0,
            'elaborate' => $wochenende ? 1.0 : 0.0,
            default => 0.75,
        };
    }

    /**
     * Wie dringend die Zutaten weg muessen (0 bis 1).
     */
    protected function ablaufdruck(Recipe $rezept): float
    {
        $ids = $rezept->ingredients->pluck('product_id')->filter()->all();

        if ($ids === []) {
            return 0.0;
        }

        $naechstes = PantryItem::whereIn('product_id', $ids)
            ->whereNotNull('best_before')
            ->where('current_qty', '>', 0)
            ->orderBy('best_before')
            ->value('best_before');

        if ($naechstes === null) {
            return 0.0;
        }

        $tage = now()->startOfDay()->diffInDays(Carbon::parse($naechstes), false);

        // Innerhalb einer Woche: je naeher, desto dringender.
        return $tage < 0 ? 1.0 : max(0.0, 1.0 - ($tage / 7));
    }

    /**
     * Die Rezepte, aus denen gewaehlt werden darf.
     *
     * @param  array<int, int>  $ausgeschlossen
     * @return Collection<int, Recipe>
     */
    protected function rezepte(array $ausgeschlossen): Collection
    {
        return Recipe::query()
            // F-15: dauerhaft ausgeschlossene Rezepte kommen nie.
            ->where('exclude_from_suggestions', false)
            ->when($ausgeschlossen !== [], fn ($q) => $q->whereNotIn('id', $ausgeschlossen))
            ->with(['ingredients', 'tags'])
            ->get();
    }

    /**
     * Wann ein Rezept zuletzt auf dem Tisch stand (F-12).
     *
     * @return Collection<int, int> Rezept-ID => Tage seither
     */
    protected function letzteZubereitung(): Collection
    {
        return MealPlanSlot::query()
            ->whereNotNull('recipe_id')
            ->where('plan_date', '<=', now())
            ->selectRaw('recipe_id, MAX(plan_date) as zuletzt')
            ->groupBy('recipe_id')
            ->pluck('zuletzt', 'recipe_id')
            ->map(fn ($datum) => (int) Carbon::parse($datum)->startOfDay()->diffInDays(now()->startOfDay()));
    }

    /**
     * Was im Vorrat liegt, nach Normalform des Artikelnamens.
     *
     * @return Collection<string, float>
     */
    protected function vorratsdeckung(): Collection
    {
        return PantryItem::active()
            ->where('current_qty', '>', 0)
            ->with('product')
            ->get()
            ->filter(fn (PantryItem $item) => $item->product !== null)
            ->mapWithKeys(fn (PantryItem $item) => [
                Product::normalize($item->product->name) => (float) $item->current_qty,
            ]);
    }

    /**
     * Was in dieser Woche schon feststeht -- fuer die Abwechslung.
     *
     * @return Collection<int, array{datum: Carbon, protein: ?string, kueche: ?string}>
     */
    protected function belegung(MealPlan $plan, ?int $ausser = null): Collection
    {
        return $plan->slots()
            ->whereNotNull('recipe_id')
            ->when($ausser !== null, fn ($q) => $q->whereKeyNot($ausser))
            ->with('recipe.tags')
            ->get()
            ->map(fn (MealPlanSlot $slot) => [
                'datum' => $slot->plan_date,
                'protein' => $slot->recipe ? $this->tag($slot->recipe, 'protein') : null,
                'kueche' => $slot->recipe ? $this->tag($slot->recipe, 'cuisine') : null,
            ]);
    }

    /**
     * @return Collection<int, MealPlanSlot>
     */
    protected function offeneSlots(MealPlan $plan, string $mahlzeit): Collection
    {
        return $plan->slots()
            ->where('meal', $mahlzeit)
            ->where('is_locked', false)
            ->whereNull('recipe_id')
            ->whereNull('custom_text')
            ->orderBy('plan_date')
            ->get();
    }

    protected function tag(Recipe $rezept, string $typ): ?string
    {
        return $rezept->tags->firstWhere('type', $typ)?->name;
    }
}
