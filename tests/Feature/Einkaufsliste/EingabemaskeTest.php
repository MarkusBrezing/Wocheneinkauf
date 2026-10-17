<?php

namespace Tests\Feature\Einkaufsliste;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;

/**
 * Die Eingabemaske der Einkaufsliste: getrennte Felder für Menge und Artikel,
 * Artikel zuerst, die Warengruppe bringt ein gewählter Vorschlag mit.
 */
class EingabemaskeTest extends EinkaufslisteTestCase
{
    public function test_menge_und_artikel_kommen_aus_zwei_feldern(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/einkaufsliste/eintraege', [
            'menge' => '2 l',
            'text' => 'Milch',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $eintrag = $this->eintrag($user, 'Milch');

        $this->assertSame('2.000', $eintrag->amount);
        $this->assertSame('l', $eintrag->unit);
        $this->assertSame('Milch', $eintrag->raw_name);
    }

    public function test_die_menge_darf_leer_bleiben(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/einkaufsliste/eintraege', ['text' => 'Brot'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $eintrag = $this->eintrag($user, 'Brot');

        $this->assertNull($eintrag->amount);
        $this->assertSame('Brot', $eintrag->raw_name);
    }

    /**
     * Wer aus Gewohnheit alles in ein Feld tippt, bekommt weiterhin das
     * Richtige -- gelesen wird beides mit demselben Parser.
     */
    public function test_menge_im_namensfeld_wird_weiterhin_erkannt(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/einkaufsliste/eintraege', ['text' => '500g Mehl'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $eintrag = $this->eintrag($user, 'Mehl');

        $this->assertSame('500.000', $eintrag->amount);
        $this->assertSame('g', $eintrag->unit);
    }

    public function test_bearbeiten_trennt_menge_und_artikel_ebenfalls(): void
    {
        [$user] = $this->familie();
        $this->aufDieListe($user, '2 l Milch');

        $eintrag = $this->eintrag($user, 'Milch');

        $this->actingAs($user)->from('/einkaufsliste')
            ->put('/einkaufsliste/eintraege/'.$eintrag->client_uuid, [
                'menge' => '3 l',
                'text' => 'Vollmilch',
                'note' => 'die im Glas',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $eintrag->refresh();

        $this->assertSame('3.000', $eintrag->amount);
        $this->assertSame('l', $eintrag->unit);
        $this->assertSame('Vollmilch', $eintrag->raw_name);
        $this->assertSame('die im Glas', $eintrag->note);
    }

    /**
     * Der Artikel steht vorn, weil dort der Cursor steht und weil ein
     * gewählter Vorschlag die Warengruppe selbst setzt. Die Gruppe von Hand
     * zu wählen ist die Ausnahme -- sie steht deshalb hinten.
     */
    public function test_der_artikel_steht_vor_menge_und_warengruppe(): void
    {
        [$user] = $this->familie();

        $html = $this->actingAs($user)->get('/einkaufsliste')->assertOk()->getContent();

        $formular = substr($html, strpos($html, 'action="'.route('einkaufsliste.eintraege.store').'"'));
        $formular = substr($formular, 0, strpos($formular, '</form>'));

        $this->assertLessThan(
            strpos($formular, 'id="menge"'),
            strpos($formular, 'id="text"'),
            'Das Artikelfeld muss vor der Menge stehen.'
        );
        $this->assertLessThan(
            strpos($formular, 'id="product_group_id"'),
            strpos($formular, 'id="menge"'),
            'Die Menge muss vor der Warengruppe stehen.'
        );
    }

    public function test_die_vorschlaege_kennen_warengruppe_und_einheit_ihrer_artikel(): void
    {
        [$user] = $this->familie();

        $gruppe = $this->imKontext($user, fn () => ProductGroup::where('code', 'MILCH')->firstOrFail());

        $this->imKontext($user, function () use ($gruppe) {
            Product::factory()->create(['name' => 'Vollmilch', 'product_group_id' => $gruppe->id, 'default_unit' => 'l']);
            Product::factory()->create(['name' => 'Schraubenzieher', 'product_group_id' => null, 'default_unit' => null]);
        });

        $antwort = $this->actingAs($user)->get('/einkaufsliste')->assertOk();

        // Der Katalog im Quelltext trägt je Artikel Warengruppe und Einheit --
        // damit setzt ein gewählter Vorschlag die Gruppe und zeigt die
        // Einheit im Mengenfeld.
        $antwort->assertSee('{"name":"Vollmilch","gruppe":'.$gruppe->id.',"einheit":"l"}', false);
        $antwort->assertSee('{"name":"Schraubenzieher","gruppe":null,"einheit":null}', false);

        // Die Namen der Warengruppen reisen mit, sonst stünde in jeder
        // Vorschlagszeile nur eine Nummer.
        $antwort->assertSee('{"id":'.$gruppe->id.',"label":"Milchprodukte"}', false);

        // Und das Eingabefeld weiß, welche Auswahl und welches Mengenfeld
        // zu ihm gehören.
        $antwort->assertSee('data-gruppe="#product_group_id"', false);
        $antwort->assertSee('data-menge="#menge"', false);
    }
}
