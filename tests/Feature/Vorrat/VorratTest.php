<?php

namespace Tests\Feature\Vorrat;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pantry\Models\Inventory;
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
 * Vorrat und Inventur: E-01 bis E-11.
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

    public function test_inventur_starten_friert_soll_und_ist_ein(): void
    {
        [$user] = $this->familie();
        $position = $this->position($user, 'Mehl', bestand: 2, soll: 3);

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $zeile = $this->imKontext($user, fn () => Inventory::offen()->firstOrFail()->lines()->firstOrFail());

        $this->assertSame('2.000', $zeile->expected_qty);
        $this->assertSame('3.000', $zeile->target_qty);
        $this->assertNull($zeile->counted_qty, 'Noch nicht gezählt.');

        // Der eingefrorene Wert bleibt, auch wenn sich der Bestand ändert.
        $this->actingAs($user)->post('/vorrat/'.$position->id.'/buchen', ['delta' => 5, 'reason' => 'purchase']);
        $this->assertSame('2.000', $zeile->fresh()->expected_qty);
    }

    public function test_es_laeuft_hoechstens_eine_inventur(): void
    {
        [$user, $family] = $this->familie();
        $this->position($user, 'Mehl');

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full'])->assertSessionHasErrors('scope');

        $this->assertSame(1, $this->imKontext($user, fn () => Inventory::count()));

        // Ebene 2: auch an Eloquent vorbei nicht.
        $this->expectException(QueryException::class);

        DB::table('inventories')->insert([
            'public_id' => str_repeat('Y', 26), 'family_id' => $family->id,
            'scope' => 'full', 'status' => 'open', 'started_at' => now(),
        ]);
    }

    public function test_zaehlen_und_abschliessen_schreibt_den_bestand_fort(): void
    {
        [$user] = $this->familie();
        $position = $this->position($user, 'Mehl', bestand: 2, soll: 3);

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full']);
        $inventur = $this->imKontext($user, fn () => Inventory::offen()->firstOrFail());
        $zeile = $this->imKontext($user, fn () => $inventur->lines()->firstOrFail());

        $this->actingAs($user)->put('/vorrat/inventur/zeile/'.$zeile->id, ['counted_qty' => 5])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('5.000', $zeile->fresh()->counted_qty);
        $this->assertSame('3.000', $zeile->fresh()->difference, 'Die Abweichung rechnet die Datenbank.');
        $this->assertSame('2.000', $position->fresh()->current_qty, 'Vor dem Abschluss ändert sich nichts.');

        $this->actingAs($user)->post('/vorrat/inventur/'.$inventur->public_id.'/abschliessen')
            ->assertRedirect();

        $this->assertSame('5.000', $position->fresh()->current_qty);
        $this->assertSame('completed', $inventur->fresh()->status);

        $buchung = $this->imKontext($user, fn () => PantryMovement::latest('id')->firstOrFail());
        $this->assertSame('inventory', $buchung->reason);
        $this->assertSame('3.000', $buchung->qty_delta);
        $this->assertSame($inventur->id, $buchung->ref_id);
    }

    public function test_ungezaehlte_positionen_bleiben_beim_abschluss_unangetastet(): void
    {
        [$user] = $this->familie();
        $gezaehlt = $this->position($user, 'Mehl', bestand: 2);
        $ungezaehlt = $this->position($user, 'Zucker', bestand: 4);

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full']);
        $inventur = $this->imKontext($user, fn () => Inventory::offen()->firstOrFail());
        $zeile = $this->imKontext($user, fn () => $inventur->lines()->where('pantry_item_id', $gezaehlt->id)->firstOrFail());

        $this->actingAs($user)->put('/vorrat/inventur/zeile/'.$zeile->id, ['counted_qty' => 1]);
        $this->actingAs($user)->post('/vorrat/inventur/'.$inventur->public_id.'/abschliessen');

        $this->assertSame('1.000', $gezaehlt->fresh()->current_qty);
        // Was niemand angesehen hat, darf die Inventur nicht auf null setzen.
        $this->assertSame('4.000', $ungezaehlt->fresh()->current_qty);
    }

    public function test_eine_leere_eingabe_nimmt_die_zaehlung_zurueck(): void
    {
        [$user] = $this->familie();
        $this->position($user, 'Mehl', bestand: 2);

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full']);
        $inventur = $this->imKontext($user, fn () => Inventory::offen()->firstOrFail());
        $zeile = $this->imKontext($user, fn () => $inventur->lines()->firstOrFail());

        $this->actingAs($user)->put('/vorrat/inventur/zeile/'.$zeile->id, ['counted_qty' => 3]);
        $this->actingAs($user)->put('/vorrat/inventur/zeile/'.$zeile->id, ['counted_qty' => '']);

        $this->assertNull($zeile->fresh()->counted_qty, 'Wer sich vertippt, muss zurückkönnen.');
        $this->assertNull($zeile->fresh()->counted_by);
    }

    public function test_abbrechen_laesst_die_bestaende_in_ruhe(): void
    {
        [$user] = $this->familie();
        $position = $this->position($user, 'Mehl', bestand: 2);

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full']);
        $inventur = $this->imKontext($user, fn () => Inventory::offen()->firstOrFail());
        $zeile = $this->imKontext($user, fn () => $inventur->lines()->firstOrFail());

        $this->actingAs($user)->put('/vorrat/inventur/zeile/'.$zeile->id, ['counted_qty' => 99]);
        $this->actingAs($user)->post('/vorrat/inventur/'.$inventur->public_id.'/abbrechen')
            ->assertRedirect(route('vorrat.inventur.index'));

        $this->assertSame('cancelled', $inventur->fresh()->status);
        $this->assertSame('2.000', $position->fresh()->current_qty);
    }

    public function test_der_bericht_zeigt_die_abweichung(): void
    {
        [$user] = $this->familie();
        $this->position($user, 'Mehl', bestand: 2);

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full']);
        $inventur = $this->imKontext($user, fn () => Inventory::offen()->firstOrFail());
        $zeile = $this->imKontext($user, fn () => $inventur->lines()->firstOrFail());

        $this->actingAs($user)->put('/vorrat/inventur/zeile/'.$zeile->id, ['counted_qty' => 1]);
        $this->actingAs($user)->post('/vorrat/inventur/'.$inventur->public_id.'/abschliessen');

        $this->actingAs($user)->get('/vorrat/inventur/'.$inventur->public_id)
            ->assertOk()
            ->assertSee('Mehl')
            ->assertSee('Abweichung');
    }

    public function test_fehlmengen_aus_der_inventur_gehen_auf_die_liste(): void
    {
        [$user] = $this->familie();
        $this->position($user, 'Mehl', bestand: 3, soll: 3);

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full']);
        $inventur = $this->imKontext($user, fn () => Inventory::offen()->firstOrFail());
        $zeile = $this->imKontext($user, fn () => $inventur->lines()->firstOrFail());

        // Gezählt wurde weniger als gedacht.
        $this->actingAs($user)->put('/vorrat/inventur/zeile/'.$zeile->id, ['counted_qty' => 1]);
        $this->actingAs($user)->post('/vorrat/inventur/'.$inventur->public_id.'/abschliessen');
        $this->actingAs($user)->post('/vorrat/inventur/'.$inventur->public_id.'/auf-die-liste')
            ->assertRedirect(route('einkaufsliste.index'));

        $eintrag = $this->listeneintraege($user)->firstWhere('raw_name', 'Mehl');

        $this->assertSame('2.000', $eintrag->amount, 'Soll 3 minus gezählte 1.');
    }

    public function test_an_einer_abgeschlossenen_inventur_wird_nicht_mehr_gezaehlt(): void
    {
        [$user] = $this->familie();
        $this->position($user, 'Mehl');

        $this->actingAs($user)->post('/vorrat/inventur', ['scope' => 'full']);
        $inventur = $this->imKontext($user, fn () => Inventory::offen()->firstOrFail());
        $zeile = $this->imKontext($user, fn () => $inventur->lines()->firstOrFail());

        $this->actingAs($user)->post('/vorrat/inventur/'.$inventur->public_id.'/abschliessen');

        $this->actingAs($user)->put('/vorrat/inventur/zeile/'.$zeile->id, ['counted_qty' => 7])
            ->assertForbidden();
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
