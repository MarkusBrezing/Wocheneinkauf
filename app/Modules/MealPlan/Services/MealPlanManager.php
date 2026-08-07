<?php

namespace App\Modules\MealPlan\Services;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Modules\MealPlan\Models\MealPlan;
use App\Modules\MealPlan\Models\MealPlanSlot;
use App\Modules\MealPlan\Models\MealPlanSuggestion;
use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Services\RecipeToList;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Der Wochenplan (F-01 … F-11).
 *
 * Ein Plan entsteht mit allen Slots auf einmal -- sieben Tage mal die
 * gewuenschten Mahlzeiten. Leere Slots sind Datensaetze, keine Luecken: So
 * lassen sie sich sperren, bewerten und einzeln neu wuerfeln, ohne dass beim
 * Belegen erst etwas angelegt werden muesste.
 */
class MealPlanManager
{
    public function __construct(
        protected SuggestionEngine $engine,
        protected ShoppingListManager $listen,
        protected RecipeToList $rezeptUebernahme,
    ) {}

    /**
     * Den Plan einer Woche holen oder anlegen (F-01).
     *
     * @param  array<int, string>  $mahlzeiten
     */
    public function forWeek(Carbon $tag, Family $family, ?User $user = null, array $mahlzeiten = ['dinner']): MealPlan
    {
        $start = $this->wochenstart($tag, $family);

        return DB::transaction(function () use ($start, $user, $mahlzeiten) {
            $plan = MealPlan::where('week_start', $start->toDateString())->first();

            if ($plan === null) {
                $plan = new MealPlan(['week_start' => $start->toDateString()]);
                $plan->created_by = $user?->getKey();
                $plan->save();
            }

            $this->slotsErgaenzen($plan, $mahlzeiten);

            return $plan->refresh();
        });
    }

    /**
     * Slot belegen -- mit Rezept oder Freitext (F-02, F-03).
     *
     * Beides gleichzeitig waere widerspruechlich: Wer ein Rezept einsetzt,
     * loescht damit den Freitext und umgekehrt.
     */
    public function fill(MealPlanSlot $slot, ?int $rezeptId = null, ?string $text = null, ?int $portionen = null): MealPlanSlot
    {
        // Ueber den Global Scope nachgeladen -- ein fremdes Rezept trifft ins
        // Leere statt in den Plan.
        $rezept = $rezeptId ? Recipe::whereKey($rezeptId)->first() : null;

        $slot->forceFill([
            'recipe_id' => $rezept?->getKey(),
            'custom_text' => $rezept === null ? ($text ?: null) : null,
            'servings' => $portionen,
            'origin' => 'manual',
        ])->save();

        return $slot;
    }

    /**
     * Slot leeren (F-04) -- die Sperre bleibt, sie gilt dem Platz.
     */
    public function clear(MealPlanSlot $slot): MealPlanSlot
    {
        $slot->forceFill([
            'recipe_id' => null,
            'custom_text' => null,
            'servings' => null,
            'is_cooked' => false,
            'cooked_at' => null,
            'origin' => 'manual',
        ])->save();

        return $slot;
    }

    public function toggleLock(MealPlanSlot $slot): MealPlanSlot
    {
        $slot->forceFill(['is_locked' => ! $slot->is_locked])->save();

        return $slot;
    }

    /**
     * Slot als gekocht markieren (F-11).
     */
    public function toggleCooked(MealPlanSlot $slot): MealPlanSlot
    {
        $gekocht = ! $slot->is_cooked;

        $slot->forceFill([
            'is_cooked' => $gekocht,
            'cooked_at' => $gekocht ? now() : null,
        ])->save();

        return $slot;
    }

    /**
     * Vorschlag erzeugen und protokollieren (F-06).
     *
     * Der Lauf wird festgehalten, bevor er angenommen ist -- nur so laesst
     * sich "nochmal wuerfeln" von "so lassen" unterscheiden.
     */
    public function suggest(MealPlan $plan, string $mahlzeit = 'dinner', ?User $user = null): MealPlanSuggestion
    {
        $verworfen = $this->verworfeneRezepte($plan);
        $vorschlag = $this->engine->suggest($plan, $mahlzeit, $verworfen);

        $lauf = new MealPlanSuggestion([
            'strategy' => 'balanced',
            'params' => ['meal' => $mahlzeit, 'ausgeschlossen' => $verworfen],
            'result' => $vorschlag,
        ]);

        $lauf->meal_plan_id = $plan->getKey();
        $lauf->family_id = $plan->family_id;
        $lauf->created_by = $user?->getKey();
        $lauf->save();

        return $lauf;
    }

