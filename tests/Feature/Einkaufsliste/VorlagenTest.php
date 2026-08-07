<?php

namespace Tests\Feature\Einkaufsliste;

use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Models\ListTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Vorlagen für neue Einkaufslisten: verwalten, anwenden, automatisch übernehmen.
 */
class VorlagenTest extends EinkaufslisteTestCase
{
    public function test_vorlage_anlegen_und_mit_artikeln_fuellen(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/vorlagen', [
            'name' => 'Grillabend',
            'description' => 'Für spontane Abende',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $vorlage = $this->vorlage($user, 'Grillabend');

        $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/positionen', [
            'text' => '500 g Hackfleisch',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/positionen', [
            'text' => 'Brötchen, für 6 Personen',
        ])->assertRedirect();

        $positionen = $this->imKontext($user, fn () => $vorlage->items()->get());

        $this->assertCount(2, $positionen);

        $hack = $positionen->firstWhere('raw_name', 'Hackfleisch');
        $this->assertSame('500.000', $hack->amount);
        $this->assertSame('g', $hack->unit);
        $this->assertSame('FLEISCH', $hack->productGroup->code, 'Die Stichwortregeln greifen auch hier.');

        $broetchen = $positionen->firstWhere('raw_name', 'Brötchen');
        $this->assertSame('für 6 Personen', $broetchen->note);
    }

    public function test_derselbe_name_wird_nur_einmal_vergeben(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/vorlagen', ['name' => 'Wocheneinkauf']);
        $this->actingAs($user)->post('/vorlagen', ['name' => 'Wocheneinkauf'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $this->imKontext($user, fn () => ListTemplate::count()));
    }

    public function test_vorlage_anwenden_fuehrt_mit_der_liste_zusammen(): void
    {
        [$user] = $this->familie();
        $vorlage = $this->vorlageMit($user, 'Wocheneinkauf', ['2 l Vollmilch', 'Brot']);

        $this->aufDieListe($user, '1 l Vollmilch');

        $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/anwenden')
            ->assertRedirect(route('einkaufsliste.index'));

        $eintraege = $this->eintraege($user);

        $this->assertCount(2, $eintraege, 'Milch war schon da (C-17).');
        $this->assertSame('3.000', $eintraege->firstWhere('raw_name', 'Vollmilch')->amount);
        $this->assertSame('template', $eintraege->firstWhere('raw_name', 'Brot')->source);
        $this->assertSame($vorlage->id, $eintraege->firstWhere('raw_name', 'Brot')->source_ref_id);
    }

    public function test_die_standardvorlage_kommt_auf_jede_neue_liste(): void
    {
        [$user] = $this->familie();
        $vorlage = $this->vorlageMit($user, 'Wocheneinkauf', ['2 l Vollmilch', 'Butter']);

        $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/standard')
            ->assertRedirect()->assertSessionHasNoErrors();

        // Die erste Liste dieser Familie entsteht beim Aufruf der Seite.
        $this->actingAs($user)->get('/einkaufsliste')->assertOk();

        $this->assertSame(
            ['Butter', 'Vollmilch'],
            $this->eintraege($user)->pluck('raw_name')->sort()->values()->all()
        );
    }

    public function test_nach_dem_abschluss_beginnt_die_neue_liste_mit_der_vorlage(): void
    {
        [$user] = $this->familie();
        $vorlage = $this->vorlageMit($user, 'Wocheneinkauf', ['2 l Vollmilch', 'Brot']);

        $this->aufDieListe($user, 'Zahnpasta');
        $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/standard');

        $this->actingAs($user)->post('/einkaufsliste/archivieren', ['offene_uebernehmen' => 1])
            ->assertRedirect();

        // Zahnpasta fehlte und kommt mit, dazu die beiden Vorlagenartikel.
        $this->assertSame(
            ['Brot', 'Vollmilch', 'Zahnpasta'],
            $this->eintraege($user)->pluck('raw_name')->sort()->values()->all()
        );
    }

    public function test_ein_uebernommener_fehlartikel_wird_von_der_vorlage_nicht_verdoppelt(): void
    {
        [$user] = $this->familie();

        // Erst die Liste, dann die Standardvorlage -- sonst stünde die
        // Vorlagenmenge schon auf der laufenden Liste.
        $this->aufDieListe($user, '1 l Vollmilch');
        $vorlage = $this->vorlageMit($user, 'Wocheneinkauf', ['2 l Vollmilch']);
        $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/standard');

        $this->actingAs($user)->post('/einkaufsliste/archivieren', ['offene_uebernehmen' => 1]);

        $eintraege = $this->eintraege($user);

        // Der übernommene Liter trifft auf die zwei Liter der Vorlage: ein
        // Eintrag mit drei Litern, keine zwei Zeilen Milch. Das ist der Grund,
        // warum der Archiver die Vorlage erst nach den Fehlartikeln anwendet.
        $this->assertCount(1, $eintraege);
        $this->assertSame('3.000', $eintraege->first()->amount);
    }

    public function test_was_letzte_woche_fehlte_kommt_zur_neuen_vorlagenmenge_hinzu(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Vollmilch');
        $vorlage = $this->vorlageMit($user, 'Wocheneinkauf', ['2 l Vollmilch']);
        $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/standard');

        $this->actingAs($user)->post('/einkaufsliste/archivieren', ['offene_uebernehmen' => 1]);

        // Bewusst so: Wer die Milch nicht bekommen hat, braucht sie zusätzlich
        // zum Wochenbedarf. Wer sie gekauft hat, hakt sie ab -- dann wird
        // nichts übernommen und es bleiben die zwei Liter der Vorlage.
        $this->assertSame('4.000', $this->eintraege($user)->first()->amount);
    }

    public function test_der_assistent_nimmt_die_gewaehlten_vorlagen(): void
    {
        [$user] = $this->familie();
        $wochen = $this->vorlageMit($user, 'Wocheneinkauf', ['2 l Vollmilch']);
        $grillen = $this->vorlageMit($user, 'Grillabend', ['500 g Hackfleisch']);

        $this->imKontext($user, function () {
            Product::factory()->create(['name' => 'Butter'])->update(['is_pantry_staple' => true]);
        });

        $this->actingAs($user)->post('/einkaufsliste/neue-woche', [
            'vorlagen' => [$wochen->id],
            'grundvorrat' => $this->imKontext($user, fn () => Product::grundvorrat()->pluck('id')->all()),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            ['Butter', 'Vollmilch'],
            $this->eintraege($user)->pluck('raw_name')->sort()->values()->all(),
            'Nur die gewählte Vorlage, nicht der Grillabend.'
        );
        $this->assertNotNull($grillen->fresh());
    }

    public function test_der_assistent_verdoppelt_die_standardvorlage_nicht(): void
    {
        [$user] = $this->familie();

        // Reihenfolge zählt: erst die Liste anlegen, dann die Vorlage zum
        // Standard machen -- so steht die Milch noch nicht darauf.
        $this->aufDieListe($user, 'Zahnpasta');
        $vorlage = $this->vorlageMit($user, 'Wocheneinkauf', ['2 l Vollmilch']);
        $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/standard');

        // Die Standardvorlage ist im Formular vorausgewählt -- sie darf nicht
        // zusätzlich automatisch beim Archivieren angewendet werden.
        $this->actingAs($user)->post('/einkaufsliste/neue-woche', [
            'archivieren' => 1,
            'offene_uebernehmen' => 1,
            'vorlagen' => [$vorlage->id],
        ])->assertRedirect();

        $milch = $this->eintraege($user)->firstWhere('raw_name', 'Vollmilch');

        $this->assertSame('2.000', $milch->amount, 'Genau einmal die Vorlagenmenge.');
    }

    public function test_es_gibt_hoechstens_eine_standardvorlage(): void
    {
        [$user] = $this->familie();
        $a = $this->vorlageMit($user, 'Wocheneinkauf', ['Brot']);
        $b = $this->vorlageMit($user, 'Grillabend', ['Hackfleisch']);

        $this->actingAs($user)->post('/vorlagen/'.$a->public_id.'/standard');
        $this->actingAs($user)->post('/vorlagen/'.$b->public_id.'/standard');

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);

        // Noch einmal derselbe Knopf schaltet die Automatik wieder ab.
        $this->actingAs($user)->post('/vorlagen/'.$b->public_id.'/standard');

        $this->assertSame(0, $this->imKontext($user, fn () => ListTemplate::where('is_default', true)->count()));
    }

    public function test_die_aktuelle_liste_laesst_sich_als_vorlage_sichern(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Vollmilch');
        $this->aufDieListe($user, 'Brot');
        $brot = $this->eintrag($user, 'Brot');
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$brot->client_uuid.'/haken');

        $this->actingAs($user)->post('/vorlagen/aus-liste', ['name' => 'Wie diese Woche'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $vorlage = $this->vorlage($user, 'Wie diese Woche');
        $positionen = $this->imKontext($user, fn () => $vorlage->items()->get());

        // Auch das Abgehakte kommt mit: wer den Einkauf gerade erledigt hat,
        // will genau ihn beim nächsten Mal wieder.
        $this->assertSame(['Vollmilch', 'Brot'], $positionen->pluck('raw_name')->all());
        $this->assertSame('2.000', $positionen->firstWhere('raw_name', 'Vollmilch')->amount);
    }

    public function test_aus_einer_leeren_liste_entsteht_keine_vorlage(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/vorlagen/aus-liste', ['name' => 'Leer'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $this->imKontext($user, fn () => ListTemplate::count()));
    }

    public function test_positionen_lassen_sich_bearbeiten_und_entfernen(): void
    {
        [$user] = $this->familie();
        $vorlage = $this->vorlageMit($user, 'Wocheneinkauf', ['1 l Vollmilch', 'Brot']);
        $position = $this->imKontext($user, fn () => $vorlage->items()->where('raw_name', 'Vollmilch')->firstOrFail());

        $this->actingAs($user)->put('/vorlagen/positionen/'.$position->id, [
            'text' => '3 l Vollmilch',
            'note' => 'die haltbare',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $position->refresh();

        $this->assertSame('3.000', $position->amount);
        $this->assertSame('die haltbare', $position->note);

        $this->actingAs($user)->delete('/vorlagen/positionen/'.$position->id)->assertRedirect();

        $this->assertSame(1, $this->imKontext($user, fn () => $vorlage->items()->count()));
    }

    public function test_eine_geloeschte_vorlage_nimmt_ihre_positionen_mit(): void
    {
        [$user] = $this->familie();
        $vorlage = $this->vorlageMit($user, 'Wocheneinkauf', ['Brot']);

        $this->actingAs($user)->delete('/vorlagen/'.$vorlage->public_id)
            ->assertRedirect(route('vorlagen.index'));

        $this->assertDatabaseMissing('list_templates', ['id' => $vorlage->id]);
        $this->assertDatabaseMissing('list_template_items', ['template_id' => $vorlage->id]);
    }

    public function test_eine_fremde_vorlage_endet_in_404(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $vorlage = $this->vorlageMit($eigen, 'Wocheneinkauf', ['Brot']);
        $position = $this->imKontext($eigen, fn () => $vorlage->items()->firstOrFail());

        $this->actingAs($fremd)->get('/vorlagen/'.$vorlage->public_id)->assertNotFound();
        $this->actingAs($fremd)->post('/vorlagen/'.$vorlage->public_id.'/anwenden')->assertNotFound();
        $this->actingAs($fremd)->delete('/vorlagen/'.$vorlage->public_id)->assertNotFound();
        $this->actingAs($fremd)->delete('/vorlagen/positionen/'.$position->id)->assertNotFound();

        $this->assertNotNull($vorlage->fresh());
    }

    public function test_die_datenbank_lehnt_eine_familienuebergreifende_position_ab(): void
    {
        [$eigen] = $this->familie();
        [$fremd, $fremdeFamilie] = $this->familie('fremd@example.test', 'Familie Fremd');

        $vorlage = $this->vorlageMit($eigen, 'Wocheneinkauf', ['Brot']);

        // Ebene 2: der Verbund-Fremdschlüssel (template_id, family_id) lässt
        // eine Position mit fremder family_id nicht zu.
        $this->expectException(QueryException::class);

        DB::table('list_template_items')->insert([
            'family_id' => $fremdeFamilie->id,
            'template_id' => $vorlage->id,
            'raw_name' => 'Untergeschoben',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_der_assistent_ignoriert_eine_fremde_vorlage(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $fremdeVorlage = $this->vorlageMit($fremd, 'Fremd', ['Fremdartikel']);

        $this->actingAs($eigen)->post('/einkaufsliste/neue-woche', [
            'vorlagen' => [$fremdeVorlage->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertCount(0, $this->eintraege($eigen));
    }

    public function test_die_verwaltungsmaske_zeigt_vorlagen_und_positionen(): void
    {
        [$user] = $this->familie();
        $vorlage = $this->vorlageMit($user, 'Grillabend', ['500 g Hackfleisch']);

        $this->actingAs($user)->get('/vorlagen')->assertOk()->assertSee('Grillabend');

        $this->actingAs($user)->get('/vorlagen/'.$vorlage->public_id)
            ->assertOk()
            ->assertSee('Hackfleisch')
            ->assertSee('500 g');
    }

    protected function vorlage($user, string $name): ListTemplate
    {
        return $this->imKontext($user, fn () => ListTemplate::where('name', $name)->firstOrFail());
    }

    /**
     * @param  array<int, string>  $texte
     */
    protected function vorlageMit($user, string $name, array $texte): ListTemplate
    {
        $this->actingAs($user)->post('/vorlagen', ['name' => $name])->assertSessionHasNoErrors();

        $vorlage = $this->vorlage($user, $name);

        foreach ($texte as $text) {
            $this->actingAs($user)->post('/vorlagen/'.$vorlage->public_id.'/positionen', ['text' => $text])
                ->assertSessionHasNoErrors();
        }

        return $vorlage->refresh();
    }
}
