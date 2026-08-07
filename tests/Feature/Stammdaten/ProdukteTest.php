<?php

namespace Tests\Feature\Stammdaten;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-06 und B-08: Artikelkatalog, Dublettenzusammenfuehrung, Fixartikel.
 */
class ProdukteTest extends TestCase
{
    use RefreshDatabase;

    public function test_artikel_anlegen_setzt_die_normalform(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Tomaten (Bio)'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Tomaten (Bio)',
            'name_normalized' => 'tomaten',   // Klammerzusatz entfaellt
        ]);
    }

    public function test_gleicher_artikel_in_anderer_schreibweise_wird_abgelehnt(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Tomaten']);

        // Das ist der Kern der Dublettenvermeidung: die Normalform zaehlt,
        // nicht die Eingabe.
        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => '  TOMATEN (bio) '])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $this->imKontext($user, fn () => Product::count()));
    }

    public function test_ohne_angabe_bestimmen_die_stichwortregeln_die_warengruppe(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Vollmilch 1,5%'])
            ->assertSessionHasNoErrors();

        $produkt = $this->imKontext($user, fn () => Product::where('name', 'Vollmilch 1,5%')->firstOrFail());

        $this->assertSame('MILCH', $produkt->productGroup->code);
    }

    public function test_eine_ausdrueckliche_angabe_schlaegt_die_automatik(): void
    {
        [$user] = $this->familie();
        $sonstiges = $this->imKontext($user, fn () => ProductGroup::where('code', 'SONSTIGES')->firstOrFail());

        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Vollmilch',
            'product_group_id' => $sonstiges->id,
        ]);

        $produkt = $this->imKontext($user, fn () => Product::where('name', 'Vollmilch')->firstOrFail());

        $this->assertSame('SONSTIGES', $produkt->productGroup->code);
    }

    public function test_geloeschter_artikel_gibt_seinen_namen_wieder_frei(): void
    {
        // Review-Befund R-04: ohne das Suffix ~{id} blockiert der soft-geloeschte
        // Datensatz den UNIQUE-Key dauerhaft.
        [$user] = $this->familie();

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Tomaten']);
        $produkt = $this->imKontext($user, fn () => Product::where('name', 'Tomaten')->firstOrFail());

        $this->actingAs($user)->delete('/stammdaten/produkte/'.$produkt->id)->assertRedirect();

        $this->assertSoftDeleted('products', ['id' => $produkt->id]);
        $this->assertDatabaseHas('products', ['id' => $produkt->id, 'name_normalized' => 'tomaten~'.$produkt->id]);

        $this->actingAs($user)->post('/stammdaten/produkte', ['name' => 'Tomaten'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->imKontext($user, fn () => Product::count()));
    }

    public function test_zusammenfuehren_haengt_verweise_um_und_uebernimmt_fehlende_angaben(): void
    {
        [$user] = $this->familie();

        [$behalten, $aufloesen] = $this->imKontext($user, function () {
            $gruppe = ProductGroup::where('code', 'OBST_GEMUESE')->firstOrFail();

            $behalten = Product::factory()->create(['name' => 'Tomate', 'default_unit' => null]);
            $aufloesen = Product::factory()->create(['name' => 'Tomaten rot', 'default_unit' => 'g']);
            $aufloesen->update(['product_group_id' => $gruppe->id, 'is_pantry_staple' => true]);

            return [$behalten, $aufloesen];
        });

        $this->actingAs($user)->post('/stammdaten/produkte/zusammenfuehren', [
            'behalten' => $behalten->id,
            'aufloesen' => $aufloesen->id,
        ])->assertRedirect();

        $behalten->refresh();

        $this->assertSame('g', $behalten->default_unit, 'Fehlende Einheit wird übernommen.');
        $this->assertSame($aufloesen->product_group_id, $behalten->product_group_id);
        $this->assertTrue($behalten->is_pantry_staple);
        $this->assertSoftDeleted('products', ['id' => $aufloesen->id]);
    }

    public function test_zusammenfuehren_mit_sich_selbst_wird_abgelehnt(): void
    {
        [$user] = $this->familie();
        $produkt = $this->imKontext($user, fn () => Product::factory()->create());

        $this->actingAs($user)->post('/stammdaten/produkte/zusammenfuehren', [
            'behalten' => $produkt->id,
            'aufloesen' => $produkt->id,
        ])->assertSessionHasErrors('aufloesen');
    }

    public function test_grundvorrat_traegt_kennzeichen_und_sollmenge(): void
    {
        [$user] = $this->familie();

        // Fixartikel und Grundvorrat waren einmal zwei Dinge. Seit der
        // Zusammenfuehrung gibt es nur noch dieses eine Kennzeichen am Artikel.
        $this->actingAs($user)->post('/stammdaten/produkte', [
            'name' => 'Vollmilch',
            'default_unit' => 'l',
            'is_pantry_staple' => 1,
            'pantry_target_amount' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $produkt = $this->imKontext($user, fn () => Product::matchingName('Vollmilch')->firstOrFail());

        $this->assertTrue($produkt->is_pantry_staple);
        $this->assertSame('2.000', $produkt->pantry_target_amount);
        $this->assertSame('2 l', $produkt->sollmengeAnzeige());
        $this->assertSame('MILCH', $produkt->productGroup->code);
    }

    public function test_das_kennzeichen_laesst_sich_wieder_entfernen(): void
    {
        [$user] = $this->familie();
        $produkt = $this->imKontext($user, function () {
            $p = Product::factory()->create(['name' => 'Butter']);
            $p->update(['is_pantry_staple' => true, 'pantry_target_amount' => 1]);

            return $p;
        });

        $this->actingAs($user)->put('/stammdaten/produkte/'.$produkt->id, [
            'name' => 'Butter',
            // is_pantry_staple fehlt = kein Grundvorrat mehr
        ])->assertRedirect();

        $produkt->refresh();

        $this->assertFalse($produkt->is_pantry_staple);
        $this->assertSame(0, $this->imKontext($user, fn () => Product::grundvorrat()->count()));
    }

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
     * @param  \Closure():TReturn  $callback
     * @return TReturn
     */
    protected function imKontext(User $user, \Closure $callback): mixed
    {
        return app(FamilyContext::class)->runFor($user->current_family_id, $callback);
    }
}
