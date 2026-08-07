<?php

namespace Tests\Feature\Handbuch;

use App\Core\Support\Handbuch;
use App\Core\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Das Anwenderhandbuch auf der Webseite.
 */
class HandbuchTest extends TestCase
{
    use RefreshDatabase;

    public function test_das_handbuch_ist_ohne_anmeldung_erreichbar(): void
    {
        $this->get('/handbuch')
            ->assertOk()
            ->assertSee('Anwenderhandbuch')
            ->assertSee('Erste Schritte');
    }

    public function test_ein_kapitel_wird_als_html_ausgeliefert(): void
    {
        $antwort = $this->get('/handbuch/einkaufsliste')->assertOk();

        // Markdown ist umgewandelt, nicht als Quelltext durchgereicht.
        $antwort->assertSee('<h2>Artikel aufschreiben</h2>', false);
        $antwort->assertSee('<table>', false);
        $antwort->assertDontSee('## Artikel aufschreiben');
    }

    public function test_alle_kapitel_lassen_sich_oeffnen(): void
    {
        $kapitel = Handbuch::kapitel();

        $this->assertGreaterThanOrEqual(10, count($kapitel));

        foreach ($kapitel as $k) {
            $this->get('/handbuch/'.$k['slug'])
                ->assertOk()
                ->assertSee($k['titel']);
        }
    }

    public function test_ein_unbekanntes_kapitel_endet_in_404(): void
    {
        $this->get('/handbuch/gibt-es-nicht')->assertNotFound();
    }

    /**
     * Der Slug wird nur gegen die vorhandenen Kapitel geprüft und nie in einen
     * Pfad eingesetzt -- ein Ausbruch aus dem Ordner trifft ins Leere.
     */
    public function test_kein_ausbruch_aus_dem_handbuchordner(): void
    {
        $this->assertNull(Handbuch::finden('../../.env'));
        $this->assertNull(Handbuch::finden('../Schritt-4_Einkaufsliste'));
    }

    public function test_alles_auf_einer_seite_enthaelt_jedes_kapitel(): void
    {
        $antwort = $this->get('/handbuch/komplett')->assertOk();

        foreach (Handbuch::kapitel() as $k) {
            $antwort->assertSee($k['titel']);
        }
    }

    /**
     * Die Kopfdaten kommen aus der Datei selbst -- eine zweite Liste hätte sich
     * nach der ersten Umbenennung vom Text entfernt.
     */
    public function test_kapitelangaben_stammen_aus_der_datei(): void
    {
        $kapitel = collect(Handbuch::kapitel())->keyBy('slug');

        $this->assertSame('Die Einkaufsliste', $kapitel['einkaufsliste']['titel']);
        $this->assertSame(2, $kapitel['einkaufsliste']['nummer']);
        $this->assertNotSame('', $kapitel['einkaufsliste']['teaser']);
        // Die Geräteangabe des Kapitels benennt eine Art, die es gibt.
        $this->assertStringContainsString(
            Navigation::GERAETE['handy']['kurz'],
            $kapitel['einkaufen']['geraet']
        );
    }

    public function test_die_anwendung_verlinkt_das_handbuch(): void
    {
        $this->get('/')->assertOk()->assertSee(route('handbuch.index'));
        $this->get('/impressum')->assertOk()->assertSee(route('handbuch.index'));
    }
}
