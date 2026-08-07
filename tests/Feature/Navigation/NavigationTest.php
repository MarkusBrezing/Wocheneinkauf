<?php

namespace Tests\Feature\Navigation;

use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigation in zwei Ebenen und die Gerätekennzeichnung der Seiten.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_die_erste_ebene_zeigt_alle_bereiche(): void
    {
        $antwort = $this->actingAs($this->nutzer())->get('/dashboard')->assertOk();

        foreach (['Einkaufen', 'Planen', 'Vorrat', 'Stammdaten', 'Verwaltung'] as $bereich) {
            $antwort->assertSee($bereich);
        }
    }

    public function test_die_zweite_ebene_zeigt_nur_den_offenen_bereich(): void
    {
        $antwort = $this->actingAs($this->nutzer())->get('/stammdaten/produkte')->assertOk();

        $antwort->assertSee('Seiten im Bereich Stammdaten');
        // Die Seiten anderer Bereiche stehen nicht im Menü -- das war der
        // Zustand vorher.
        $antwort->assertDontSee('Seiten im Bereich Einkaufen');
    }

    public function test_die_uebersicht_hat_keine_zweite_ebene(): void
    {
        $this->actingAs($this->nutzer())->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Seiten im Bereich');
    }

    public function test_die_seite_sagt_fuer_welches_geraet_sie_gebaut_ist(): void
    {
        $nutzer = $this->nutzer();

        $this->actingAs($nutzer)->get('/stammdaten/produkte')->assertOk()->assertSee('Für den PC');
        $this->actingAs($nutzer)->get('/einkaufsliste')->assertOk()->assertSee('PC und Tablet');
    }

    /**
     * Unterseiten ohne eigenen Menüeintrag markieren trotzdem den richtigen
     * Bereich -- ein einzelnes Rezept gehört zu „Rezepte".
     */
    public function test_der_laengste_treffer_gewinnt(): void
    {
        $this->assertSame('planen', Navigation::aktiv('rezepte.show')['gruppe']);

        // „wochenplan.historie" beginnt auch mit „wochenplan.", gehört aber
        // zur Historie und nicht zum Plan.
        $this->assertSame('Historie', Navigation::aktiv('wochenplan.historie')['item']['label']);
        $this->assertSame('Wochenplan', Navigation::aktiv('wochenplan.index')['item']['label']);
    }

    public function test_seiten_ohne_geraetezuordnung_bekommen_keine_marke(): void
    {
        $this->assertNull(Navigation::geraet('dashboard'));
        $this->assertNull(Navigation::geraet(null));
        $this->assertSame('handy', Navigation::geraet('einkaufsliste.einkaufen'));
    }

    /**
     * Jeder Menüeintrag muss auf eine Route zeigen, die es gibt -- sonst
     * scheitert jede Seite der Anwendung an ihrem eigenen Menü.
     */
    public function test_alle_menueeintraege_zeigen_auf_vorhandene_routen(): void
    {
        foreach (Navigation::gruppen() as $gruppe) {
            foreach ($gruppe['items'] as $item) {
                $this->assertNotNull(
                    app('router')->getRoutes()->getByName($item['route']),
                    "Route {$item['route']} fehlt."
                );
                $this->assertArrayHasKey($item['geraet'], Navigation::GERAETE);
            }
        }
    }

    protected function nutzer(): User
    {
        $user = User::factory()->create();
        app(FamilyProvisioner::class)->createFor($user, 'Familie Test');

        return $user->refresh();
    }
}
