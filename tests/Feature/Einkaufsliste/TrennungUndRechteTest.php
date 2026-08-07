<?php

namespace Tests\Feature\Einkaufsliste;

use App\Modules\ShoppingList\Models\ShoppingList;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mandantentrennung und Rechte auf der Einkaufsliste.
 *
 * Beide Ebenen aus Schritt 3 gelten weiter: der Global Scope im Code und die
 * Verbund-Fremdschluessel in der Datenbank.
 */
class TrennungUndRechteTest extends EinkaufslisteTestCase
{
    public function test_ein_fremder_listeneintrag_endet_in_404(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->aufDieListe($eigen, 'Brot');
        $brot = $this->eintrag($eigen, 'Brot');

        // Route-Model-Binding laeuft durch den Global Scope -- deshalb steht
        // in den Controllern keine einzige Besitzpruefung.
        $this->actingAs($fremd)->post('/einkaufsliste/eintraege/'.$brot->client_uuid.'/haken')->assertNotFound();
        $this->actingAs($fremd)->put('/einkaufsliste/eintraege/'.$brot->client_uuid, ['text' => 'Kuchen'])->assertNotFound();
        $this->actingAs($fremd)->delete('/einkaufsliste/eintraege/'.$brot->client_uuid)->assertNotFound();

        $this->assertFalse($brot->refresh()->is_checked);
    }

    public function test_eine_fremde_archivliste_endet_in_404(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->aufDieListe($eigen, 'Brot');
        $this->actingAs($eigen)->post('/einkaufsliste/archivieren');
        $archiv = $this->imKontext($eigen, fn () => ShoppingList::archived()->firstOrFail());

        $this->actingAs($fremd)->get('/einkaufsliste/archiv/'.$archiv->public_id)->assertNotFound();
        $this->actingAs($fremd)->post('/einkaufsliste/archiv/'.$archiv->public_id.'/laden')->assertNotFound();
    }

    public function test_jede_familie_sieht_nur_ihre_eigene_liste(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->aufDieListe($eigen, 'Brot');
        $this->aufDieListe($fremd, 'Käse');

        $this->actingAs($fremd)->getJson('/api/einkaufsliste/stand')
            ->assertOk()
            ->assertJsonPath('eintraege.0.name', 'Käse')
            ->assertJsonCount(1, 'eintraege');
    }

    public function test_ein_mitglied_ohne_verwaltungsrecht_darf_das_archiv_nicht_loeschen(): void
    {
        [$owner, $family] = $this->familie();
        $kind = $this->mitglied($family);

        $this->aufDieListe($owner, 'Brot');
        $this->actingAs($owner)->post('/einkaufsliste/archivieren');
        $archiv = $this->imKontext($owner, fn () => ShoppingList::archived()->firstOrFail());

        // Die einzige Stelle des Moduls mit Rollenpruefung: ein Archiveintrag
        // ist Historie und laesst sich nicht wieder aufschreiben.
        $this->actingAs($kind)->delete('/einkaufsliste/archiv/'.$archiv->public_id)->assertForbidden();

        $this->assertDatabaseHas('shopping_lists', ['id' => $archiv->id]);

        // Alles andere darf jedes Mitglied.
        $this->actingAs($kind)->post('/einkaufsliste/archiv/'.$archiv->public_id.'/laden')->assertRedirect();
        $this->aufDieListe($kind, 'Butter');
    }

    public function test_die_datenbank_lehnt_einen_familienuebergreifenden_eintrag_ab(): void
    {
        [$eigen] = $this->familie();
        [$fremd, $fremdeFamilie] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->aufDieListe($eigen, 'Brot');
        $liste = $this->aktiveListe($eigen);

        // Ebene 2: der Verbund-Fremdschluessel (list_id, family_id) laesst
        // einen Eintrag mit fremder family_id nicht zu -- auch nicht an
        // Eloquent vorbei.
        $this->expectException(QueryException::class);

        DB::table('shopping_list_items')->insert([
            'family_id' => $fremdeFamilie->id,
            'list_id' => $liste->id,
            'client_uuid' => (string) Str::uuid(),
            'raw_name' => 'Untergeschoben',
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ohne_anmeldung_geht_gar_nichts(): void
    {
        $this->get('/einkaufsliste')->assertRedirect('/login');
        $this->getJson('/api/einkaufsliste/stand')->assertUnauthorized();
    }
}
