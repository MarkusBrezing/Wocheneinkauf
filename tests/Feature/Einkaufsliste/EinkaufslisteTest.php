<?php

namespace Tests\Feature\Einkaufsliste;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Services\ListSorter;

/**
 * Die taeglichen Handgriffe an der Liste: C-01 bis C-06, C-09, C-16, C-17.
 */
class EinkaufslisteTest extends EinkaufslisteTestCase
{
    public function test_freitext_wird_in_menge_einheit_und_name_zerlegt(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Vollmilch');

        $item = $this->eintrag($user, 'Vollmilch');

        $this->assertSame('2.000', $item->amount);
        $this->assertSame('l', $item->unit);
        $this->assertSame('MILCH', $item->productGroup->code, 'Die Stichwortregeln greifen auch hier (B-07).');
    }

    public function test_nicht_rechenbare_menge_bleibt_als_text_erhalten(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '1-2 Zwiebeln, moeglichst rote');

        $item = $this->eintrag($user, 'Zwiebeln');

        $this->assertNull($item->amount, 'Ein Bereich laesst sich nicht addieren.');
        $this->assertSame('1-2', $item->amount_raw);
        $this->assertSame('moeglichst rote', $item->note);
    }

    public function test_bekannter_artikel_wird_mit_dem_katalog_verknuepft(): void
    {
        [$user] = $this->familie();

        $produkt = $this->imKontext($user, function () {
            $gruppe = ProductGroup::where('code', 'TROCKEN')->firstOrFail();

            return Product::factory()->create(['name' => 'Basmatireis', 'product_group_id' => $gruppe->id]);
        });

        $this->aufDieListe($user, '2 Pck Basmatireis (Bio)');

        $item = $this->eintrag($user, 'Basmatireis (Bio)');

        // Die Normalform macht "Basmatireis (Bio)" und "Basmatireis" zum
        // selben Artikel -- deshalb greift auch dessen Warengruppe.
        $this->assertSame($produkt->id, $item->product_id);
        $this->assertSame('TROCKEN', $item->productGroup->code);
    }

    public function test_derselbe_artikel_wird_nicht_doppelt_eingetragen(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Milch');
        $this->aufDieListe($user, '1 l milch');

        $eintraege = $this->eintraege($user);

        $this->assertCount(1, $eintraege, 'C-17: zusammenfuehren statt Dublette.');
        $this->assertSame('3.000', $eintraege->first()->amount);
    }

    public function test_unvereinbare_mengen_werden_nicht_addiert_sondern_notiert(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Milch');
        $this->aufDieListe($user, '500 g Milch');

        $item = $this->eintraege($user)->first();

        // Liter und Gramm zusammenzuzaehlen waere geraten. Die Angabe geht
        // trotzdem nicht verloren.
        $this->assertSame('2.000', $item->amount);
        $this->assertSame('l', $item->unit);
        $this->assertStringContainsString('500 g', $item->note);
    }

    public function test_ein_abgehakter_artikel_wird_nicht_zusammengefuehrt(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '1 l Milch');
        $item = $this->eintraege($user)->first();

        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$item->client_uuid.'/haken')->assertRedirect();

        $this->aufDieListe($user, '1 l Milch');

        // Was im Wagen liegt, ist erledigt -- die zweite Angabe meint eine
        // zweite Packung.
        $this->assertCount(2, $this->eintraege($user));
    }

    public function test_abhaken_haelt_fest_wer_es_war(): void
    {
        [$user, $family] = $this->familie();
        $kind = $this->mitglied($family);

        $this->aufDieListe($user, 'Brot');
        $item = $this->eintraege($user)->first();

        // C-09, und zugleich die Rollenfestlegung: 'child' darf abhaken.
        $this->actingAs($kind)->post('/einkaufsliste/eintraege/'.$item->client_uuid.'/haken')->assertRedirect();

        $item->refresh();

        $this->assertTrue($item->is_checked);
        $this->assertSame($kind->id, $item->checked_by);
        $this->assertNotNull($item->checked_at);

        $this->actingAs($kind)->post('/einkaufsliste/eintraege/'.$item->client_uuid.'/haken');

        $this->assertFalse($item->refresh()->is_checked);
        $this->assertNull($item->checked_by, 'Ohne Haken gibt es auch niemanden, der ihn gesetzt hat.');
    }

    public function test_bearbeiten_liest_die_menge_neu_und_haengt_die_warengruppe_um(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '1 l Milch');
        $item = $this->eintraege($user)->first();
        $haushalt = $this->imKontext($user, fn () => ProductGroup::where('code', 'HAUSHALT')->firstOrFail());

        $this->actingAs($user)->put('/einkaufsliste/eintraege/'.$item->client_uuid, [
            'text' => '2 Pck Spülmaschinentabs',
            'note' => 'die großen',
            'product_group_id' => $haushalt->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertSame('Spülmaschinentabs', $item->raw_name);
        $this->assertSame('2.000', $item->amount);
        $this->assertSame('Pck', $item->unit);
        $this->assertSame('die großen', $item->note);
        $this->assertSame($haushalt->id, $item->product_group_id);
    }

    public function test_bearbeiten_ordnet_ohne_angabe_selbst_neu_zu(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '1 l Milch');
        $item = $this->eintraege($user)->first();

        $this->actingAs($user)->put('/einkaufsliste/eintraege/'.$item->client_uuid, [
            'text' => 'Vollkornbrot',
        ])->assertRedirect();

        // Eine Korrektur des Namens darf den Eintrag nicht im Kuehlregal
        // stehen lassen.
        $this->assertSame('BACKWAREN', $item->refresh()->productGroup->code);
    }

    public function test_loeschen_ist_weich(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Klopapier');
        $item = $this->eintraege($user)->first();

        $this->actingAs($user)->delete('/einkaufsliste/eintraege/'.$item->client_uuid)->assertRedirect();

        // Hart geloescht bekaeme ein Geraet, das gerade offline war, die
        // Loeschung nie mit (C-08).
        $this->assertSoftDeleted('shopping_list_items', ['id' => $item->id]);
        $this->assertCount(0, $this->eintraege($user));
    }

    public function test_markt_umschalten_sortiert_nach_laufreihenfolge(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Vollmilch');
        $this->aufDieListe($user, 'Bananen');
        $this->aufDieListe($user, 'Spülmittel');

        $markt = $this->imKontext($user, fn () => Market::where('is_default', true)->firstOrFail());

        $this->actingAs($user)->post('/einkaufsliste/markt', ['market_id' => $markt->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $abschnitte = $this->imKontext($user, fn () => app(ListSorter::class)
            ->sections(ShoppingList::active()->firstOrFail()));

        // Obst und Gemuese steht im Startdatensatz vor dem Kuehlregal, das
        // vor Haushalt und Drogerie kommt.
        $this->assertSame(
            ['Obst und Gemüse', 'Kühlregal', 'Haushalt und Drogerie'],
            $abschnitte->pluck('name')->all()
        );
    }

    public function test_ohne_markt_gliedert_die_liste_nach_warengruppen(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Vollmilch');
        $this->actingAs($user)->post('/einkaufsliste/markt', ['market_id' => null]);

        $abschnitte = $this->imKontext($user, fn () => app(ListSorter::class)
            ->sections(ShoppingList::active()->firstOrFail()));

        $this->assertSame(['Milchprodukte'], $abschnitte->pluck('name')->all());
    }

    public function test_erledigtes_sinkt_innerhalb_des_abschnitts_nach_unten(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Butter');
        $this->aufDieListe($user, 'Joghurt');
        $this->aufDieListe($user, 'Quark');

        $butter = $this->eintrag($user, 'Butter');
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$butter->client_uuid.'/haken');

        $abschnitte = $this->imKontext($user, fn () => app(ListSorter::class)
            ->sections(ShoppingList::active()->firstOrFail()));

        $namen = $abschnitte->first()->items->pluck('raw_name')->all();

        $this->assertSame(['Joghurt', 'Quark', 'Butter'], $namen, 'C-16');
    }

    public function test_jede_aenderung_zaehlt_die_version_hoch(): void
    {
        [$user] = $this->familie();

        // Die Liste entsteht mit dem ersten Artikel; ab da wird gezaehlt.
        $this->aufDieListe($user, 'Brot');
        $vorher = $this->aktiveListe($user)->version;

        $item = $this->eintraege($user)->first();
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$item->client_uuid.'/haken');
        $this->actingAs($user)->delete('/einkaufsliste/eintraege/'.$item->client_uuid);

        // Abhaken und Loeschen: zwei Schritte, zwei Versionen. Daran haengt
        // der Delta-Sync (C-07).
        $this->assertSame($vorher + 2, $this->aktiveListe($user)->version);
    }

    public function test_die_liste_laesst_sich_drucken_und_als_text_kopieren(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Vollmilch');

        $this->actingAs($user)->get('/einkaufsliste/drucken')
            ->assertOk()
            ->assertSee('Kühlregal')
            ->assertSee('[ ] 2 l Vollmilch');
    }
}
