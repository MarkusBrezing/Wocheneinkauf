<?php

namespace Tests\Feature\Einkaufsliste;

use App\Modules\Markets\Models\Market;
use App\Modules\ShoppingList\Models\ShoppingList;

/**
 * Die Einkaufsansicht: die Liste, wie man sie im Laden braucht.
 *
 * Sie kann genau zwei Dinge — abhaken und den Einkauf beenden. Was sie nicht
 * kann, ist genauso Teil der Zusage; die Tests halten beides fest.
 */
class EinkaufsansichtTest extends EinkaufslisteTestCase
{
    public function test_die_seite_zeigt_die_offenen_artikel_nach_regalbereichen(): void
    {
        [$user] = $this->familie();

        $markt = $this->imKontext($user, fn () => Market::where('is_default', true)->firstOrFail());
        $this->actingAs($user)->post('/einkaufsliste/markt', ['market_id' => $markt->id]);

        $this->aufDieListe($user, '2 l Vollmilch');
        $this->aufDieListe($user, 'Bananen');

        $this->actingAs($user)->get('/einkaufsliste/einkaufen')
            ->assertOk()
            ->assertSee('Vollmilch')
            ->assertSee('Bananen')
            ->assertSee('Kühlregal')
            ->assertSee('Obst und Gemüse')
            ->assertSee('0 von 2');
    }

    public function test_abgehaktes_ist_ausgeblendet_und_laesst_sich_zeigen(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Vollmilch');
        $this->aufDieListe($user, 'Bananen');
        $milch = $this->eintrag($user, 'Vollmilch');
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$milch->client_uuid.'/haken');

        // Im Laden zählt, was noch fehlt.
        $this->actingAs($user)->get('/einkaufsliste/einkaufen')
            ->assertOk()
            ->assertDontSee('Vollmilch')
            ->assertSee('Bananen')
            ->assertSee('1 Abgehaktes zeigen');

