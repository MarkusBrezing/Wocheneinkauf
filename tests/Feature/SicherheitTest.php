<?php

namespace Tests\Feature;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sicherheitszusagen, die nicht stillschweigend verlorengehen dürfen.
 */
class SicherheitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ein Artikelname landete früher roh in einem JavaScript-String:
     *
     *     onsubmit="return confirm('Artikel „NAME“ löschen?')"
     *
     * Blade wandelt das Apostroph in `&#039;` um -- der HTML-Parser macht
     * daraus wieder ein Apostroph, *bevor* JavaScript den Text sieht. Damit
     * brach der Name aus dem String aus und wurde ausgeführt. Js::from()
     * erzeugt stattdessen einen JSON-Literal, in dem das Apostroph als
     * ' stehen bleibt.
     */
    public function test_ein_artikelname_kann_nicht_aus_dem_javascript_ausbrechen(): void
    {
        [$user] = $this->familie();

        $this->imKontext($user, fn () => Product::factory()->create([
            'name' => "Mehl');alert(document.domain);//",
        ]));

        $inhalt = $this->actingAs($user)->get('/stammdaten/produkte')->assertOk()->getContent();

        // Nur das confirm-Attribut zaehlt: In value="…" ist &#039; die
        // richtige Schreibweise und voellig harmlos -- dort wird nichts
        // ausgefuehrt.
        preg_match('/onsubmit="return confirm\([^"]*"/', $inhalt, $treffer);

        $this->assertNotEmpty($treffer, 'Kein confirm-Attribut auf der Seite gefunden.');

        // Weder das rohe Apostroph noch seine HTML-Schreibweise: Der Browser
        // macht aus &#039; wieder ein Apostroph, bevor JavaScript den Text
        // sieht.
        $this->assertStringNotContainsString('&#039;);alert', $treffer[0]);
        $this->assertStringNotContainsString("');alert", $treffer[0]);

        // Sondern als JSON-Escape innerhalb des Literals.
        $this->assertStringContainsString('\\u0027', $treffer[0]);
    }

    /**
     * Dieselbe Falle steckte in jedem Löschen-Knopf der Anwendung. Kein
     * confirm() darf noch eine Blade-Ausgabe ohne Js::from() enthalten.
     */
    public function test_kein_confirm_setzt_nutzerdaten_ungeschuetzt_ein(): void
    {
        $treffer = [];

        foreach (glob(resource_path('views/**/*.blade.php'), GLOB_BRACE) as $datei) {
            foreach (file($datei) as $nummer => $zeile) {
                if (preg_match("/confirm\('[^']*\{\{(?!\s*Js::from)/", $zeile)) {
                    $treffer[] = basename($datei).':'.($nummer + 1);
                }
            }
        }

        $this->assertSame([], $treffer, 'Nutzerdaten in confirm() gehören durch Js::from().');
    }

    public function test_jede_antwort_traegt_die_schutzkopfzeilen(): void
    {
        $antwort = $this->get('/impressum')->assertOk();

        $antwort->assertHeader('X-Frame-Options', 'DENY');
        $antwort->assertHeader('X-Content-Type-Options', 'nosniff');
        $antwort->assertHeader('Referrer-Policy', 'same-origin');

        $csp = $antwort->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    /**
     * Der Endpunkt ist ohne Anmeldung erreichbar. Wer Laravel- und PHP-Version
     * kennt, sucht gezielt nach passenden Lücken.
     */
    public function test_der_health_endpunkt_verraet_keine_versionen(): void
    {
        $antwort = $this->get('/health')->assertOk();

        $antwort->assertJsonStructure(['status', 'db', 'migrationen' => ['offen'], 'time']);
        $antwort->assertJsonMissingPath('laravel');
        $antwort->assertJsonMissingPath('php');
        $antwort->assertJsonMissingPath('migrationen.letzte');
    }

    /**
     * Ohne Familienkontext liefert der Global Scope nichts -- er scheitert
     * geschlossen, nicht offen.
     */
    public function test_ohne_familienkontext_ist_die_datenbank_leer(): void
    {
        [$user] = $this->familie();
        $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Mehl']));

        // Außerhalb jedes Kontexts -- so, wie eine vergessene Zuweisung wirkt.
        $this->assertSame(0, Product::count());
    }

    // ── Helfer ──────────────────────────────────────────────────────────

    /**
     * @return array{0: User, 1: Family}
     */
    protected function familie(string $email = 'owner@example.test'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $family = app(FamilyProvisioner::class)->createFor($user, 'Familie Test');

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
}
