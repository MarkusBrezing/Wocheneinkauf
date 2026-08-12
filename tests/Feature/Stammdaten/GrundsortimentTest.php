<?php

namespace Tests\Feature\Stammdaten;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Data\StarterData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Grundsortiment: rund 150 Artikel eines Vierpersonenhaushalts auf einen Klick.
 */
class GrundsortimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_die_abfrage_zeigt_was_dazukaeme(): void
    {
        [$user] = $this->familie();

        $anzahl = count(StarterData::basicAssortment());

        $this->actingAs($user)->get('/stammdaten/produkte/grundsortiment')
            ->assertOk()
            ->assertSee('Grundsortiment laden')
            ->assertSee((string) $anzahl)
            ->assertSee('Es geht nichts verloren.')
            // Warengruppen mit Bezeichnung, nicht mit Kuerzel.
            ->assertSee('Obst und Gemüse')
            ->assertDontSee('OBST_GEMUESE');
    }

    /**
     * Ein zweiter Aufruf zeigt, was schon da ist -- und nennt es beim Namen.
     * „12 waren schon da" beantwortet die eigentliche Frage nicht.
     */
    public function test_die_abfrage_nennt_die_vorhandenen_artikel(): void
    {
        [$user] = $this->familie();

        $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Mehl']));

        $antwort = $this->actingAs($user)->get('/stammdaten/produkte/grundsortiment')->assertOk();

        $antwort->assertSee('gibt es schon')->assertSee('Mehl');
        $this->assertSame(['Mehl'], $antwort->viewData('vorschau')['vorhanden']);
        $this->assertSame(count(StarterData::basicAssortment()) - 1, $antwort->viewData('vorschau')['neu']);
    }

    /**
     * Ist alles vorhanden, gibt es nichts zu tun -- und der Knopf sagt das,
     * statt einen folgenlosen Lauf anzubieten.
     */
    public function test_ohne_offene_artikel_ist_der_knopf_gesperrt(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte/grundsortiment');

        $this->actingAs($user)->get('/stammdaten/produkte/grundsortiment')
            ->assertOk()
            ->assertSee('Es ist bereits alles vorhanden')
            ->assertSee('disabled', false);
    }

    public function test_das_grundsortiment_landet_im_katalog(): void
    {
        [$user] = $this->familie();

        $this->assertSame(0, $this->imKontext($user, fn () => Product::count()));

        $this->actingAs($user)->from('/stammdaten/produkte')
            ->post('/stammdaten/produkte/grundsortiment')
            ->assertRedirect('/stammdaten/produkte')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            count(StarterData::basicAssortment()),
            $this->imKontext($user, fn () => Product::count())
        );
    }

    /**
     * Die eine Zusage des Satzes: kein Süßkram, kein Knabberzeug, keine
     * Fertiggerichte.
     */
    public function test_der_satz_enthaelt_keine_suessigkeiten_und_kein_fertigessen(): void
    {
        $namen = collect(StarterData::basicAssortment())->pluck('name')
            ->map(fn (string $n) => mb_strtolower($n));

        foreach ([
            'schokolade', 'gummibärchen', 'chips', 'kekse', 'salzstangen', 'müsliriegel',
            'speiseeis', 'pizza', 'pommes', 'fischstäbchen', 'fertiggericht',
        ] as $unerwuenscht) {
            $this->assertFalse(
                $namen->contains(fn (string $n) => str_contains($n, $unerwuenscht)),
                "„{$unerwuenscht}“ gehört nicht ins Grundsortiment."
            );
        }

        // Und keine Warengruppe „Süßes und Snacks“.
        $gruppen = collect(StarterData::basicAssortment())->pluck('group')->unique();
        $this->assertFalse($gruppen->contains('SUESS'));
    }

    public function test_jeder_artikel_bekommt_seine_warengruppe(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte/grundsortiment');

        $this->assertSame(0, $this->imKontext(
            $user,
            fn () => Product::whereNull('product_group_id')->count()
        ));

        // Stichprobe: Mehl gehört zu „Nudeln, Reis, Mehl“.
        [$mehl, $trocken] = $this->imKontext($user, fn () => [
            Product::matchingName('Mehl')->firstOrFail(),
            ProductGroup::where('code', 'TROCKEN')->firstOrFail(),
        ]);

        $this->assertSame($trocken->id, $mehl->product_group_id);
    }

    /**
     * Ohne Sollmenge wäre das Kennzeichen „Grundvorrat" nur eine Behauptung --
     * die Bestandsaufnahme könnte nicht ausrechnen, was fehlt.
     */
    public function test_vorratsartikel_bringen_ihre_sollmenge_mit(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte/grundsortiment');

        $this->imKontext($user, function () {
            $vorrat = Product::where('is_pantry_staple', true)->get();

            $this->assertGreaterThan(30, $vorrat->count());
            $this->assertSame(0, $vorrat->whereNull('pantry_target_amount')->count());
            $this->assertSame('2.000', Product::matchingName('Mehl')->firstOrFail()->pantry_target_amount);
        });
    }

    /**
     * Additiv und wiederholbar: Ein gepflegter Eintrag hat Vorrang vor der
     * Vorlage -- das ist die Zusage, die die Abfrageseite gibt.
     */
    public function test_ein_zweiter_lauf_legt_nichts_doppelt_an(): void
    {
        [$user] = $this->familie();

        // Ein von Hand gepflegter Artikel, der auch im Satz vorkommt.
        $eigenes = $this->imKontext($user, fn () => Product::factory()->create([
            'name' => 'Mehl',
            'default_unit' => 'Sack',
        ]));

        $this->actingAs($user)->post('/stammdaten/produkte/grundsortiment');
        $this->actingAs($user)->post('/stammdaten/produkte/grundsortiment');

        $this->assertSame(
            count(StarterData::basicAssortment()),
            $this->imKontext($user, fn () => Product::count())
        );

        // Der eigene Eintrag ist unverändert geblieben.
        $this->assertSame('Sack', $eigenes->fresh()->default_unit);
    }

    public function test_das_grundsortiment_bleibt_in_der_eigenen_familie(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->actingAs($eigen)->post('/stammdaten/produkte/grundsortiment');

        $this->assertSame(0, $this->imKontext($fremd, fn () => Product::count()));
    }

    public function test_nur_verwalter_duerfen_laden(): void
    {
        [$owner, $family] = $this->familie();

        $kind = User::factory()->create(['email' => 'kind@example.test']);
        app(FamilyProvisioner::class)->join($kind, $family, 'child');
        $kind->refresh();

        $this->actingAs($kind)->post('/stammdaten/produkte/grundsortiment')->assertForbidden();
        $this->assertSame(0, $this->imKontext($owner, fn () => Product::count()));

        // Auch die Abfrageseite bleibt ihr verschlossen ...
        $this->actingAs($kind)->get('/stammdaten/produkte/grundsortiment')->assertForbidden();

        // ... und der Verweis darauf steht gar nicht erst da.
        $this->actingAs($kind)->get('/stammdaten/produkte')
            ->assertOk()->assertDontSee(route('stammdaten.produkte.grundsortiment'));
    }

    // ── Helfer ──────────────────────────────────────────────────────────

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
}
