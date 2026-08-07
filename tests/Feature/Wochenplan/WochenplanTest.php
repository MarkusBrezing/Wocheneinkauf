<?php

namespace Tests\Feature\Wochenplan;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\MealPlan\Models\MealPlan;
use App\Modules\MealPlan\Models\MealPlanSlot;
use App\Modules\MealPlan\Models\MealPlanSuggestion;
use App\Modules\MealPlan\Services\MealPlanManager;
use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Models\RecipeIngredient;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wochenplan: F-01 bis F-15.
 */
class WochenplanTest extends TestCase
{
    use RefreshDatabase;

    public function test_die_planwoche_entsteht_beim_ersten_aufruf(): void
    {
        [$user, $family] = $this->familie();

        $this->actingAs($user)->get('/wochenplan')->assertOk()->assertSee('Wochenplan');

        $plan = $this->imKontext($user, fn () => MealPlan::firstOrFail());

        // Sieben Tage, eine Mahlzeit -- mehr will die Standardwoche nicht.
        $this->assertSame(7, $this->imKontext($user, fn () => $plan->slots()->count()));
        // Die Woche faengt dort an, wo die Familie sie anfangen laesst.
        $this->assertSame($family->fresh()->week_starts_on, (int) $plan->week_start->dayOfWeekIso);
    }

    public function test_die_woche_beginnt_am_eingestellten_wochentag(): void
    {
        [$user, $family] = $this->familie();
        $family->forceFill(['week_starts_on' => 6])->save();

        $this->actingAs($user)->get('/wochenplan?woche=2026-08-05')->assertOk();

        $plan = $this->imKontext($user, fn () => MealPlan::firstOrFail());

        // Mittwoch, der 5.8.2026 gehoert zur Woche ab Samstag, dem 1.8.
        $this->assertSame('2026-08-01', $plan->week_start->toDateString());
    }

    public function test_derselbe_aufruf_legt_keine_zweite_woche_an(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/wochenplan');
        $this->actingAs($user)->get('/wochenplan');

        $this->assertSame(1, $this->imKontext($user, fn () => MealPlan::count()));
        $this->assertSame(7, $this->imKontext($user, fn () => MealPlanSlot::count()));
    }

    public function test_zusaetzliche_mahlzeiten_ergaenzen_die_vorhandene_woche(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/wochenplan')->assertOk();
        $this->actingAs($user)->get('/wochenplan?mahlzeiten[]=lunch&mahlzeiten[]=dinner')->assertOk();

        // Die sieben Abendessen bleiben, die Mittagessen kommen dazu.
        $this->assertSame(14, $this->imKontext($user, fn () => MealPlanSlot::count()));
    }

