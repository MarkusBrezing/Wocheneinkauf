<?php

namespace Tests\Feature\Einkaufsliste;

use App\Modules\ShoppingList\Models\ShoppingList;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Einkauf abschliessen und Archiv: C-11 bis C-14 und die Zusatzfunktion --
 * fehlende Artikel wandern in die neue Liste.
 */
class ArchivTest extends EinkaufslisteTestCase
{
    public function test_archivieren_schreibt_fehlende_artikel_auf_die_neue_liste(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Milch');
        $this->aufDieListe($user, 'Brot');
        $this->aufDieListe($user, '1-2 Zwiebeln, rote');

        // Milch liegt im Wagen, Brot und Zwiebeln gab es nicht.
        $milch = $this->eintrag($user, 'Milch');
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$milch->client_uuid.'/haken');

        $alte = $this->aktiveListe($user);

        $this->actingAs($user)->post('/einkaufsliste/archivieren', ['offene_uebernehmen' => 1])
            ->assertRedirect(route('einkaufsliste.index'))
            ->assertSessionHasNoErrors();

        $neue = $this->aktiveListe($user);

        $this->assertNotSame($alte->id, $neue->id, 'Nach dem Abschluss beginnt eine neue Liste.');
        $this->assertSame('archived', $alte->refresh()->status);
        $this->assertNotNull($alte->archived_at);

        $uebernommen = $this->eintraege($user, $neue);

        $this->assertSame(['Brot', 'Zwiebeln'], $uebernommen->pluck('raw_name')->sort()->values()->all());
        $this->assertFalse($uebernommen->contains('raw_name', 'Milch'), 'Gekauftes gehoert nicht auf die neue Liste.');

        // Menge, Notiz und Warengruppe kommen unveraendert mit.
        $zwiebeln = $uebernommen->firstWhere('raw_name', 'Zwiebeln');
        $this->assertSame('1-2', $zwiebeln->amount_raw);
        $this->assertSame('rote', $zwiebeln->note);
        $this->assertFalse($zwiebeln->is_checked);
        $this->assertSame('OBST_GEMUESE', $zwiebeln->productGroup->code);
    }

    public function test_das_archiv_behaelt_auch_die_fehlenden_artikel(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Milch');
        $this->aufDieListe($user, 'Brot');
        $alte = $this->aktiveListe($user);

        $this->actingAs($user)->post('/einkaufsliste/archivieren', ['offene_uebernehmen' => 1]);

        // Kopiert, nicht verschoben: das Archiv soll zeigen, was auf der Liste
        // stand -- einschliesslich dessen, was nicht im Wagen landete.
        $this->assertCount(2, $this->eintraege($user, $alte->refresh()));
    }

    public function test_ohne_uebernahme_beginnt_die_neue_liste_leer(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Milch');

        $this->actingAs($user)->post('/einkaufsliste/archivieren')->assertRedirect();

        $this->assertCount(0, $this->eintraege($user));
    }

    public function test_eine_leere_liste_wird_nicht_archiviert(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/einkaufsliste')->assertOk();
        $liste = $this->aktiveListe($user);

        $this->actingAs($user)->post('/einkaufsliste/archivieren')->assertRedirect();

        $this->assertSame('active', $liste->refresh()->status, 'Sonst sammeln sich leere Archiveintraege an.');
    }

    public function test_archiv_zeigt_die_abgeschlossenen_einkaeufe(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Bananen');
        $this->actingAs($user)->post('/einkaufsliste/archivieren');

        $archiv = $this->imKontext($user, fn () => ShoppingList::archived()->firstOrFail());

        $this->actingAs($user)->get('/einkaufsliste/archiv')
            ->assertOk()
            ->assertSee($archiv->anzeigename());

        $this->actingAs($user)->get('/einkaufsliste/archiv/'.$archiv->public_id)
            ->assertOk()
            ->assertSee('Bananen')
            ->assertSee('hat gefehlt');
    }

    public function test_die_aktive_liste_ist_im_archiv_nicht_erreichbar(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Brot');
        $aktiv = $this->aktiveListe($user);

        $this->actingAs($user)->get('/einkaufsliste/archiv/'.$aktiv->public_id)->assertNotFound();
    }

    public function test_archivierte_liste_laden_fuehrt_mit_der_aktuellen_zusammen(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Milch');
        $this->aufDieListe($user, 'Brot');
        $this->actingAs($user)->post('/einkaufsliste/archivieren');

        $archiv = $this->imKontext($user, fn () => ShoppingList::archived()->firstOrFail());

        $this->aufDieListe($user, '1 l Milch');

        $this->actingAs($user)->post('/einkaufsliste/archiv/'.$archiv->public_id.'/laden')
            ->assertRedirect(route('einkaufsliste.index'));

        $eintraege = $this->eintraege($user);

        $this->assertCount(2, $eintraege, 'Milch war schon da und wird zusammengefuehrt (C-17).');
        $this->assertSame('3.000', $eintraege->firstWhere('raw_name', 'Milch')->amount);
        $this->assertTrue($eintraege->contains('raw_name', 'Brot'));
    }

    public function test_an_einer_archivierten_liste_laesst_sich_nichts_mehr_aendern(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Brot');
        $brot = $this->eintrag($user, 'Brot');

        $this->actingAs($user)->post('/einkaufsliste/archivieren');

        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$brot->client_uuid.'/haken')->assertForbidden();
        $this->actingAs($user)->delete('/einkaufsliste/eintraege/'.$brot->client_uuid)->assertForbidden();
    }

    public function test_archiveintrag_loeschen_nimmt_die_eintraege_mit(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Brot');
        $brot = $this->eintrag($user, 'Brot');
        $this->actingAs($user)->post('/einkaufsliste/archivieren');

        $archiv = $this->imKontext($user, fn () => ShoppingList::archived()->firstOrFail());

        $this->actingAs($user)->delete('/einkaufsliste/archiv/'.$archiv->public_id)->assertRedirect();

        $this->assertDatabaseMissing('shopping_lists', ['id' => $archiv->id]);
        $this->assertDatabaseMissing('shopping_list_items', ['id' => $brot->id]);
    }

    public function test_die_datenbank_laesst_keine_zweite_aktive_liste_zu(): void
    {
        [$user, $family] = $this->familie();

        $this->aufDieListe($user, 'Brot');

        // Ebene 2 wie in Schritt 3: der Schutz haelt auch an Eloquent vorbei.
        $this->expectException(QueryException::class);

        DB::table('shopping_lists')->insert([
            'public_id' => str_repeat('Z', 26),
            'family_id' => $family->id,
            'status' => 'active',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
