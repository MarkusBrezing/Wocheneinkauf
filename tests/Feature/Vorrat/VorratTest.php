<?php

namespace Tests\Feature\Vorrat;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pantry\Models\PantryItem;
use App\Modules\Pantry\Models\PantryMovement;
use App\Modules\Pantry\Services\PantryManager;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vorrat: E-01, E-02, E-09, E-10, E-11.
 */
class VorratTest extends TestCase
{
    use RefreshDatabase;

    public function test_vorratsposition_anlegen_und_bestand_setzen(): void
    {
        [$user] = $this->familie();
        $mehl = $this->artikel($user, 'Mehl');

        $this->actingAs($user)->post('/vorrat', [
            'product_id' => $mehl->id,
            'location' => 'Speisekammer',
            'unit' => 'kg',
            'target_qty' => 3,
            'min_qty' => 1,
            'current_qty' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $position = $this->imKontext($user, fn () => PantryItem::firstOrFail());

        $this->assertSame('Speisekammer', $position->location);
        $this->assertSame('2.000', $position->current_qty);
        $this->assertSame('3.000', $position->target_qty);
        // Auch das Setzen des Anfangsbestands ist eine Buchung.
        $this->assertSame(1, $this->imKontext($user, fn () => PantryMovement::count()));
    }

    public function test_derselbe_artikel_darf_an_zwei_orten_liegen(): void
    {
        [$user] = $this->familie();
        $nudeln = $this->artikel($user, 'Nudeln');

        $this->actingAs($user)->post('/vorrat', ['product_id' => $nudeln->id, 'location' => 'Speisekammer', 'target_qty' => 2]);
        $this->actingAs($user)->post('/vorrat', ['product_id' => $nudeln->id, 'location' => 'Keller', 'target_qty' => 5]);

        $this->assertSame(2, $this->imKontext($user, fn () => PantryItem::count()));
    }

    public function test_die_datenbank_verhindert_dieselbe_position_zweimal(): void
    {
        [$user, $family] = $this->familie();
        $nudeln = $this->artikel($user, 'Nudeln');

        $this->actingAs($user)->post('/vorrat', ['product_id' => $nudeln->id, 'location' => 'Keller', 'target_qty' => 2]);

        $this->expectException(QueryException::class);

        DB::table('pantry_items')->insert([
            'family_id' => $family->id, 'product_id' => $nudeln->id, 'location' => 'Keller',
            'target_qty' => 1, 'min_qty' => 0, 'current_qty' => 0, 'is_active' => 1, 'sort' => 0,
        ]);
    }

    public function test_bestand_wird_gebucht_nicht_gesetzt(): void
    {
        [$user] = $this->familie();
        $position = $this->position($user, 'Mehl', bestand: 3);

        $this->actingAs($user)->post('/vorrat/'.$position->id.'/buchen', [
            'delta' => -1, 'reason' => 'consumption',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('2.000', $position->fresh()->current_qty);

        $buchung = $this->imKontext($user, fn () => PantryMovement::latest('id')->firstOrFail());

        // Das Bewegungskonto beantwortet die Frage, die ein Zahlenfeld
        // offenlässt: warum hat sich die Menge geändert?
        $this->assertSame('-1.000', $buchung->qty_delta);
        $this->assertSame('consumption', $buchung->reason);
        $this->assertSame($user->id, $buchung->created_by);
    }

    public function test_der_bestand_faellt_nicht_unter_null(): void
    {
        [$user] = $this->familie();
        $position = $this->position($user, 'Mehl', bestand: 1);

        $this->actingAs($user)->post('/vorrat/'.$position->id.'/buchen', ['delta' => -5, 'reason' => 'consumption']);

        $this->assertSame('0.000', $position->fresh()->current_qty);
        // Gebucht wird, was tatsächlich passiert ist -- nicht, was verlangt war.
        $this->assertSame('-1.000', $this->imKontext($user, fn () => PantryMovement::latest('id')->firstOrFail())->qty_delta);
    }

    public function test_knappe_positionen_werden_erkannt(): void
    {
        [$user] = $this->familie();
        $mehl = $this->position($user, 'Mehl', bestand: 1, soll: 3, melde: 1);
        $zucker = $this->position($user, 'Zucker', bestand: 5, soll: 3, melde: 1);

        $knapp = $this->imKontext($user, fn () => PantryItem::unterMeldebestand()->with('product')->get());

        $this->assertSame(['Mehl'], $knapp->pluck('product.name')->all());

        // Auf der Seite steht jeder Artikelname auch im Auswahlfeld zum
        // Aufnehmen -- geprüft wird deshalb die Zeile, nicht das Wort.
        $this->actingAs($user)->get('/vorrat?knapp=1')
            ->assertOk()
            ->assertSee('form="p'.$mehl->id.'"', false)
            ->assertDontSee('form="p'.$zucker->id.'"', false)
            ->assertSee('· knapp');
    }

    /**
     * Fehlmengen gehen erst nach Bestätigung auf die Liste.
     *
     * Ungefragt fünfzehn Zeilen aufzuschreiben ist schwer rückgängig zu
     * machen: Danach steht man vor einer Liste und weiß nicht mehr, was von
     * Hand daraufkam und was aus dem Vorrat.
     */
    public function test_die_bestaetigung_zeigt_bestand_soll_und_fehlmenge(): void
    {
        [$user] = $this->familie();
        $this->position($user, 'Mehl', bestand: 1, soll: 3);
        $this->position($user, 'Zucker', bestand: 5, soll: 3);

        $antwort = $this->actingAs($user)->get('/vorrat/auf-die-liste')->assertOk();

        $antwort->assertSee('Mehl');
        // Was über dem Soll liegt, taucht gar nicht erst auf.
        $antwort->assertDontSee('Zucker');

        $zeile = $antwort->viewData('fehlmengen')->firstWhere('name', 'Mehl');

        $this->assertSame('1', $zeile['bestand']);
        $this->assertSame('3', $zeile['soll']);
        $this->assertSame('2', $zeile['fehlt']);

        // Angesehen heißt nicht übernommen -- es entsteht nicht einmal eine
        // Liste, auf die etwas könnte.
        $this->assertSame(0, $this->imKontext($user, fn () => ShoppingListItem::count()));
    }

    public function test_abgewaehlte_positionen_bleiben_von_der_liste_weg(): void
    {
        [$user] = $this->familie();
        $mehl = $this->position($user, 'Mehl', bestand: 1, soll: 3);
        $this->position($user, 'Salz', bestand: 0, soll: 2);

        // Wie das Formular es schickt: nur die angehakten Zeilen.
        $this->actingAs($user)->post('/vorrat/auf-die-liste', ['positionen' => [$mehl->id]])
            ->assertRedirect(route('einkaufsliste.index'));

        $eintraege = $this->listeneintraege($user);

        $this->assertCount(1, $eintraege);
        $this->assertSame('Mehl', $eintraege->first()->raw_name);
    }

    public function test_ohne_fehlmenge_sagt_die_seite_dass_nichts_fehlt(): void
    {
        [$user] = $this->familie();
        $this->position($user, 'Mehl', bestand: 5, soll: 3);

        $this->actingAs($user)->get('/vorrat/auf-die-liste')
            ->assertOk()
            ->assertSee('Es fehlt nichts.');
    }

    public function test_fehlmengen_gehen_auf_die_einkaufsliste(): void
    {
        [$user] = $this->familie();
        $this->position($user, 'Mehl', bestand: 1, soll: 3);
        $this->position($user, 'Zucker', bestand: 5, soll: 3);

        $this->actingAs($user)->post('/vorrat/auf-die-liste')
            ->assertRedirect(route('einkaufsliste.index'));

        $eintraege = $this->listeneintraege($user);

        // Soll minus Ist -- und nur, was darunter liegt.
        $this->assertSame(['Mehl'], $eintraege->pluck('raw_name')->all());
        $this->assertSame('2.000', $eintraege->first()->amount);
        $this->assertSame('pantry', $eintraege->first()->source);
    }

    public function test_fremder_vorrat_endet_in_404(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $position = $this->position($eigen, 'Mehl');

        $this->actingAs($fremd)->post('/vorrat/'.$position->id.'/buchen', ['delta' => 1, 'reason' => 'purchase'])
            ->assertNotFound();
        $this->actingAs($fremd)->get('/vorrat/'.$position->id.'/verlauf')->assertNotFound();
        $this->actingAs($fremd)->delete('/vorrat/'.$position->id)->assertNotFound();

        $this->assertNotNull($position->fresh());
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

    protected function artikel(User $user, string $name): Product
    {
        return $this->imKontext($user, fn () => Product::factory()->create(['name' => $name]));
    }

    protected function position(User $user, string $name, float $bestand = 0, float $soll = 2, float $melde = 0): PantryItem
    {
        return $this->imKontext($user, function () use ($name, $bestand, $soll, $melde, $user) {
            $produkt = Product::matchingName($name)->first() ?? Product::factory()->create(['name' => $name]);

            $item = app(PantryManager::class)->ensureItem($produkt, 'Vorrat', [
                'target_qty' => $soll,
                'min_qty' => $melde,
                'unit' => 'kg',
            ]);

            if ($bestand > 0) {
                app(PantryManager::class)->book($item, $bestand, 'purchase', $user);
            }

            return $item->refresh();
        });
    }

    /**
     * @return Collection<int, ShoppingListItem>
     */
    protected function listeneintraege(User $user)
    {
        return $this->imKontext($user, fn () => ShoppingList::active()->firstOrFail()->items()->orderBy('id')->get());
    }
}