    /**
     * Vorschlag annehmen (F-08): die Slots werden belegt.
     *
     * @return int Anzahl belegter Slots
     */
    public function accept(MealPlanSuggestion $lauf): int
    {
        return DB::transaction(function () use ($lauf) {
            $belegt = 0;

            foreach ($lauf->result as $eintrag) {
                $slot = MealPlanSlot::whereKey($eintrag['slot_id'])->first();

                // Wer inzwischen selbst etwas eingetragen oder gesperrt hat,
                // behaelt es -- der Vorschlag ist aelter als die Entscheidung.
                if ($slot === null || $slot->is_locked || $slot->istBelegt()) {
                    continue;
                }

                $slot->forceFill([
                    'recipe_id' => $eintrag['recipe_id'],
                    'origin' => 'suggested',
                ])->save();

                $belegt++;
            }

            $lauf->forceFill(['accepted_at' => now()])->save();

            return $belegt;
        });
    }

    /**
     * Einen einzelnen Slot neu wuerfeln (F-07).
     */
    public function reroll(MealPlanSlot $slot): ?MealPlanSlot
    {
        $treffer = $this->engine->reroll($slot, $this->verworfeneRezepte($slot->mealPlan));

        if ($treffer === null) {
            return null;
        }

        $slot->forceFill([
            'recipe_id' => $treffer['recipe_id'],
            'custom_text' => null,
            'origin' => 'suggested',
        ])->save();

        return $slot;
    }

    /**
     * Den Plan in die Einkaufsliste uebernehmen (F-09).
     *
     * Dedupliziert und um Vorratsbestaende bereinigt -- beides erledigt
     * RecipeToList, das dafuer schon die Regeln kennt (D-05, D-06).
     *
     * @return int Anzahl uebernommener Zutaten
     */
    public function toShoppingList(MealPlan $plan, ?User $user = null): int
    {
        return DB::transaction(function () use ($plan, $user) {
            $liste = $this->listen->current($user);
            $anzahl = 0;

            foreach ($plan->slots()->whereNotNull('recipe_id')->with('recipe')->get() as $slot) {
                if ($slot->recipe === null || $slot->is_cooked) {
                    continue;
                }

                $anzahl += $this->rezeptUebernahme->transfer(
                    rezept: $slot->recipe,
                    liste: $liste,
                    portionen: $slot->servings,
                );
            }

            $plan->forceFill([
                'list_id' => $liste->getKey(),
                'status' => 'active',
            ])->save();

            return $anzahl;
        });
    }

    /**
     * Plan in die Folgewoche kopieren (F-14).
     */
    public function copyToNextWeek(MealPlan $plan, Family $family, ?User $user = null): MealPlan
    {
        return DB::transaction(function () use ($plan, $family, $user) {
            $ziel = $this->forWeek($plan->week_start->copy()->addWeek(), $family, $user);

            foreach ($plan->slots()->belegt()->get() as $slot) {
                $zielSlot = $ziel->slots()
                    ->where('plan_date', $slot->plan_date->copy()->addWeek()->toDateString())
                    ->where('meal', $slot->meal)
                    ->first();

                if ($zielSlot === null || $zielSlot->istBelegt()) {
                    continue;
                }

                $zielSlot->forceFill([
                    'recipe_id' => $slot->recipe_id,
                    'custom_text' => $slot->custom_text,
                    'servings' => $slot->servings,
                    'origin' => 'manual',
                ])->save();
            }

            return $ziel->refresh();
        });
    }

    /**
     * Fehlende Slots anlegen -- auch wenn nachtraeglich eine Mahlzeit
     * dazukommt.
     *
     * @param  array<int, string>  $mahlzeiten
     */
    protected function slotsErgaenzen(MealPlan $plan, array $mahlzeiten): void
    {
        $vorhanden = $plan->slots()->get()
            ->map(fn (MealPlanSlot $s) => $s->plan_date->toDateString().'|'.$s->meal)
            ->all();

        foreach ($plan->tage() as $tag) {
            foreach ($mahlzeiten as $mahlzeit) {
                if (in_array($tag->toDateString().'|'.$mahlzeit, $vorhanden, true)) {
                    continue;
                }

                $slot = new MealPlanSlot;

                $slot->meal_plan_id = $plan->getKey();
                $slot->family_id = $plan->family_id;
                $slot->plan_date = $tag->toDateString();
                $slot->meal = $mahlzeit;
                $slot->save();
            }
        }
    }

    /**
     * Rezepte aus verworfenen Vorschlaegen -- sie kommen beim naechsten Wurf
     * nicht wieder.
     *
     * @return array<int, int>
     */
    protected function verworfeneRezepte(MealPlan $plan): array
    {
        return $plan->suggestions()
            ->whereNull('accepted_at')
            ->get()
            ->flatMap(fn (MealPlanSuggestion $lauf) => collect($lauf->result)->pluck('recipe_id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Der erste Tag der Woche, in der dieser Tag liegt -- nach der
     * Familieneinstellung (1 = Montag … 7 = Sonntag).
     */
    protected function wochenstart(Carbon $tag, Family $family): Carbon
    {
        $start = (int) ($family->week_starts_on ?: 1);
        $datum = $tag->copy()->startOfDay();

        // Wie viele Tage liegt der letzte Wochenstart zurueck?
        $abstand = ($datum->dayOfWeekIso - $start + 7) % 7;

        return $datum->subDays($abstand);
    }
}
