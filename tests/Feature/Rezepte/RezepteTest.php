<?php

namespace Tests\Feature\Rezepte;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Http\Controllers\RecipeController;
use App\Modules\Catalog\Models\Product;
use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Models\Tag;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Das Rezeptmodul: D-01 bis D-08.
 */
class RezepteTest extends TestCase
{
    use RefreshDatabase;

    public function test_rezept_anlegen_und_zutaten_erfassen(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/rezepte', [
            'name' => 'Linsencurry',
            'servings' => 4,
            'effort' => 'quick',
            'instructions' => 'Alles in einen Topf.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rezept = $this->rezept($user, 'Linsencurry');

        $this->assertSame('linsencurry', $rezept->slug);
        $this->assertSame($user->id, $rezept->created_by);

        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/zutaten', [
            'text' => '200 g rote Linsen',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $zutat = $this->imKontext($user, fn () => $rezept->ingredients()->firstOrFail());

        // Derselbe Parser wie auf der Einkaufsliste -- eine Zerlegung, ein
        // Verhalten.
        $this->assertSame('rote Linsen', $zutat->raw_name);
        $this->assertSame('200.000', $zutat->amount);
        $this->assertSame('g', $zutat->unit);
    }

    public function test_der_slug_bleibt_je_familie_eindeutig(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/rezepte', ['name' => 'Chili', 'servings' => 4, 'effort' => 'normal']);
        $this->actingAs($user)->post('/rezepte', ['name' => 'Chili', 'servings' => 4, 'effort' => 'normal'])
            ->assertSessionHasNoErrors();

        $slugs = $this->imKontext($user, fn () => Recipe::orderBy('id')->pluck('slug')->all());

        $this->assertSame(['chili', 'chili-2'], $slugs);
    }

    public function test_eine_bekannte_zutat_wird_mit_dem_katalog_verknuepft(): void
    {
        [$user] = $this->familie();
        $produkt = $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Feta']));
        $rezept = $this->einRezept($user, 'Salat');

        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/zutaten', ['text' => '200 g Feta']);

        // D-08: erst die Verknuepfung macht aus der Zeichenkette einen
        // Artikel, den auch Liste und Vorrat kennen.
        $this->assertSame($produkt->id, $this->imKontext($user, fn () => $rezept->ingredients()->firstOrFail())->product_id);
    }

    public function test_rezept_auf_die_liste_skaliert_die_mengen(): void
    {
        [$user] = $this->familie();
        $rezept = $this->einRezept($user, 'Linsencurry', 4, [
            ['text' => '200 g Linsen'],
            ['text' => '1 Dose Kokosmilch'],
        ]);

        // Für acht statt vier Portionen: doppelte Menge (D-04).
        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/auf-die-liste', ['portionen' => 8])
            ->assertRedirect(route('einkaufsliste.index'));

        $eintraege = $this->eintraege($user);

        $this->assertSame('400.000', $eintraege->firstWhere('raw_name', 'Linsen')->amount);
        $this->assertSame('2.000', $eintraege->firstWhere('raw_name', 'Kokosmilch')->amount);
        $this->assertSame('recipe', $eintraege->first()->source);
        $this->assertSame($rezept->id, $eintraege->first()->source_ref_id);
    }

    public function test_zutaten_ohne_menge_bleiben_unskaliert(): void
    {
        [$user] = $this->familie();
        $rezept = $this->einRezept($user, 'Salat', 2, [['text' => 'Petersilie']]);

        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/auf-die-liste', ['portionen' => 6]);

        // "etwas Petersilie" bleibt "etwas Petersilie".
        $this->assertNull($this->eintraege($user)->first()->amount);
    }

    public function test_vorratszutaten_kommen_nicht_von_allein_auf_die_liste(): void
    {
        [$user] = $this->familie();
        $rezept = $this->einRezept($user, 'Linsencurry', 4, [
            ['text' => '200 g Linsen'],
            ['text' => '2 EL Olivenöl', 'is_pantry' => 1],
        ]);

        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/auf-die-liste')->assertRedirect();

        // D-05: Was im Schrank steht, muss nicht mit.
        $this->assertSame(['Linsen'], $this->eintraege($user)->pluck('raw_name')->all());
    }

