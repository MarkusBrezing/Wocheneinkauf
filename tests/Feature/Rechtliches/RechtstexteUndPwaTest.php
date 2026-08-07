<?php

namespace Tests\Feature\Rechtliches;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Schritt 9: Rechtstexte und PWA.
 */
class RechtstexteUndPwaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ohne Anmeldung erreichbar -- ein Impressum hinter einem Login ist keines.
     */
    public function test_die_rechtstexte_sind_ohne_anmeldung_erreichbar(): void
    {
        $this->get('/impressum')->assertOk()->assertSee('Impressum');
        $this->get('/datenschutz')->assertOk()->assertSee('Datenschutzerklärung');
        $this->get('/nutzungsbedingungen')->assertOk()->assertSee('Nutzungsbedingungen');
    }

    public function test_fehlende_pflichtangaben_fallen_auf(): void
    {
        config(['rechtliches.betreiber.name' => null]);

        $this->get('/impressum')->assertOk()->assertSee('[bitte eintragen]');
    }

    public function test_vorhandene_angaben_stehen_im_impressum(): void
    {
        config([
            'rechtliches.betreiber.name' => 'Markus Brezing',
            'rechtliches.betreiber.strasse' => 'Musterweg 1',
            'rechtliches.betreiber.ort' => '12345 Musterstadt',
            'rechtliches.betreiber.email' => 'kontakt@example.test',
        ]);

        $this->get('/impressum')
            ->assertOk()
            ->assertSee('Markus Brezing')
            ->assertSee('kontakt@example.test')
            // Sind alle Pflichtangaben da, bleibt keine Luecke stehen.
            ->assertDontSee('[bitte eintragen]', false);
    }

    public function test_das_manifest_kommt_mit_dem_richtigen_medientyp(): void
    {
        $antwort = $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');

        $manifest = $antwort->json();

        // Wer das Symbol antippt, steht meistens im Laden.
        $this->assertSame('/einkaufsliste/einkaufen', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
    }

    public function test_die_dateien_fuer_den_offline_betrieb_liegen_bereit(): void
    {
        foreach ([
            'sw.js',
            'offline.html',
            'symbole/icon-192.png',
            'symbole/icon-512.png',
            'symbole/icon-maskable-512.png',
            'symbole/apple-touch-icon.png',
        ] as $datei) {
            $this->assertFileExists(public_path($datei));
        }
    }

    public function test_die_startseite_verlinkt_die_rechtstexte(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('impressum'))
            ->assertSee(route('datenschutz'))
            ->assertSee(route('nutzungsbedingungen'));
    }
}
