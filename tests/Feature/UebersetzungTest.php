<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Die Anwendung läuft im Betrieb auf Deutsch.
 *
 * Fehlt eine Übersetzung, gibt Laravel den Schlüssel selbst aus -- auf der
 * Registrierungsseite stand so "validation.min.string" statt einer Meldung.
 * Lokal fiel das nie auf, weil in der Entwicklung APP_LOCALE=en gilt: Die
 * englischen Texte kommen aus dem Framework, die deutschen aus lang/de.
 *
 * Diese Tests laufen deshalb ausdrücklich unter der Sprache, die im Betrieb
 * eingestellt ist -- nicht unter der, mit der entwickelt wird.
 */
class UebersetzungTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('de');
    }

    /**
     * Der ursprüngliche Fund: die Passwort-Mindestlänge.
     */
    public function test_die_meldung_zur_mindestlaenge_ist_deutsch(): void
    {
        $meldung = Validator::make(
            ['password' => 'kurz'],
            ['password' => 'string|min:12'],
        )->errors()->first('password');

        $this->assertStringNotContainsString('validation.', $meldung);
        $this->assertStringContainsString('mindestens 12 Zeichen', $meldung);
    }

    /**
     * Feldnamen sollen lesbar sein, nicht die Spalte der Datenbank nennen.
     */
    public function test_feldnamen_erscheinen_lesbar(): void
    {
        $meldung = Validator::make(
            [],
            ['product_group_id' => 'required'],
        )->errors()->first('product_group_id');

        $this->assertSame('Warengruppe ist erforderlich.', $meldung);
    }

    /**
     * Der eigentliche Wächter: Jeder Schlüssel, den das Framework auf Englisch
     * kennt, muss auch auf Deutsch da sein. Sonst taucht irgendwann wieder
     * Technik auf dem Bildschirm auf -- an einer Stelle, die niemand getestet
     * hat, weil sie selten erreicht wird.
     */
    public function test_keine_regel_ohne_deutsche_uebersetzung(): void
    {
        $englisch = Lang::get('validation', [], 'en');
        $deutsch = Lang::get('validation', [], 'de');

        $fehlend = [];

        foreach ($englisch as $regel => $text) {
            // custom und attributes sind Platzhalter fuer eigene Eintraege,
            // keine Regeln -- sie duerfen abweichen.
            if (in_array($regel, ['custom', 'attributes'], true)) {
                continue;
            }

            if (! array_key_exists($regel, $deutsch)) {
                $fehlend[] = $regel;

                continue;
            }

            // Die Groessenregeln haben Untervarianten (array, file, numeric,
            // string). Fehlt eine davon, faellt genau sie auf den Schluessel
            // zurueck -- der urspruengliche Fehler.
            if (is_array($text)) {
                foreach (array_keys($text) as $variante) {
                    if (! isset($deutsch[$regel][$variante])) {
                        $fehlend[] = "$regel.$variante";
                    }
                }
            }
        }

        $this->assertSame([], $fehlend, 'Diese Regeln haben keine deutsche Meldung.');
    }

    /**
     * Die Rückfallsprache muss eine sein, für die es Texte gibt.
     *
     * Steht sie -- wie ursprünglich in der Produktionsvorlage -- ebenfalls auf
     * "de", gibt es beim kleinsten Loch nichts, worauf zurückzufallen wäre,
     * und der Schlüssel steht auf dem Bildschirm.
     */
    public function test_die_rueckfallsprache_hat_texte(): void
    {
        $rueckfall = config('app.fallback_locale');

        $this->assertNotSame(
            'de',
            $rueckfall,
            'Die Rückfallsprache darf nicht dieselbe sein wie die Hauptsprache.'
        );

        $this->assertIsString(Lang::get('validation.required', [], $rueckfall));
        $this->assertStringNotContainsString(
            'validation.',
            Lang::get('validation.required', [], $rueckfall)
        );
    }
}
