<?php

namespace App\Http\Controllers;

use App\Modules\MealPlan\Models\MealPlan;
use App\Modules\MealPlan\Models\MealPlanSlot;
use App\Modules\MealPlan\Models\MealPlanSuggestion;
use App\Modules\MealPlan\Services\MealPlanManager;
use App\Modules\Recipes\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Der Wochenplan (F-01 … F-15).
 *
 * Der Plan gehoert dem Alltag, nicht der Verwaltung: Jedes Mitglied darf
 * planen, wuerfeln und abhaken -- wie beim Vorrat und bei den Rezepten.
 */
class MealPlanController extends Controller
{
    public function __construct(protected MealPlanManager $plaene) {}

    /**
     * Die Planwoche anzeigen (F-01, F-10).
     */
    public function index(Request $request)
    {
        $family = $this->currentFamily($request);

        $plan = $this->plaene->forWeek(
            $this->woche($request),
            $family,
            $request->user(),
            $this->mahlzeiten($request),
        );

        $plan->load(['slots.recipe.tags', 'list']);

        return view('wochenplan.index', [
            'plan' => $plan,
            'slots' => $plan->slots->groupBy(fn (MealPlanSlot $s) => $s->plan_date->toDateString()),
            'mahlzeiten' => $plan->slots->pluck('meal')->unique()->sortBy(
                fn (string $m) => array_search($m, array_keys(MealPlan::MAHLZEITEN), true)
            )->values(),
            // Fuer die Auswahl im Slot: Rezepte kompakt, nach Name sortiert.
            'rezepte' => Recipe::orderBy('name')->get(['id', 'name', 'servings']),
            'vorschlag' => $plan->suggestions()->whereNull('accepted_at')->first(),
            'vorwoche' => $plan->week_start->copy()->subWeek()->toDateString(),
            'folgewoche' => $plan->week_start->copy()->addWeek()->toDateString(),
        ]);
    }

    /**
     * Historie: was wann zuletzt auf dem Tisch stand (F-12).
     *
     * Zugleich die Stelle, an der Rezepte dauerhaft aus den Vorschlaegen
     * genommen werden (F-15) -- die Frage "warum kommt das staendig?" stellt
     * sich genau hier.
     */
    public function history(Request $request)
    {
        $this->currentFamily($request);

        // Nur vergangene Tage zaehlen: Was naechste Woche geplant ist, stand
        // noch nicht auf dem Tisch.
        $zuletzt = MealPlanSlot::query()
            ->whereNotNull('recipe_id')
            ->whereDate('plan_date', '<=', now())
            ->selectRaw('recipe_id, MAX(plan_date) as zuletzt, COUNT(*) as anzahl')
            ->groupBy('recipe_id')
            ->get()
            ->keyBy('recipe_id');

        $rezepte = Recipe::orderBy('name')->get()
            ->map(function (Recipe $rezept) use ($zuletzt) {
                $eintrag = $zuletzt->get($rezept->getKey());
                $datum = $eintrag ? Carbon::parse($eintrag->zuletzt) : null;

                return [
                    'rezept' => $rezept,
                    'zuletzt' => $datum,
                    'tage' => $datum?->startOfDay()->diffInDays(now()->startOfDay()),
                    'anzahl' => (int) ($eintrag->anzahl ?? 0),
                ];
            })
            // Was am laengsten her ist, zuerst -- nie Gekochtes ganz oben.
            ->sortByDesc(fn (array $z) => $z['zuletzt'] === null ? PHP_INT_MAX : -$z['zuletzt']->timestamp)
            ->values();

        return view('wochenplan.historie', ['rezepte' => $rezepte]);
    }

    /**
     * Druckansicht des Plans (F-13).
     */
    public function print(Request $request)
    {
        $family = $this->currentFamily($request);

        $plan = $this->plaene->forWeek($this->woche($request), $family, $request->user(), $this->mahlzeiten($request));
        $plan->load('slots.recipe');

        return view('wochenplan.drucken', [
            'plan' => $plan,
            'slots' => $plan->slots->groupBy(fn (MealPlanSlot $s) => $s->plan_date->toDateString()),
        ]);
    }

    /**
     * Slot belegen (F-02, F-03).
     */
    public function fill(Request $request, MealPlanSlot $slot)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'recipe_id' => ['nullable', 'integer'],
            'custom_text' => ['nullable', 'string', 'max:160'],
            'servings' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $this->plaene->fill(
            slot: $slot,
            rezeptId: $daten['recipe_id'] ?? null,
            text: $daten['custom_text'] ?? null,
            portionen: $daten['servings'] ?? null,
        );