    public function test_die_auswahl_im_formular_schlaegt_die_voreinstellung(): void
    {
        [$user] = $this->familie();
        $rezept = $this->einRezept($user, 'Linsencurry', 4, [
            ['text' => '200 g Linsen'],
            ['text' => '2 EL Olivenöl', 'is_pantry' => 1],
        ]);

        $zutaten = $this->imKontext($user, fn () => $rezept->ingredients()->get());
        $oel = $zutaten->firstWhere('raw_name', 'Olivenöl');

        // Wer die Vorratszutat ausdrücklich anhakt, bekommt sie -- und die
        // abgewählten bleiben draußen.
        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/auf-die-liste', [
            'zutaten' => [$oel->id],
        ])->assertRedirect();

        $this->assertSame(['Olivenöl'], $this->eintraege($user)->pluck('raw_name')->all());
    }

    public function test_gleiche_zutaten_fallen_ueber_den_sammelbegriff_zusammen(): void
    {
        [$user] = $this->familie();
        $rezept = $this->einRezept($user, 'Suppe', 4, [
            ['text' => '2 Zwiebeln', 'merge_key' => 'zwiebel'],
            ['text' => '1 rote Zwiebel', 'merge_key' => 'zwiebel'],
        ]);

        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/auf-die-liste')->assertRedirect();

        $eintraege = $this->eintraege($user);

        // D-06: ein Eintrag statt zweier, Mengen addiert.
        $this->assertCount(1, $eintraege);
        $this->assertSame('3.000', $eintraege->first()->amount);
    }

    public function test_der_einkaufsname_gewinnt_gegen_den_zutatennamen(): void
    {
        [$user] = $this->familie();
        $rezept = $this->einRezept($user, 'Dressing', 4, [
            ['text' => '3 EL Olivenöl', 'buy_label' => 'Olivenöl, gutes'],
        ]);

        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/auf-die-liste')->assertRedirect();

        $this->assertSame('Olivenöl, gutes', $this->eintraege($user)->first()->raw_name);
    }

    public function test_zwei_rezepte_verschmelzen_auf_der_liste(): void
    {
        [$user] = $this->familie();
        $eins = $this->einRezept($user, 'Suppe', 4, [['text' => '2 Zwiebeln']]);
        $zwei = $this->einRezept($user, 'Curry', 4, [['text' => '1 Zwiebeln']]);

        $this->actingAs($user)->post('/rezepte/'.$eins->public_id.'/auf-die-liste');
        $this->actingAs($user)->post('/rezepte/'.$zwei->public_id.'/auf-die-liste');

        $eintraege = $this->eintraege($user);

        // Über die Normalform des Namens, wie beim Tippen (C-17).
        $this->assertCount(1, $eintraege);
        $this->assertSame('3.000', $eintraege->first()->amount);
    }

    public function test_suche_findet_ueber_name_und_zutat(): void
    {
        [$user] = $this->familie();
        $this->einRezept($user, 'Linsencurry', 4, [['text' => 'Kokosmilch']]);
        $this->einRezept($user, 'Kartoffelsalat', 4, [['text' => 'Essiggurken']]);

        $this->actingAs($user)->get('/rezepte?suche=Kokos')
            ->assertOk()->assertSee('Linsencurry')->assertDontSee('Kartoffelsalat');

        $this->actingAs($user)->get('/rezepte?suche=salat')
            ->assertOk()->assertSee('Kartoffelsalat')->assertDontSee('Linsencurry');
    }

    public function test_tags_filtern_die_liste(): void
    {
        [$user] = $this->familie();
        $curry = $this->einRezept($user, 'Linsencurry');
        $this->einRezept($user, 'Rinderbraten');

        $this->actingAs($user)->put('/rezepte/'.$curry->public_id.'/schlagworte', [
            'neuer_tag' => 'vegetarisch',
            'neuer_tag_typ' => 'diet',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tag = $this->imKontext($user, fn () => Tag::where('name', 'vegetarisch')->firstOrFail());

        $this->actingAs($user)->get('/rezepte?tags[]='.$tag->id)
            ->assertOk()->assertSee('Linsencurry')->assertDontSee('Rinderbraten');
    }

    public function test_favorit_laesst_sich_umschalten(): void
    {
        [$user] = $this->familie();
        $rezept = $this->einRezept($user, 'Linsencurry');

        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/favorit')->assertRedirect();
        $this->assertTrue($rezept->fresh()->is_favorite);

        $this->actingAs($user)->get('/rezepte?favoriten=1')->assertOk()->assertSee('Linsencurry');

        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/favorit');
        $this->assertFalse($rezept->fresh()->is_favorite);
    }

    public function test_rezept_bearbeiten_und_loeschen(): void
    {
        [$user] = $this->familie();
        $rezept = $this->einRezept($user, 'Linsencurry');

        $this->actingAs($user)->put('/rezepte/'.$rezept->public_id, [
            'name' => 'Rotes Linsencurry',
            'servings' => 6,
            'effort' => 'normal',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rezept->refresh();

        $this->assertSame('Rotes Linsencurry', $rezept->name);
        $this->assertSame('rotes-linsencurry', $rezept->slug, 'Der Slug folgt dem Namen.');
        $this->assertSame(6, $rezept->servings);

        $this->actingAs($user)->delete('/rezepte/'.$rezept->public_id)
            ->assertRedirect(route('rezepte.index'));

        $this->assertSoftDeleted('recipes', ['id' => $rezept->id]);
    }

    public function test_ein_fremdes_rezept_endet_in_404(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $rezept = $this->einRezept($eigen, 'Linsencurry', 4, [['text' => '200 g Linsen']]);
        $zutat = $this->imKontext($eigen, fn () => $rezept->ingredients()->firstOrFail());

        $this->actingAs($fremd)->get('/rezepte/'.$rezept->public_id)->assertNotFound();
        $this->actingAs($fremd)->put('/rezepte/'.$rezept->public_id, ['name' => 'X', 'servings' => 2, 'effort' => 'quick'])->assertNotFound();
        $this->actingAs($fremd)->post('/rezepte/'.$rezept->public_id.'/auf-die-liste')->assertNotFound();
        $this->actingAs($fremd)->delete('/rezepte/zutaten/'.$zutat->id)->assertNotFound();

        $this->assertNotNull($rezept->fresh());
    }

    public function test_die_datenbank_lehnt_eine_familienuebergreifende_zutat_ab(): void
    {
        [$eigen] = $this->familie();
        [$fremd, $fremdeFamilie] = $this->familie('fremd@example.test', 'Familie Fremd');

        $rezept = $this->einRezept($eigen, 'Linsencurry');

        // Ebene 2: der Verbund (recipe_id, family_id) laesst das nicht zu.
        $this->expectException(QueryException::class);

        DB::table('recipe_ingredients')->insert([
            'family_id' => $fremdeFamilie->id,
            'recipe_id' => $rezept->id,
            'raw_name' => 'Untergeschoben',
        ]);
    }

    public function test_jede_familie_sieht_nur_ihre_rezepte(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->einRezept($eigen, 'Linsencurry');
        $this->einRezept($fremd, 'Rinderbraten');

        $this->actingAs($fremd)->get('/rezepte')
            ->assertOk()->assertSee('Rinderbraten')->assertDontSee('Linsencurry');
    }

    /**
     * Die Liste ist nach Küche gegliedert.
     *
     * Die Küche ist die Achse, nach der man vor dem Rezeptregal sucht -- und
     * die, nach der der Wochenplan für Abwechslung sorgt. Eine Gruppe mit
     * zwölf und eine mit zwei Einträgen erklärt, warum die Vorschläge sich
     * wiederholen.
     */
    public function test_die_rezeptliste_ist_nach_kueche_gegliedert(): void
    {
        [$user] = $this->familie();

        $this->mitKueche($user, 'Lasagne', 'italienisch');
        $this->mitKueche($user, 'Linsencurry', 'indisch');
        $this->einRezept($user, 'Etwas Namenloses');

        $inhalt = $this->actingAs($user)->get('/rezepte')->assertOk()->getContent();

        $this->assertStringContainsString('italienisch', $inhalt);
        $this->assertStringContainsString('indisch', $inhalt);
        $this->assertStringContainsString(RecipeController::OHNE_KUECHE, $inhalt);

        // "Ohne Küche" ist die Arbeitsliste für unvollständige Importe --
        // sichtbar, aber nicht an erster Stelle.
        $this->assertGreaterThan(
            strpos($inhalt, '>italienisch<'),
            strpos($inhalt, '>'.RecipeController::OHNE_KUECHE.'<'),
            'Die Restgruppe steht vor den Küchen.'
        );
    }

    /**
     * Zwei Küchen-Schlagworte an einem Rezept dürfen es nicht verdoppeln --
     * eine Liste, in der dieselbe Zeile zweimal steht, lässt sich nicht
     * abzählen. Es zählt das alphabetisch erste.
     */
    public function test_ein_rezept_mit_zwei_kuechen_erscheint_nur_einmal(): void
    {
        [$user] = $this->familie();

        $rezept = $this->mitKueche($user, 'Fusion-Pfanne', 'thailändisch');
        $this->imKontext($user, function () use ($rezept) {
            $tag = Tag::firstOrCreate(['type' => 'cuisine', 'name' => 'italienisch']);
            $rezept->tags()->syncWithoutDetaching([
                $tag->getKey() => ['family_id' => $rezept->family_id],
            ]);
        });

        $inhalt = $this->actingAs($user)->get('/rezepte')->assertOk()->getContent();

        $this->assertSame(
            1,
            substr_count($inhalt, 'Fusion-Pfanne</strong>'),
            'Das Rezept steht in mehr als einer Gruppe.'
        );
        // italienisch kommt alphabetisch vor thailaendisch.
        $this->assertStringContainsString('>italienisch<', $inhalt);
    }

    /**
     * Favoriten stehen doppelt: oben zum Zugreifen, unten in ihrer Küche zum
     * Vergleichen.
     */
    public function test_favoriten_stehen_zusaetzlich_oben(): void
    {
        [$user] = $this->familie();

        $rezept = $this->mitKueche($user, 'Lasagne', 'italienisch');
        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/favorit');

        $inhalt = $this->actingAs($user)->get('/rezepte')->assertOk()->getContent();

        $this->assertStringContainsString('★ Favoriten', $inhalt);
        $this->assertSame(2, substr_count($inhalt, 'Lasagne</strong>'));
    }

    /**
     * Ohne Favoriten keine Favoritengruppe -- eine leere Überschrift ist kein
     * Angebot, sondern ein Vorwurf.
     */
    public function test_ohne_favoriten_fehlt_die_gruppe(): void
    {
        [$user] = $this->familie();
        $this->mitKueche($user, 'Lasagne', 'italienisch');

        $this->actingAs($user)->get('/rezepte')->assertOk()->assertDontSee('★ Favoriten');
    }

    /**
     * Alle Gruppen starten zugeklappt.
     *
     * Die Seite beginnt damit als Überblick über die Küchen statt als Wand aus
     * Rezeptzeilen. Ein "open" an einem der Blöcke würde genau das wieder
     * einreißen -- deshalb steht es hier als Zusicherung.
     */
    public function test_die_gruppen_starten_zugeklappt(): void
    {
        [$user] = $this->familie();

        $rezept = $this->mitKueche($user, 'Lasagne', 'italienisch');
        $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/favorit');

        $inhalt = $this->actingAs($user)->get('/rezepte')->assertOk()->getContent();

        $this->assertStringContainsString('<details class="kuechenblock', $inhalt);
        $this->assertDoesNotMatchRegularExpression('/<details[^>]+kuechenblock[^>]*\sopen/', $inhalt);
    }

    /**
     * Sobald ein Filter greift, stehen die Gruppen offen.
     *
     * Zugeklappt ist der Zustand fuers Stoebern. Bleibt es auch beim Suchen
     * dabei, sieht man drei Ueberschriften statt der Treffer -- und haelt den
     * Filter fuer kaputt. Genau so ist es gemeldet worden.
     */
    public function test_ein_filter_klappt_die_gruppen_auf(): void
    {
        [$user] = $this->familie();
        $this->mitKueche($user, 'Lasagne', 'italienisch');
        $this->mitKueche($user, 'Linsencurry', 'indisch');

        // Ohne Filter: zu.
        $this->assertDoesNotMatchRegularExpression(
            '/<details[^>]+kuechenblock[^>]*\sopen/',
            $this->actingAs($user)->get('/rezepte')->assertOk()->getContent()
        );

        // Mit Suche: offen, und nur der Treffer steht da.
        $inhalt = $this->actingAs($user)->get('/rezepte?suche=Lasagne')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<details[^>]+kuechenblock[^>]*\sopen/', $inhalt);
        $this->assertStringContainsString('Lasagne', $inhalt);
        $this->assertStringNotContainsString('Linsencurry', $inhalt);
    }

    /**
     * Ein Haekchen wirkt sofort. Ohne das automatische Absenden passiert beim
     * Anhaken nichts, bis jemand zusaetzlich auf "Suchen" klickt -- was sich
     * anfuehlt, als taete der Filter nichts.
     */
    public function test_die_haekchen_senden_das_formular_ab(): void
    {
        [$user] = $this->familie();
        $this->mitKueche($user, 'Lasagne', 'italienisch');

        $inhalt = $this->actingAs($user)->get('/rezepte')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/name="favoriten"[^>]*onchange="this\.form\.submit\(\)"/s',
            $inhalt
        );
        $this->assertMatchesRegularExpression(
            '/name="tags\[\]"[^>]*onchange="this\.form\.submit\(\)"/s',
            $inhalt
        );
    }

    /**
     * Gegliedert und geblättert vertragen sich nicht: Die Zahl im Kopf einer
     * Gruppe wuerde etwas anderes behaupten als die Zeilen darunter.
     */
    public function test_die_liste_blaettert_nicht_mehr(): void
    {
        [$user] = $this->familie();

        for ($i = 1; $i <= 30; $i++) {
            $this->einRezept($user, 'Rezept '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $inhalt = $this->actingAs($user)->get('/rezepte')->assertOk()->getContent();

        $this->assertStringContainsString('Rezept 01', $inhalt);
        $this->assertStringContainsString('Rezept 30', $inhalt);
        $this->assertStringNotContainsString('pagination', $inhalt);
    }

    protected function mitKueche(User $user, string $name, string $kueche): Recipe
    {
        $rezept = $this->einRezept($user, $name);

        $this->imKontext($user, function () use ($rezept, $kueche) {
            $tag = Tag::firstOrCreate(['type' => 'cuisine', 'name' => $kueche]);
            $rezept->tags()->syncWithoutDetaching([
                $tag->getKey() => ['family_id' => $rezept->family_id],
            ]);
        });

        return $rezept;
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

    protected function rezept(User $user, string $name): Recipe
    {
        return $this->imKontext($user, fn () => Recipe::where('name', $name)->firstOrFail());
    }

    /**
     * @param  array<int, array<string, mixed>>  $zutaten
     */
    protected function einRezept(User $user, string $name, int $portionen = 4, array $zutaten = []): Recipe
    {
        $this->actingAs($user)->post('/rezepte', [
            'name' => $name,
            'servings' => $portionen,
            'effort' => 'normal',
        ])->assertSessionHasNoErrors();

        $rezept = $this->rezept($user, $name);

        foreach ($zutaten as $zutat) {
            $this->actingAs($user)->post('/rezepte/'.$rezept->public_id.'/zutaten', $zutat)
                ->assertSessionHasNoErrors();
        }

        return $rezept->refresh();
    }

    /**
     * @return Collection<int, ShoppingListItem>
     */
    protected function eintraege(User $user)
    {
        return $this->imKontext($user, fn () => ShoppingList::active()->firstOrFail()->items()->orderBy('id')->get());
    }
}
