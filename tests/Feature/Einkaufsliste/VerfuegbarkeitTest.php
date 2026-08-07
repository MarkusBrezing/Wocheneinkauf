<?php

namespace Tests\Feature\Einkaufsliste;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Services\ListSorter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Verfügbarkeit je Markt: Tomaten gibt es überall, Misopaste nur im Asialaden.
 *
 * Eingetragen wird nur die Ausnahme -- ein Artikel ohne jede Zuordnung ist
 * überall erhältlich.
 */
class VerfuegbarkeitTest extends EinkaufslisteTestCase
{
    public function test_ein_artikel_ohne_zuordnung_ist_ueberall_erhaeltlich(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Tomaten'])
            ->assertRedirect()->assertSessionHasNoErrors();

        [$tomaten, $markt] = $this->imKontext($user, fn () => [
            Product::matchingName('Tomaten')->firstOrFail(),
            Market::where('is_default', true)->firstOrFail(),
        ]);

        $this->assertTrue($tomaten->istUeberallErhaeltlich());
        $this->assertTrue($tomaten->istErhaeltlichIn($markt));
        $this->assertNull($tomaten->verfuegbarkeitshinweis());
    }

    public function test_verfuegbarkeit_laesst_sich_auf_maerkte_einschraenken(): void
    {
        [$user] = $this->familie();
        $asialaden = $this->imKontext($user, fn () => Market::factory()->create(['name' => 'Asialaden']));

        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Misopaste',
            'verfuegbarkeit' => 'auswahl',
            'maerkte' => [$asialaden->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $miso = $this->imKontext($user, fn () => Product::matchingName('Misopaste')->firstOrFail());

        $this->assertFalse($miso->istUeberallErhaeltlich());
        $this->assertTrue($miso->istErhaeltlichIn($asialaden));
        $this->assertSame('nur bei Asialaden', $miso->verfuegbarkeitshinweis());

        $supermarkt = $this->imKontext($user, fn () => Market::where('is_default', true)->firstOrFail());
        $this->assertFalse($miso->istErhaeltlichIn($supermarkt));
    }

    public function test_zurueck_auf_ueberall_loescht_die_einschraenkung(): void
    {
        [$user] = $this->familie();
        $asialaden = $this->imKontext($user, fn () => Market::factory()->create(['name' => 'Asialaden']));

        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Sojasauce',
            'verfuegbarkeit' => 'auswahl',
            'maerkte' => [$asialaden->id],
        ]);

        $soja = $this->imKontext($user, fn () => Product::matchingName('Sojasauce')->firstOrFail());

        $this->actingAs($user)->put('/stammdaten/produkte/'.$soja->id, [
            'name' => 'Sojasauce',
            'verfuegbarkeit' => 'ueberall',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($soja->fresh()->istUeberallErhaeltlich());
        $this->assertDatabaseCount('market_product', 0);
    }

    public function test_ein_formular_ohne_das_feld_laesst_die_pflege_unangetastet(): void
    {
        [$user] = $this->familie();
        $asialaden = $this->imKontext($user, fn () => Market::factory()->create(['name' => 'Asialaden']));

        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Misopaste',
            'verfuegbarkeit' => 'auswahl',
            'maerkte' => [$asialaden->id],
        ]);

        $miso = $this->imKontext($user, fn () => Product::matchingName('Misopaste')->firstOrFail());

        // Etwa ein älteres Frontend, das die Verfügbarkeit nicht kennt: es darf
        // die gepflegte Einschränkung nicht stillschweigend wegwerfen.
        $this->actingAs($user)->put('/stammdaten/produkte/'.$miso->id, [
            'name' => 'Misopaste',
            'default_unit' => 'g',
        ])->assertRedirect();