        return back()->with('status', 'Eintrag gespeichert.');
    }

    /**
     * Slot leeren (F-04).
     */
    public function clear(Request $request, MealPlanSlot $slot)
    {
        $this->currentFamily($request);
        $this->plaene->clear($slot);

        return back()->with('status', 'Eintrag entfernt.');
    }

    /**
     * Slot sperren oder freigeben (F-05).
     */
    public function lock(Request $request, MealPlanSlot $slot)
    {
        $this->currentFamily($request);
        $slot = $this->plaene->toggleLock($slot);

        return back()->with('status', $slot->is_locked ? 'Eintrag gesperrt.' : 'Sperre aufgehoben.');
    }

    /**
     * Gekocht-Haken (F-11).
     */
    public function cooked(Request $request, MealPlanSlot $slot)
    {
        $this->currentFamily($request);
        $slot = $this->plaene->toggleCooked($slot);

        return back()->with('status', $slot->is_cooked ? 'Als gekocht vermerkt.' : 'Haken entfernt.');
    }

    /**
     * Einzelnen Slot neu wuerfeln (F-07).
     */
    public function reroll(Request $request, MealPlanSlot $slot)
    {
        $this->currentFamily($request);

        $neu = $this->plaene->reroll($slot);

        return back()->with('status', $neu === null
            ? 'Kein passendes Rezept mehr übrig.'
            : 'Neuer Vorschlag: '.$neu->fresh('recipe')->anzeige());
    }

    /**
     * Vorschlag fuer die Woche erzeugen (F-06).
     */
    public function suggest(Request $request, MealPlan $plan)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'meal' => ['nullable', 'string', 'in:'.implode(',', array_keys(MealPlan::MAHLZEITEN))],
        ]);

        $lauf = $this->plaene->suggest($plan, $daten['meal'] ?? 'dinner', $request->user());

        return back()->with('status', $lauf->result === []
            ? 'Es gibt keine passenden Rezepte -- lege zuerst welche an.'
            : count($lauf->result).' Vorschläge stehen bereit.');
    }

    /**
     * Vorschlag annehmen (F-08).
     */
    public function accept(Request $request, MealPlanSuggestion $vorschlag)
    {
        $this->currentFamily($request);

        $anzahl = $this->plaene->accept($vorschlag);

        return back()->with('status', $anzahl.' Einträge übernommen.');
    }

    /**
     * Vorschlag verwerfen -- er bleibt als Ausschluss stehen (F-07).
     */
    public function discard(Request $request, MealPlanSuggestion $vorschlag)
    {
        $this->currentFamily($request);

        $lauf = $this->plaene->suggest($vorschlag->mealPlan, $vorschlag->params['meal'] ?? 'dinner', $request->user());

        return back()->with('status', $lauf->result === []
            ? 'Keine weiteren Rezepte übrig.'
            : 'Neu gewürfelt.');
    }

    /**
     * Den Plan auf die Einkaufsliste uebernehmen (F-09).
     */
    public function toList(Request $request, MealPlan $plan)
    {
        $this->currentFamily($request);

        $anzahl = $this->plaene->toShoppingList($plan, $request->user());

        return redirect()->route('einkaufsliste.index')
            ->with('status', $anzahl.' Zutaten aus dem Wochenplan übernommen.');
    }

    /**
     * Plan in die Folgewoche kopieren (F-14).
     */
    public function copy(Request $request, MealPlan $plan)
    {
        $family = $this->currentFamily($request);

        $ziel = $this->plaene->copyToNextWeek($plan, $family, $request->user());

        return redirect()->route('wochenplan.index', ['woche' => $ziel->week_start->toDateString()])
            ->with('status', 'In die Folgewoche kopiert.');
    }

    /**
     * "Nie vorschlagen" umschalten (F-15).
     */
    public function exclude(Request $request, Recipe $rezept)
    {
        $this->currentFamily($request);

        $rezept->forceFill(['exclude_from_suggestions' => ! $rezept->exclude_from_suggestions])->save();

        return back()->with('status', $rezept->exclude_from_suggestions
            ? $rezept->name.' wird nicht mehr vorgeschlagen.'
            : $rezept->name.' kommt wieder in die Vorschläge.');
    }

    /**
     * Der Tag, dessen Woche gezeigt wird. Ohne Angabe: heute.
     */
    protected function woche(Request $request): Carbon
    {
        $wert = (string) $request->query('woche', '');

        try {
            return $wert === '' ? now() : Carbon::parse($wert);
        } catch (\Throwable $e) {
            // Ein unbrauchbares Datum in der URL ist kein Fehlerfall, sondern
            // ein Tippfehler -- die laufende Woche ist die richtige Antwort.
            return now();
        }
    }

    /**
     * Welche Mahlzeiten geplant werden. Standard ist das Abendessen -- alles
     * andere waere fuer die meisten Familien Leerlauf.
     *
     * @return array<int, string>
     */
    protected function mahlzeiten(Request $request): array
    {
        $gewaehlt = array_intersect(
            (array) $request->query('mahlzeiten', ['dinner']),
            array_keys(MealPlan::MAHLZEITEN),
        );

        return $gewaehlt === [] ? ['dinner'] : array_values($gewaehlt);
    }
}
