<?php

namespace Tests\Feature\Rezepte;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Models\Tag;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rezepte aus einer JSON-Datei übernehmen (D-09).
 */
class RezeptImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_die_seite_zeigt_den_auftrag_fuer_die_ki(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/rezepte/import')
            ->assertOk()
            ->assertSee('Rezepte importieren')
            ->assertSee('Die Antwort einfügen')
            // Der Prompt muss die Feldnamen nennen, sonst passt das Ergebnis nicht.
            ->assertSee('"zutaten"', false)
            ->assertSee('sammelbegriff')
            ->assertSee('"quick", "normal", "elaborate"', false);
    }

    public function test_die_vorschau_legt_noch_nichts_an(): void
    {
        [$user] = $this->familie();

        $antwort = $this->actingAs($user)
            ->post('/rezepte/import/vorschau', ['inhalt' => $this->eingabe()])
            ->assertOk();

        $antwort->assertSee('Linsencurry')->assertSee('Wird angelegt');

        $this->assertSame(0, $this->imKontext($user, fn () => Recipe::count()));
    }

    public function test_erst_die_bestaetigung_legt_die_rezepte_an(): void
    {
        [$user] = $this->familie();

        $kennung = $this->actingAs($user)
            ->post('/rezepte/import/vorschau', ['inhalt' => $this->eingabe()])
            ->assertOk()->viewData('kennung');

        $this->actingAs($user)->post('/rezepte/import', ['kennung' => $kennung])
            ->assertRedirect(route('rezepte.index'))
            ->assertSessionHasNoErrors();

        $rezept = $this->imKontext($user, fn () => Recipe::with(['ingredients', 'tags'])->firstOrFail());

        $this->assertSame('Linsencurry', $rezept->name);
        $this->assertSame(4, $rezept->servings);
        $this->assertSame('normal', $rezept->effort);
        $this->assertCount(2, $rezept->ingredients);
        $this->assertCount(1, $rezept->tags);
        $this->assertSame('protein', $rezept->tags->first()->type);
    }

    /**
     * Der Artikelkatalog wird verknüpft, aber nicht erweitert: Ein Import von
     * zwanzig Rezepten würde sonst hundert ungeprüfte Artikel anlegen.
     */
    public function test_zutaten_werden_mit_vorhandenen_artikeln_verknuepft(): void
    {
        [$user] = $this->familie();

        $linsen = $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Rote Linsen']));
        $vorher = $this->imKontext($user, fn () => Product::count());

        $this->importieren($user, $this->eingabe());

        $zutaten = $this->imKontext($user, fn () => Recipe::firstOrFail()->ingredients()->orderBy('sort')->get());

        $this->assertSame($linsen->id, $zutaten->firstWhere('raw_name', 'rote Linsen')->product_id);
        // Zwiebeln kennt der Katalog nicht -- die Zutat bleibt Text.
        $this->assertNull($zutaten->firstWhere('raw_name', 'Zwiebeln')->product_id);
        $this->assertSame($vorher, $this->imKontext($user, fn () => Product::count()));
    }

    public function test_vorhandene_rezepte_werden_uebersprungen(): void
    {
        [$user] = $this->familie();

        $this->imKontext($user, fn () => Recipe::factory()->create(['name' => 'Linsencurry']));

        $antwort = $this->actingAs($user)
            ->post('/rezepte/import/vorschau', ['inhalt' => $this->eingabe()])
            ->assertOk();

        $this->assertSame(['Linsencurry'], $antwort->viewData('vorschau')['vorhanden']);
        $this->assertSame([], $antwort->viewData('vorschau')['neu']);
        $this->assertSame(1, $this->imKontext($user, fn () => Recipe::count()));
    }

    public function test_fehlerhafte_eintraege_werden_benannt_und_der_rest_bleibt(): void
    {
        [$user] = $this->familie();

        $inhalt = json_encode(['rezepte' => [
            ['name' => 'Ohne Zutaten'],
            ['name' => 'Falscher Aufwand', 'aufwand' => 'gemütlich', 'zutaten' => [['name' => 'Salz']]],
            ['name' => 'Gutes Rezept', 'zutaten' => [['name' => 'Salz']]],
        ]]);

        $antwort = $this->actingAs($user)
            ->post('/rezepte/import/vorschau', ['inhalt' => $inhalt])
            ->assertOk();

        $vorschau = $antwort->viewData('vorschau');

        $this->assertSame(['Gutes Rezept'], $vorschau['neu']);
        $this->assertCount(2, $vorschau['fehler']);
        $this->assertStringContainsString('Ohne Zutaten', $vorschau['fehler'][0]);
    }

    public function test_kaputtes_json_endet_in_einer_meldung_statt_in_einem_fehler(): void
    {
        [$user] = $this->familie();

        $antwort = $this->actingAs($user)->post('/rezepte/import/vorschau', [
            'inhalt' => '{ das ist kein json',
        ])->assertOk();

        $this->assertStringContainsString('kein gültiges JSON', $antwort->viewData('vorschau')['fehler'][0]);
    }

    public function test_ohne_eingabe_geht_es_nicht_weiter(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->from('/rezepte/import')
            ->post('/rezepte/import/vorschau', [])
            ->assertRedirect('/rezepte/import')
            ->assertSessionHasErrors('inhalt');
    }

    /**
     * Die geprüfte Datei liegt unter der Familie -- eine fremde Kennung trifft
     * selbst dann ins Leere, wenn jemand sie erraten hat.
     */
    public function test_eine_fremde_kennung_importiert_nichts(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $kennung = $this->actingAs($fremd)
            ->post('/rezepte/import/vorschau', ['inhalt' => $this->eingabe()])
            ->assertOk()->viewData('kennung');

        $this->actingAs($eigen)->from('/rezepte/import')
            ->post('/rezepte/import', ['kennung' => $kennung])
            ->assertRedirect('/rezepte/import')
            ->assertSessionHasErrors('inhalt');

        $this->assertSame(0, $this->imKontext($eigen, fn () => Recipe::count()));
    }

    public function test_der_import_bleibt_in_der_eigenen_familie(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->importieren($eigen, $this->eingabe());

        $this->assertSame(1, $this->imKontext($eigen, fn () => Recipe::count()));
        $this->assertSame(0, $this->imKontext($fremd, fn () => Recipe::count()));
        $this->assertSame(0, $this->imKontext($fremd, fn () => Tag::count()));
    }

    // ── Helfer ──────────────────────────────────────────────────────────

    protected function eingabe(): string
    {
        $inhalt = json_encode([
            'version' => 1,
            'rezepte' => [[
                'name' => 'Linsencurry',
                'portionen' => 4,
                'vorbereitung_minuten' => 15,
                'koch_minuten' => 25,
                'aufwand' => 'normal',
                'pflanzenpunkte' => 6,
                'zubereitung' => "1. Zwiebeln anschwitzen.\n2. Linsen zugeben.",
                'schlagworte' => [['art' => 'protein', 'name' => 'vegetarisch']],
                'zutaten' => [
                    ['name' => 'rote Linsen', 'menge' => 250, 'einheit' => 'g', 'vorrat' => true, 'sammelbegriff' => 'linsen'],
                    ['name' => 'Zwiebeln', 'menge' => 2, 'einheit' => 'Stk'],
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE);

        return $inhalt;
    }

    protected function importieren(User $user, string $inhalt): void
    {
        $kennung = $this->actingAs($user)
            ->post('/rezepte/import/vorschau', ['inhalt' => $inhalt])
            ->assertOk()->viewData('kennung');

        $this->actingAs($user)->post('/rezepte/import', ['kennung' => $kennung])
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array{0: User, 1: Family}
     */
    protected function familie(string $email = 'owner@example.test', string $name = 'Familie Test'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $family = app(FamilyProvisioner::class)->createFor($user, $name);

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

    protected function setUp(): void
    {
        parent::setUp();

        // Die geprüften Rezepte landen als Datei -- im Test in einem eigenen
        // Ablageort, damit nichts im Projekt zurückbleibt.
        Storage::fake();
    }
}
