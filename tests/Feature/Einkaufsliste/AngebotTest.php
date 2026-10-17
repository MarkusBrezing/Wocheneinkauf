<?php

namespace Tests\Feature\Einkaufsliste;

use App\Core\Models\User;

/**
 * Aktionsangebote auf der Einkaufsliste.
 *
 * Wer den Prospekt durchgeht, meint mit "Butter" die Butter, die diese Woche
 * im Angebot ist. Im Laden steht die oft woanders -- auf dem Aktionstisch statt
 * im Kühlregal. Ohne Markierung kauft man die reguläre zum vollen Preis.
 */
class AngebotTest extends EinkaufslisteTestCase
{
    public function test_ein_artikel_laesst_sich_als_angebot_eintragen(): void
    {
        [$user] = $this->familie();

        $this->eintragen($user, 'Butter', angebot: true);
        $this->eintragen($user, 'Brot');

        $this->assertTrue($this->eintrag($user, 'Butter')->is_offer);
        $this->assertFalse($this->eintrag($user, 'Brot')->is_offer);
    }

    /**
     * Wird ein Artikel, der schon auf der Liste steht, mit Haken nachgetragen,
     * wird der vorhandene Eintrag zum Angebot -- statt einer zweiten Zeile.
     */
    public function test_nachtragen_mit_haken_macht_den_vorhandenen_eintrag_zum_angebot(): void
    {
        [$user] = $this->familie();

        $this->eintragen($user, 'Butter');
        $this->eintragen($user, 'Butter', angebot: true);

        $this->assertCount(1, $this->eintraege($user));
        $this->assertTrue($this->eintrag($user, 'Butter')->is_offer);
    }

    /**
     * Die andere Richtung: Ein zweites "Butter" ohne Haken -- etwa aus einer
     * Vorlage -- darf die Aktionsbutter nicht still zur regulären machen.
     */
    public function test_nachtragen_ohne_haken_laesst_ein_angebot_stehen(): void
    {
        [$user] = $this->familie();

        $this->eintragen($user, 'Butter', angebot: true);
        $this->eintragen($user, 'Butter');

        $this->assertCount(1, $this->eintraege($user));
        $this->assertTrue($this->eintrag($user, 'Butter')->is_offer);
    }

    /**
     * Abwählen über die Bearbeitungsmaske.
     *
     * Ein abgewähltes Kästchen sendet der Browser nicht. Die Maske schickt
     * deshalb ein verstecktes angebot=0 vorweg. Ohne das ließe sich ein
     * Angebot nie wieder abwählen -- genau die Falle, in die ein leeres
     * select multiple in diesem Projekt schon einmal geführt hat.
     */
    public function test_ein_angebot_laesst_sich_wieder_abwaehlen(): void
    {
        [$user] = $this->familie();
        $this->eintragen($user, 'Butter', angebot: true);
        $butter = $this->eintrag($user, 'Butter');

        // So, wie der Browser es bei abgewähltem Kästchen schickt: nur die 0.
        $this->actingAs($user)->from('/einkaufsliste')
            ->put('/einkaufsliste/eintraege/'.$butter->client_uuid, [
                'text' => 'Butter',
                'angebot' => '0',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($butter->fresh()->is_offer);

        // Und angehakt: 0 und 1 kommen beide, die spätere gewinnt.
        $this->actingAs($user)->from('/einkaufsliste')
            ->put('/einkaufsliste/eintraege/'.$butter->client_uuid, [
                'text' => 'Butter',
                'angebot' => '1',
            ])->assertSessionHasNoErrors();

        $this->assertTrue($butter->fresh()->is_offer);
    }

    /**
     * Ein Aufruf, der das Feld gar nicht kennt, darf ein Angebot nicht
     * versehentlich löschen. Fehlendes Feld heißt "nicht anfassen".
     */
    public function test_bearbeiten_ohne_angebotsfeld_laesst_es_unveraendert(): void
    {
        [$user] = $this->familie();
        $this->eintragen($user, 'Butter', angebot: true);
        $butter = $this->eintrag($user, 'Butter');

        $this->actingAs($user)->from('/einkaufsliste')
            ->put('/einkaufsliste/eintraege/'.$butter->client_uuid, [
                'text' => 'Butter',
                'note' => 'die gesalzene',
            ])->assertSessionHasNoErrors();

        $butter = $butter->fresh();
        $this->assertTrue($butter->is_offer);
        $this->assertSame('die gesalzene', $butter->note);
    }

    /**
     * Ein Angebot gilt für die Woche, in der es im Prospekt stand.
     *
     * Wer die Aktionsbutter nicht bekommen hat, braucht nächste Woche Butter
     * -- aber nicht mehr zum Aktionspreis. Der Übertrag kopiert den Eintrag
     * mit replicate(); ohne ausdrücklichen Ausschluss wanderte die Markierung
     * mit und schickte einen zum Aktionstisch, auf dem sie längst nicht mehr
     * liegt.
     */
    public function test_beim_uebertrag_in_die_neue_woche_faellt_das_angebot_weg(): void
    {
        [$user] = $this->familie();
        $this->eintragen($user, 'Butter', angebot: true);
        $alte = $this->aktiveListe($user);

        $this->actingAs($user)->post('/einkaufsliste/archivieren', [
            'offene_uebernehmen' => 1,
        ])->assertRedirect();

        $neue = $this->aktiveListe($user);
        $this->assertNotSame($alte->id, $neue->id);

        $butter = $this->eintraege($user, $neue)->firstWhere('raw_name', 'Butter');
        $this->assertNotNull($butter, 'Die fehlende Butter muss mitwandern.');
        $this->assertFalse($butter->is_offer, 'Das Angebot muss in der alten Woche bleiben.');

        // Im Archiv bleibt festgehalten, dass es eines war.
        $this->assertTrue($this->eintraege($user, $alte)->firstWhere('raw_name', 'Butter')->is_offer);
    }

    public function test_die_markierung_steht_am_pc_und_auf_dem_telefon(): void
    {
        [$user] = $this->familie();
        $this->eintragen($user, 'Butter', angebot: true);
        $this->eintragen($user, 'Brot');

        $pc = $this->actingAs($user)->get('/einkaufsliste')->assertOk()->getContent();
        $handy = $this->actingAs($user)->get('/einkaufsliste/einkaufen')->assertOk()->getContent();

        // Genau einmal je Ansicht -- an der Butter, nicht am Brot.
        $this->assertSame(1, substr_count($pc, 'class="angebot-marke"'));
        $this->assertSame(1, substr_count($handy, 'class="angebot-marke"'));
    }

    public function test_die_eingabe_bietet_das_haekchen_an(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/einkaufsliste')
            ->assertOk()
            ->assertSee('name="angebot"', escape: false);
    }

    /**
     * Der Abgleich zwischen Geräten muss das Feld kennen, sonst verliert ein
     * zweites Gerät die Markierung.
     */
    public function test_der_sync_stand_traegt_das_angebot(): void
    {
        [$user] = $this->familie();
        $this->eintragen($user, 'Butter', angebot: true);

        $eintraege = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')
            ->assertOk()
            ->json('eintraege');

        $this->assertTrue(collect($eintraege)->firstWhere('name', 'Butter')['angebot']);
    }

    protected function eintragen(User $user, string $text, bool $angebot = false): void
    {
        $this->actingAs($user)
            ->post('/einkaufsliste/eintraege', array_filter([
                'text' => $text,
                'angebot' => $angebot ? '1' : null,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}