        // Der Schalter ist ein Link — die Seite braucht kein JavaScript.
        $this->actingAs($user)->get('/einkaufsliste/einkaufen?erledigt=1')
            ->assertOk()
            ->assertSee('Vollmilch')
            ->assertSee('Abgehaktes ausblenden');
    }

    public function test_abhaken_fuehrt_an_dieselbe_stelle_zurueck(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');
        $milch = $this->eintrag($user, 'Vollmilch');

        // Ohne den Anker stünde man nach jedem Tippen wieder am Listenanfang.
        $this->actingAs($user)
            ->from('/einkaufsliste/einkaufen')
            ->post('/einkaufsliste/eintraege/'.$milch->client_uuid.'/haken', ['anker' => 'b0'])
            ->assertRedirect('/einkaufsliste/einkaufen#b0');

        $this->assertTrue($milch->fresh()->is_checked);
    }

    /**
     * Abhaken aus JavaScript heraus: Derselbe Endpunkt antwortet mit Zahlen
     * statt mit einer Umleitung.
     *
     * Beim Einkaufen wird der Haken hundertmal getippt. Ein voller
     * Seitenneuaufbau je Tipper ist im Laden mit schwachem Empfang der
     * Unterschied zwischen „geht" und „geht nicht".
     */
    public function test_abhaken_liefert_json_wenn_json_erwartet_wird(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');
        $this->aufDieListe($user, 'Brot');
        $milch = $this->eintrag($user, 'Vollmilch');

        $this->actingAs($user)
            ->postJson('/einkaufsliste/eintraege/'.$milch->client_uuid.'/haken')
            ->assertOk()
            ->assertExactJson([
                'abgehakt' => true,
                'offen' => 1,
                'erledigt' => 1,
                'gesamt' => 2,
            ]);

        $this->assertTrue($milch->fresh()->is_checked);

        // Und wieder zurueck -- derselbe Endpunkt schaltet um, nicht ein.
        $this->actingAs($user)
            ->postJson('/einkaufsliste/eintraege/'.$milch->client_uuid.'/haken')
            ->assertOk()
            ->assertJsonPath('abgehakt', false)
            ->assertJsonPath('erledigt', 0);
    }

    /**
     * Der Schalter „N Abgehaktes zeigen" muss im Markup stehen, auch wenn noch
     * nichts abgehakt ist.
     *
     * Solange jedes Abhaken die Seite neu lud, genügte ein @if. Jetzt wächst
     * der Zähler von null hoch, ohne dass der Server noch einmal gefragt wird
     * — ein Link, den er nicht mitgeschickt hat, kann dann nie mehr
     * erscheinen, und das Abgehakte wäre bis zum nächsten Neuladen weg.
     */
    public function test_der_schalter_fuer_abgehaktes_steht_auch_bei_null_im_markup(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');

        // Nichts abgehakt: vorhanden, aber verborgen.
        $inhalt = $this->actingAs($user)->get('/einkaufsliste/einkaufen')->assertOk()->getContent();

        $this->assertStringContainsString('data-erledigt-schalter', $inhalt);
        $this->assertMatchesRegularExpression('/data-erledigt-schalter\s+hidden/', $inhalt);

        // Einer abgehakt: sichtbar.
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$this->eintrag($user, 'Vollmilch')->client_uuid.'/haken');

        $inhalt = $this->actingAs($user)->get('/einkaufsliste/einkaufen')->assertOk()->getContent();

        $this->assertStringContainsString('data-erledigt-schalter', $inhalt);
        $this->assertDoesNotMatchRegularExpression('/data-erledigt-schalter\s+hidden/', $inhalt);
    }

    /**
     * Abgehaktes verlässt die Liste — auch ohne Seitenneuaufbau.
     *
     * Vor dem Umbau erledigte das der Neuaufbau: Die Ansicht lädt von Haus aus
     * nur Offenes. Ohne ihn bliebe der Artikel durchgestrichen stehen, und die
     * Liste würde beim Einkaufen immer unübersichtlicher statt kürzer.
     *
     * Ist „Abgehaktes zeigen" eingeschaltet, bleibt alles stehen — sonst
     * verschwände genau das wieder, was man sehen wollte.
     */
    public function test_die_einkaufsansicht_blendet_abgehaktes_aus(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');

        $this->actingAs($user)->get('/einkaufsliste/einkaufen')
            ->assertOk()
            ->assertSee('ausblenden = true', escape: false);

        $this->actingAs($user)->get('/einkaufsliste/einkaufen?erledigt=1')
            ->assertOk()
            ->assertSee('ausblenden = false', escape: false);
    }

    /**
     * Das Formular muss bestehen bleiben: Ohne JavaScript ist es der einzige
     * Weg, einen Haken zu setzen.
     */
    public function test_ohne_javascript_bleibt_das_formular_der_weg(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');

        $inhalt = $this->actingAs($user)->get('/einkaufsliste/einkaufen')->assertOk()->getContent();

        $this->assertStringContainsString('method="POST"', $inhalt);
        $this->assertStringContainsString('/haken', $inhalt);
        $this->assertStringContainsString('name="_token"', $inhalt);
    }

    public function test_ein_unsinniger_anker_wird_abgewiesen(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');
        $milch = $this->eintrag($user, 'Vollmilch');

        $this->actingAs($user)
            ->from('/einkaufsliste/einkaufen')
            ->post('/einkaufsliste/eintraege/'.$milch->client_uuid.'/haken', [
                'anker' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('anker');

        $this->assertFalse($milch->fresh()->is_checked);
    }

    public function test_einkauf_beenden_archiviert_und_nimmt_das_fehlende_mit(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Vollmilch');
        $this->aufDieListe($user, 'Brot');
        $milch = $this->eintrag($user, 'Vollmilch');
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$milch->client_uuid.'/haken');

        $alte = $this->aktiveListe($user);

        // Die Fußleiste sendet offene_uebernehmen fest mit: Was nicht
        // abgehakt ist, hat gefehlt.
        $this->actingAs($user)
            ->from('/einkaufsliste/einkaufen')
            ->post('/einkaufsliste/archivieren', ['offene_uebernehmen' => 1])
            ->assertRedirect(route('einkaufsliste.index'));

        $this->assertSame('archived', $alte->fresh()->status);
        $this->assertSame(['Brot'], $this->eintraege($user)->pluck('raw_name')->all());
    }

    public function test_die_seite_hat_kein_eingabefeld(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');

        $inhalt = $this->actingAs($user)->get('/einkaufsliste/einkaufen')->assertOk()->getContent();

        // Nichts, was die Tastatur öffnet — das ist der Unterschied zur
        // Pflegeseite. Nur versteckte Felder (CSRF, Anker) sind erlaubt.
        $this->assertSame(0, preg_match_all('/<input(?![^>]*type="hidden")/i', $inhalt),
            'Die Einkaufsansicht darf kein sichtbares Eingabefeld enthalten.');
        $this->assertStringNotContainsString('<textarea', $inhalt);
        $this->assertStringNotContainsString('<select', $inhalt);
    }

    public function test_die_seite_laedt_ohne_navigation_und_ohne_artikelvorschlaege(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');

        $antwort = $this->actingAs($user)->get('/einkaufsliste/einkaufen')->assertOk();

        // Was nicht geladen wird, kann im Funkloch auch nicht fehlen.
        $antwort->assertDontSee('artikel-katalog')
            ->assertDontSee('Warengruppen')
            ->assertDontSee('Stammdaten');
    }

    public function test_eine_leere_liste_sagt_das_auch(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/einkaufsliste/einkaufen')
            ->assertOk()
            ->assertSee('Auf der Liste steht nichts.');
    }

    public function test_alles_abgehakt_wird_als_solches_gemeldet(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, 'Vollmilch');
        $milch = $this->eintrag($user, 'Vollmilch');
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$milch->client_uuid.'/haken');

        $this->actingAs($user)->get('/einkaufsliste/einkaufen')
            ->assertOk()
            ->assertSee('Alles abgehakt');
    }

    public function test_beide_einstiege_fuehren_zur_ansicht(): void
    {
        [$user] = $this->familie();

        // Knopf auf der Listenseite und Menüpunkt im Layout.
        $this->actingAs($user)->get('/einkaufsliste')
            ->assertOk()
            ->assertSee(route('einkaufsliste.einkaufen'), false)
            ->assertSee('>Einkaufen<', false);
    }

    public function test_ohne_anmeldung_geht_nichts(): void
    {
        $this->get('/einkaufsliste/einkaufen')->assertRedirect('/login');
    }

    public function test_jede_familie_sieht_nur_ihre_liste(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->aufDieListe($eigen, 'Vollmilch');
        $this->aufDieListe($fremd, 'Kaviar');

        $this->actingAs($fremd)->get('/einkaufsliste/einkaufen')
            ->assertOk()
            ->assertSee('Kaviar')
            ->assertDontSee('Vollmilch');

        $this->assertSame(2, ShoppingList::withoutGlobalScopes()->active()->count());
    }
}
