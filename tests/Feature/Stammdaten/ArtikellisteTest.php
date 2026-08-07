<?php

namespace Tests\Feature\Stammdaten;

use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die Pflegetabelle des Artikelkatalogs: Filter, Sortierung, Aufbau.
 *
 * Sie wird am Rechner bedient, nicht im Laden — die Tests halten fest, was
 * dafür gebraucht wird.
 */
class ArtikellisteTest extends TestCase
{
    use RefreshDatabase;

    public function test_die_tabelle_spricht_ihre_formulare_ueber_das_form_attribut_an(): void
    {
        [$user] = $this->familie();
        $produkt = $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Vollmilch']));

        // Ein <form> zwischen <tr> und <td> wäre ungültiges HTML. Nur über das
        // form-Attribut lassen sich die Felder in echte Spalten legen.
        $this->actingAs($user)->get('/stammdaten/produkte')
            ->assertOk()
            ->assertSee('form="artikel'.$produkt->id.'"', false)
            ->assertSee('id="artikel'.$produkt->id.'"', false)
            ->assertSee('id="loeschen'.$produkt->id.'"', false);
    }

    public function test_nach_warengruppe_filtern(): void
    {
        [$user] = $this->familie();

        [$milch] = $this->imKontext($user, function () {
            $milch = ProductGroup::where('code', 'MILCH')->firstOrFail();
            $obst = ProductGroup::where('code', 'OBST_GEMUESE')->firstOrFail();

            Product::factory()->create(['name' => 'Vollmilch', 'product_group_id' => $milch->id]);
            Product::factory()->create(['name' => 'Bananen', 'product_group_id' => $obst->id]);

            return [$milch];
        });

        $this->actingAs($user)->get('/stammdaten/produkte?gruppe='.$milch->id)
            ->assertOk()
            ->assertSee('Vollmilch')
            ->assertDontSee('Bananen');
    }

    public function test_nach_grundvorrat_und_verfuegbarkeit_filtern(): void
    {
        [$user] = $this->familie();

        $this->imKontext($user, function () {
            $vorrat = Product::factory()->create(['name' => 'Mehl']);
            $vorrat->update(['is_pantry_staple' => true]);

            $miso = Product::factory()->create(['name' => 'Misopaste']);
            $asia = Market::factory()->create(['name' => 'Asialaden']);
            $miso->markets()->sync([$asia->id => ['family_id' => $miso->family_id]]);

            Product::factory()->create(['name' => 'Bananen']);
        });

        $this->actingAs($user)->get('/stammdaten/produkte?vorrat=1')
            ->assertOk()->assertSee('Mehl')->assertDontSee('Bananen');

        $this->actingAs($user)->get('/stammdaten/produkte?eingeschraenkt=1')
            ->assertOk()->assertSee('Misopaste')->assertDontSee('Bananen');
    }

    public function test_sortierung_nach_warengruppe_folgt_der_laufreihenfolge(): void
    {
        [$user] = $this->familie();

        $this->imKontext($user, function () {
            // Obst und Gemüse steht im Startdatensatz vor den Milchprodukten.
            $milch = ProductGroup::where('code', 'MILCH')->firstOrFail();
            $obst = ProductGroup::where('code', 'OBST_GEMUESE')->firstOrFail();

            Product::factory()->create(['name' => 'Aaa Vollmilch', 'product_group_id' => $milch->id]);
            Product::factory()->create(['name' => 'Zzz Bananen', 'product_group_id' => $obst->id]);
        });

        $inhalt = $this->actingAs($user)->get('/stammdaten/produkte?sortierung=gruppe')
            ->assertOk()->getContent();

        // Trotz Alphabet steht die Banane vorn: ihre Warengruppe kommt im
        // Markt zuerst -- dieselbe Ordnung wie auf der Einkaufsliste.
        $this->assertLessThan(
            strpos($inhalt, 'Aaa Vollmilch'),
            strpos($inhalt, 'Zzz Bananen'),
        );
    }

    public function test_eine_unsinnige_sortierung_faellt_auf_den_namen_zurueck(): void
    {
        [$user] = $this->familie();
        $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Vollmilch']));

        $this->actingAs($user)->get('/stammdaten/produkte?sortierung=; DROP TABLE products')
            ->assertOk()
            ->assertSee('Vollmilch');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_die_eingestellten_filter_stehen_nach_dem_absenden_wieder_im_formular(): void
    {
        [$user] = $this->familie();
        $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Vollmilch']));

        $this->actingAs($user)->get('/stammdaten/produkte?suche=Voll&vorrat=1&sortierung=neu')
            ->assertOk()
            ->assertSee('value="Voll"', false)
            ->assertSee('name="vorrat" value="1" checked', false)
            ->assertSee('<option value="neu" selected>', false);
    }

    public function test_der_filter_ueberlebt_das_blaettern(): void
    {
        [$user] = $this->familie();

        // Eine Seite fasst 100 Zeilen -- erst darüber entstehen Links, an
        // denen der Filter hängen bleiben muss.
        $this->imKontext($user, function () {
            foreach (range(1, 101) as $nummer) {
                $produkt = new Product(['name' => 'Testartikel '.str_pad((string) $nummer, 3, '0', STR_PAD_LEFT)]);
                $produkt->is_pantry_staple = true;
                $produkt->save();
            }
        });

        // withQueryString(): sonst führt Seite 2 zurück in die ungefilterte Liste.
        $this->actingAs($user)->get('/stammdaten/produkte?suche=Testartikel&vorrat=1')
            ->assertOk()
            ->assertSee('suche=Testartikel', false)
            ->assertSee('vorrat=1', false);
    }

    public function test_eine_bestaetigung_erscheint_als_einblendung(): void
    {
        [$user] = $this->familie();

        // Wie im Browser: dem Redirect folgen und die Seite ansehen, die
        // danach steht.
        $this->actingAs($user)
            ->from('/stammdaten/produkte')
            ->followingRedirects()
            ->post('/stammdaten/produkte', ['name' => 'Vollmilch'])
            ->assertOk()
            // role="status" liest ein Screenreader vor, ohne die Eingabe zu
            // unterbrechen. Das Ausblenden nach zehn Sekunden macht die
            // CSS-Animation -- ohne Skript.
            ->assertSee('class="meldung" role="status"', false)
            // Blade wandelt das schließende Anführungszeichen in &quot; um.
            ->assertSee('Artikel „Vollmilch', false)
            ->assertSee('animation: meldung-ein', false);
    }

    public function test_ein_fehler_bleibt_stehen(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Vollmilch']);

        // Wer eine Eingabe korrigieren muss, darf die Beanstandung nicht nach
        // zehn Sekunden verlieren -- Fehler stehen weiterhin fest im Seitenkopf.
        $this->actingAs($user)
            ->from('/stammdaten/produkte')
            ->followingRedirects()
            ->post('/stammdaten/produkte', ['name' => 'vollmilch'])
            ->assertOk()
            ->assertSee('class="fehler"', false)
            ->assertSee('Einen Artikel mit diesem Namen gibt es schon.')
            ->assertDontSee('class="meldung"', false);
    }

    /**
     * @return array{0: User}
     */
    protected function familie(string $email = 'owner@example.test'): array
    {
        $user = User::factory()->create(['email' => $email]);
        app(FamilyProvisioner::class)->createFor($user, 'Familie Test');

        return [$user->refresh()];
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