        $this->assertFalse($miso->fresh()->istUeberallErhaeltlich());
    }

    public function test_ein_fremder_markt_laesst_sich_nicht_unterschieben(): void
    {
        [$user] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $fremderMarkt = $this->imKontext($fremd, fn () => Market::where('is_default', true)->firstOrFail());

        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Misopaste',
            'verfuegbarkeit' => 'auswahl',
            'maerkte' => [$fremderMarkt->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Die ID wird über den Global Scope nachgeladen und trifft ins Leere
        // (Ebene 1½) -- kein Datenbankfehler, keine fremde Zuordnung.
        $miso = $this->imKontext($user, fn () => Product::matchingName('Misopaste')->firstOrFail());

        $this->assertTrue($miso->istUeberallErhaeltlich());
        $this->assertDatabaseCount('market_product', 0);
    }

    public function test_die_datenbank_lehnt_eine_familienuebergreifende_zuordnung_ab(): void
    {
        [$user, $family] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $eigenerArtikel = $this->imKontext($user, fn () => Product::factory()->create(['name' => 'Misopaste']));
        $fremderMarkt = $this->imKontext($fremd, fn () => Market::where('is_default', true)->firstOrFail());

        // Ebene 2: die Verbund-Fremdschlüssel (R-03) greifen auch an Eloquent
        // vorbei.
        $this->expectException(QueryException::class);

        DB::table('market_product')->insert([
            'family_id' => $family->id,
            'market_id' => $fremderMarkt->id,
            'product_id' => $eigenerArtikel->id,
        ]);
    }

    public function test_die_liste_sammelt_am_ende_was_es_hier_nicht_gibt(): void
    {
        [$user] = $this->familie();
        $asialaden = $this->imKontext($user, fn () => Market::factory()->create(['name' => 'Asialaden']));

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Tomaten']);
        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Misopaste',
            'verfuegbarkeit' => 'auswahl',
            'maerkte' => [$asialaden->id],
        ]);

        $supermarkt = $this->imKontext($user, fn () => Market::where('is_default', true)->firstOrFail());
        $this->actingAs($user)->post('/einkaufsliste/markt', ['market_id' => $supermarkt->id]);

        $this->aufDieListe($user, 'Tomaten');
        $this->aufDieListe($user, '100 g Misopaste');
        $this->aufDieListe($user, 'Ahornsirup');   // Freitext ohne Katalog

        $abschnitte = $this->imKontext($user, fn () => app(ListSorter::class)
            ->sections(ShoppingList::active()->firstOrFail()));

        $letzter = $abschnitte->last();

        $this->assertSame(ListSorter::ANDERSWO, $letzter->name);
        $this->assertSame(['Misopaste'], $letzter->items->pluck('raw_name')->all());

        // Der Freitext-Eintrag steht weiter im normalen Teil: über ihn ist
        // nichts bekannt, und eine Warnung ins Blaue wäre schlimmer als keine.
        $this->assertTrue(
            $abschnitte->slice(0, -1)->flatMap->items->contains('raw_name', 'Ahornsirup')
        );
    }

    public function test_im_passenden_markt_steht_der_artikel_im_regal(): void
    {
        [$user] = $this->familie();
        $asialaden = $this->imKontext($user, function () {
            $markt = Market::factory()->create(['name' => 'Asialaden']);
            $bereich = $markt->sections()->create([
                'name' => 'Trockenregal', 'sort' => 10, 'family_id' => $markt->family_id,
            ]);
            $bereich->productGroups()->sync([
                ProductGroup::where('code', 'GEWUERZE')->value('id') => ['family_id' => $markt->family_id],
            ]);

            return $markt;
        });

        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Misopaste',
            'product_group_id' => $this->imKontext($user, fn () => ProductGroup::where('code', 'GEWUERZE')->value('id')),
            'verfuegbarkeit' => 'auswahl',
            'maerkte' => [$asialaden->id],
        ]);

        $this->actingAs($user)->post('/einkaufsliste/markt', ['market_id' => $asialaden->id]);
        $this->aufDieListe($user, '100 g Misopaste');

        $abschnitte = $this->imKontext($user, fn () => app(ListSorter::class)
            ->sections(ShoppingList::active()->firstOrFail()));

        $this->assertSame(['Trockenregal'], $abschnitte->pluck('name')->all());
    }

    public function test_der_hinweis_steht_auf_der_liste_und_im_sync(): void
    {
        [$user] = $this->familie();
        $asialaden = $this->imKontext($user, fn () => Market::factory()->create(['name' => 'Asialaden']));

        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Misopaste',
            'verfuegbarkeit' => 'auswahl',
            'maerkte' => [$asialaden->id],
        ]);

        $supermarkt = $this->imKontext($user, fn () => Market::where('is_default', true)->firstOrFail());
        $this->actingAs($user)->post('/einkaufsliste/markt', ['market_id' => $supermarkt->id]);

        // Der Hinweis kommt schon beim Aufschreiben, nicht erst im Laden.
        $this->actingAs($user)->post('/einkaufsliste/eintraege', ['text' => '100 g Misopaste'])
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'nur bei Asialaden'));

        $this->actingAs($user)->get('/einkaufsliste')->assertOk()->assertSee('nur bei Asialaden');
        $this->actingAs($user)->get('/einkaufsliste/drucken')->assertOk()->assertSee('nur bei Asialaden');

        $this->actingAs($user)->getJson('/api/einkaufsliste/stand')
            ->assertOk()
            ->assertJsonPath('eintraege.0.nur_bei_maerkten', [$asialaden->id]);
    }

    public function test_zusammenfuehren_uebernimmt_die_gepflegte_verfuegbarkeit(): void
    {
        [$user] = $this->familie();
        $asialaden = $this->imKontext($user, fn () => Market::factory()->create(['name' => 'Asialaden']));

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Misopaste']);
        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Miso Paste hell',
            'verfuegbarkeit' => 'auswahl',
            'maerkte' => [$asialaden->id],
        ]);

        [$behalten, $aufloesen] = $this->imKontext($user, fn () => [
            Product::matchingName('Misopaste')->firstOrFail(),
            Product::matchingName('Miso Paste hell')->firstOrFail(),
        ]);

        $this->actingAs($user)->post('/stammdaten/produkte/zusammenfuehren', [
            'behalten' => $behalten->id,
            'aufloesen' => $aufloesen->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Wer die Einschränkung einmal gepflegt hat, soll sie nicht durch ein
        // Zusammenführen verlieren.
        $this->assertSame('nur bei Asialaden', $behalten->fresh()->verfuegbarkeitshinweis());
    }
}