    public function test_die_druckansicht_zeigt_die_woche(): void
    {
        [$user] = $this->familie();
        $rezept = $this->rezept($user, 'Linsencurry');
        $slot = $this->ersterSlot($user);

        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $rezept->id]);

        $this->actingAs($user)->get('/wochenplan/drucken')
            ->assertOk()
            ->assertSee('Linsencurry')
            ->assertSee('Wochenplan');
    }

    public function test_slot_mit_rezept_belegen(): void
    {
        [$user] = $this->familie();
        $rezept = $this->rezept($user, 'Linsencurry');
        $slot = $this->ersterSlot($user);

        $this->actingAs($user)->from('/wochenplan')
            ->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $rezept->id, 'servings' => 6])
            ->assertRedirect('/wochenplan')->assertSessionHasNoErrors();

        $slot->refresh();

        $this->assertSame($rezept->id, $slot->recipe_id);
        $this->assertSame(6, $slot->servings);
        $this->assertSame('manual', $slot->origin);
    }

    public function test_freitext_und_rezept_schliessen_sich_aus(): void
    {
        [$user] = $this->familie();
        $rezept = $this->rezept($user, 'Linsencurry');
        $slot = $this->ersterSlot($user);

        $this->actingAs($user)->from('/wochenplan')
            ->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $rezept->id]);
        $this->actingAs($user)->from('/wochenplan')
            ->put('/wochenplan/slots/'.$slot->id, ['custom_text' => 'Essen gehen']);

        $slot->refresh();

        $this->assertNull($slot->recipe_id);
        $this->assertSame('Essen gehen', $slot->custom_text);
    }

    public function test_slot_leeren_laesst_die_sperre_stehen(): void
    {
        [$user] = $this->familie();
        $rezept = $this->rezept($user, 'Linsencurry');
        $slot = $this->ersterSlot($user);

        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $rezept->id]);
        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/slots/'.$slot->id.'/sperren');
        $this->actingAs($user)->from('/wochenplan')->delete('/wochenplan/slots/'.$slot->id);

        $slot->refresh();

        $this->assertNull($slot->recipe_id);
        $this->assertTrue($slot->is_locked);
    }

    public function test_vorschlag_fuellt_nur_offene_und_ungesperrte_slots(): void
    {
        [$user] = $this->familie();

        foreach (['Linsencurry', 'Kartoffelsuppe', 'Ofengemüse', 'Nudelauflauf'] as $name) {
            $this->rezept($user, $name);
        }

        $slots = $this->slots($user);
        $handbelegt = $slots[0];
        $gesperrt = $slots[1];

        $eigenes = $this->rezept($user, 'Pizza');

        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$handbelegt->id, ['recipe_id' => $eigenes->id]);
        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/slots/'.$gesperrt->id.'/sperren');

        $plan = $this->imKontext($user, fn () => MealPlan::firstOrFail());
        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/'.$plan->public_id.'/vorschlag', ['meal' => 'dinner']);

        $lauf = $this->imKontext($user, fn () => MealPlanSuggestion::firstOrFail());
        $angesprochen = collect($lauf->result)->pluck('slot_id');

        $this->assertFalse($angesprochen->contains($handbelegt->id));
        $this->assertFalse($angesprochen->contains($gesperrt->id));
        // Fuenf offene Slots, aber nur vier Rezepte uebrig -- mehr geht nicht.
        $this->assertCount(4, $lauf->result);
    }

    public function test_vorschlag_annehmen_traegt_die_rezepte_ein(): void
    {
        [$user] = $this->familie();
        $this->rezept($user, 'Linsencurry');
        $this->rezept($user, 'Kartoffelsuppe');

        $plan = $this->plan($user);
        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/'.$plan->public_id.'/vorschlag');

        $lauf = $this->imKontext($user, fn () => MealPlanSuggestion::firstOrFail());

        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/vorschlag/'.$lauf->id.'/annehmen')
            ->assertRedirect('/wochenplan');

        $belegt = $this->imKontext($user, fn () => MealPlanSlot::whereNotNull('recipe_id')->get());

        $this->assertCount(2, $belegt);
        $this->assertSame(['suggested', 'suggested'], $belegt->pluck('origin')->all());
        $this->assertNotNull($this->imKontext($user, fn () => MealPlanSuggestion::firstOrFail())->accepted_at);
    }

    public function test_jedes_gericht_kommt_in_einer_woche_nur_einmal(): void
    {
        [$user] = $this->familie();

        foreach (['Linsencurry', 'Kartoffelsuppe', 'Ofengemüse'] as $name) {
            $this->rezept($user, $name);
        }

        $plan = $this->plan($user);
        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/'.$plan->public_id.'/vorschlag');

        $lauf = $this->imKontext($user, fn () => MealPlanSuggestion::firstOrFail());
        $rezepte = collect($lauf->result)->pluck('recipe_id');

        $this->assertSame($rezepte->count(), $rezepte->unique()->count());
    }

    public function test_neu_wuerfeln_liefert_ein_anderes_rezept(): void
    {
        [$user] = $this->familie();
        $a = $this->rezept($user, 'Linsencurry');
        $this->rezept($user, 'Kartoffelsuppe');

        $slot = $this->ersterSlot($user);
        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $a->id]);

        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/slots/'.$slot->id.'/wuerfeln')
            ->assertRedirect('/wochenplan');

        $slot->refresh();

        $this->assertNotSame($a->id, $slot->recipe_id);
        $this->assertSame('suggested', $slot->origin);
    }

    public function test_ausgeschlossene_rezepte_werden_nie_vorgeschlagen(): void
    {
        [$user] = $this->familie();
        $verbannt = $this->rezept($user, 'Grünkohl');

        $this->actingAs($user)->from('/wochenplan/historie')
            ->post('/wochenplan/rezepte/'.$verbannt->public_id.'/ausschliessen')
            ->assertRedirect('/wochenplan/historie');

        $plan = $this->plan($user);
        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/'.$plan->public_id.'/vorschlag');

        $lauf = $this->imKontext($user, fn () => MealPlanSuggestion::firstOrFail());

        $this->assertSame([], $lauf->result);
        // Von Hand einsetzen bleibt erlaubt -- der Ausschluss gilt nur dem
        // Generator.
        $slot = $this->ersterSlot($user);
        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $verbannt->id]);
        $this->assertSame($verbannt->id, $slot->refresh()->recipe_id);
    }

    public function test_der_plan_landet_dedupliziert_auf_der_einkaufsliste(): void
    {
        [$user] = $this->familie();

        $curry = $this->rezept($user, 'Linsencurry', [
            ['raw_name' => 'Zwiebeln', 'amount' => 2, 'unit' => 'Stk'],
            ['raw_name' => 'Linsen', 'amount' => 250, 'unit' => 'g'],
        ]);
        $suppe = $this->rezept($user, 'Kartoffelsuppe', [
            ['raw_name' => 'Zwiebeln', 'amount' => 1, 'unit' => 'Stk'],
            ['raw_name' => 'Kartoffeln', 'amount' => 1, 'unit' => 'kg'],
        ]);

        $slots = $this->slots($user);
        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$slots[0]->id, ['recipe_id' => $curry->id]);
        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$slots[1]->id, ['recipe_id' => $suppe->id]);

        $plan = $this->plan($user);
        $this->actingAs($user)->post('/wochenplan/'.$plan->public_id.'/auf-die-liste')
            ->assertRedirect('/einkaufsliste');

        $eintraege = $this->listeneintraege($user);

        // Drei statt vier: die Zwiebeln aus beiden Rezepten fallen zusammen.
        $this->assertCount(3, $eintraege);
        $this->assertSame('3.000', $eintraege->firstWhere('raw_name', 'Zwiebeln')->amount);
        $this->assertNotNull($plan->fresh()->list_id);
    }

    public function test_gekochte_gerichte_kommen_nicht_mehr_auf_die_liste(): void
    {
        [$user] = $this->familie();
        $curry = $this->rezept($user, 'Linsencurry', [['raw_name' => 'Linsen', 'amount' => 250, 'unit' => 'g']]);

        $slot = $this->ersterSlot($user);
        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $curry->id]);
        $this->actingAs($user)->from('/wochenplan')->post('/wochenplan/slots/'.$slot->id.'/gekocht');

        $this->assertTrue($slot->refresh()->is_cooked);
        $this->assertNotNull($slot->cooked_at);

        $plan = $this->plan($user);
        $this->actingAs($user)->post('/wochenplan/'.$plan->public_id.'/auf-die-liste');

        $this->assertCount(0, $this->listeneintraege($user));
        // Die Liste entsteht trotzdem -- sie ist nur leer.
        $this->assertNotNull($plan->fresh()->list_id);
    }

    public function test_plan_in_die_folgewoche_kopieren(): void
    {
        [$user] = $this->familie();
        $rezept = $this->rezept($user, 'Linsencurry');

        $slot = $this->ersterSlot($user);
        $this->actingAs($user)->from('/wochenplan')->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $rezept->id]);

        $plan = $this->plan($user);
        $this->actingAs($user)->post('/wochenplan/'.$plan->public_id.'/kopieren')->assertRedirect();

        $folge = $this->imKontext($user, fn () => MealPlan::where('week_start', '>', $plan->week_start)->firstOrFail());
        $kopie = $this->imKontext($user, fn () => $folge->slots()->belegt()->firstOrFail());

        $this->assertSame($rezept->id, $kopie->recipe_id);
        $this->assertSame($slot->plan_date->copy()->addWeek()->toDateString(), $kopie->plan_date->toDateString());
    }

    public function test_historie_zeigt_wann_ein_gericht_zuletzt_auf_dem_tisch_stand(): void
    {
        [$user] = $this->familie();
        $rezept = $this->rezept($user, 'Linsencurry');

        // Ein Slot in der Vergangenheit -- die Historie zaehlt nur, was schon
        // war, nicht was noch kommt.
        $this->imKontext($user, function () use ($rezept) {
            $plan = app(MealPlanManager::class)->forWeek(now()->subWeeks(2), $rezept->family);
            $slot = $plan->slots()->first();
            $slot->forceFill(['recipe_id' => $rezept->id])->save();
        });

        $this->actingAs($user)->get('/wochenplan/historie')
            ->assertOk()
            ->assertSee('Linsencurry');
    }

    public function test_ein_fremder_plan_endet_in_404(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $slot = $this->ersterSlot($eigen);
        $plan = $this->plan($eigen);

        $this->actingAs($fremd)->put('/wochenplan/slots/'.$slot->id, ['custom_text' => 'Kaperfahrt'])
            ->assertNotFound();
        $this->actingAs($fremd)->post('/wochenplan/'.$plan->public_id.'/vorschlag')->assertNotFound();
        $this->actingAs($fremd)->post('/wochenplan/'.$plan->public_id.'/auf-die-liste')->assertNotFound();

        $this->assertNull($slot->fresh()->custom_text);
    }

    public function test_ein_fremdes_rezept_landet_nicht_im_plan(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $fremdesRezept = $this->rezept($fremd, 'Geheimrezept');
        $slot = $this->ersterSlot($eigen);

        $this->actingAs($eigen)->from('/wochenplan')
            ->put('/wochenplan/slots/'.$slot->id, ['recipe_id' => $fremdesRezept->id])
            ->assertRedirect('/wochenplan');

        // Die ID trifft ins Leere, statt fremde Daten in den Plan zu holen.
        $this->assertNull($slot->refresh()->recipe_id);
    }

    // ── Helfer ──────────────────────────────────────────────────────────

    /**
     * @return array{0: User, 1: Family}
     */
    protected function familie(string $email = 'owner@example.test', string $name = 'Familie Test'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $family = app(FamilyProvisioner::class)->createFor($user, $name);

        return [$user->refresh(), $family];
    }

    /**
     * @template TReturn
     *
     * @param  Closure():TReturn  $callback
     * @return TReturn
     */
    protected function imKontext(User $user, Closure $callback): mixed
    {
        return app(FamilyContext::class)->runFor($user->current_family_id, $callback);
    }

    /**
     * @param  array<int, array<string, mixed>>  $zutaten
     */
    protected function rezept(User $user, string $name, array $zutaten = []): Recipe
    {
        return $this->imKontext($user, function () use ($name, $zutaten) {
            $rezept = Recipe::factory()->create(['name' => $name, 'servings' => 4]);

            foreach ($zutaten as $i => $zutat) {
                $produkt = Product::matchingName($zutat['raw_name'])->first()
                    ?? Product::factory()->create(['name' => $zutat['raw_name']]);

                $eintrag = new RecipeIngredient($zutat + ['sort' => $i, 'product_id' => $produkt->id]);
                $eintrag->recipe_id = $rezept->id;
                $eintrag->family_id = $rezept->family_id;
                $eintrag->save();
            }

            return $rezept->refresh();
        });
    }

    protected function plan(User $user): MealPlan
    {
        $this->actingAs($user)->get('/wochenplan');

        return $this->imKontext($user, fn () => MealPlan::orderBy('week_start')->firstOrFail());
    }

    /**
     * @return Collection<int, MealPlanSlot>
     */
    protected function slots(User $user)
    {
        $plan = $this->plan($user);

        return $this->imKontext($user, fn () => $plan->slots()->orderBy('plan_date')->get());
    }

    protected function ersterSlot(User $user): MealPlanSlot
    {
        return $this->slots($user)->first();
    }

    /**
     * @return Collection<int, ShoppingListItem>
     */
    protected function listeneintraege(User $user)
    {
        return $this->imKontext($user, fn () => ShoppingList::active()->firstOrFail()->items()->orderBy('id')->get());
    }
}
