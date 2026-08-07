<?php

namespace Tests\Feature\Einkaufsliste;

use App\Modules\Catalog\Models\Product;

/**
 * Vorschläge aus dem Artikelkatalog beim Aufschreiben (Autovervollständigung).
 */
class ArtikelVorschlaegeTest extends EinkaufslisteTestCase
{
    public function test_die_liste_bringt_die_artikel_der_familie_als_vorschlaege_mit(): void
    {
        [$user] = $this->familie();

        $this->imKontext($user, function () {
            Product::factory()->create(['name' => 'Vollmilch']);
            Product::factory()->create(['name' => 'Vollkornbrot']);
        });

        $this->actingAs($user)->get('/einkaufsliste')
            ->assertOk()
            ->assertSee('<datalist id="artikel-vorschlaege">', false)
            ->assertSee('<option value="Vollmilch">', false)
            ->assertSee('<option value="Vollkornbrot">', false)
            // Das Eingabefeld muss auf die Liste zeigen, sonst nützt sie nichts.
            ->assertSee('list="artikel-vorschlaege"', false);
    }

    public function test_vorschlaege_bleiben_in_der_eigenen_familie(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->imKontext($eigen, fn () => Product::factory()->create(['name' => 'Vollmilch']));
        $this->imKontext($fremd, fn () => Product::factory()->create(['name' => 'Kaviar']));

        $this->actingAs($eigen)->get('/einkaufsliste')
            ->assertOk()
            ->assertSee('Vollmilch')
            ->assertDontSee('Kaviar');
    }

    public function test_ohne_artikel_bleibt_die_liste_leer_und_das_skript_weg(): void
    {
        [$user] = $this->familie();

        // Kein Katalog, kein eingebettetes JSON -- die Seite soll deswegen
        // nicht anders aussehen, nur eben ohne Vorschläge.
        $this->actingAs($user)->get('/einkaufsliste')
            ->assertOk()
            ->assertSee('<datalist id="artikel-vorschlaege">', false)
            ->assertDontSee('artikel-katalog');
    }

    public function test_ein_artikelname_mit_sonderzeichen_wird_sauber_eingebettet(): void
    {
        [$user] = $this->familie();

        $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Crème <fraîche> "Bio"']));

        $antwort = $this->actingAs($user)->get('/einkaufsliste')->assertOk();

        // Weder im Attribut noch im JSON darf der Name als Markup ankommen.
        $antwort->assertDontSee('<fraîche>', false);
        $antwort->assertSee('Crème &lt;fraîche&gt; &quot;Bio&quot;', false);
    }
}
