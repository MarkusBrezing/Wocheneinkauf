<?php

namespace Tests\Feature\Stammdaten;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die vorbereiteten Artikelsätze (`catalog:install-products`).
 *
 * Anders als der Startdatensatz laufen sie nicht automatisch bei der
 * Familienanlage — sie treffen eine Annahme darüber, wie jemand kocht und
 * einkauft.
 */
class ArtikelkatalogSaetzeTest extends TestCase
{
    use RefreshDatabase;

    public function test_der_befehl_fuellt_den_katalog_mit_grundvorrat(): void
    {
        $family = $this->familie();

        $this->artisan('catalog:install-products', ['--family' => $family->public_id, '--satz' => ['vorrat']])
            ->assertSuccessful();

        app(FamilyContext::class)->runFor($family->getKey(), function () {
            $this->assertGreaterThan(40, Product::count());
            $this->assertSame(Product::count(), Product::where('is_pantry_staple', true)->count(),
                'Der Satz "vorrat" enthält nur Grundvorrat.');

            // Jeder Artikel bekommt eine Warengruppe -- über die
            // Stichwortregeln oder über die ausdrückliche Angabe im Satz.
            $this->assertSame(0, Product::whereNull('product_group_id')->count());

            $this->assertSame('GEWUERZE', Product::matchingName('Paprikapulver')->firstOrFail()->productGroup->code);
            $this->assertSame('KONSERVEN', Product::matchingName('Kokosmilch')->firstOrFail()->productGroup->code);
            $this->assertSame('HAUSHALT', Product::matchingName('Klopapier')->firstOrFail()->productGroup->code);
            $this->assertSame('kg', Product::matchingName('Mehl')->firstOrFail()->default_unit);
        });
    }

    public function test_der_befehl_ist_wiederholbar(): void
    {
        $family = $this->familie();

        $this->artisan('catalog:install-products', ['--family' => $family->public_id]);
        $anzahl = app(FamilyContext::class)->runFor($family->getKey(), fn () => Product::count());

        $this->artisan('catalog:install-products', ['--family' => $family->public_id])
            ->assertSuccessful();

        $this->assertSame($anzahl, app(FamilyContext::class)->runFor($family->getKey(), fn () => Product::count()));
    }

    public function test_ein_vorhandener_artikel_bleibt_unangetastet(): void
    {
        $family = $this->familie();

        $eigener = app(FamilyContext::class)->runFor($family->getKey(), function () {
            $produkt = Product::factory()->create(['name' => 'Mehl', 'default_unit' => 'Pck']);
            $produkt->update(['is_pantry_staple' => false]);

            return $produkt;
        });

        $this->artisan('catalog:install-products', ['--family' => $family->public_id]);

        $eigener->refresh();

        // Wer seine Einheit selbst gesetzt hat, soll sie behalten.
        $this->assertSame('Pck', $eigener->default_unit);
        $this->assertFalse($eigener->is_pantry_staple);
    }

    public function test_nur_die_angegebene_familie_wird_versorgt(): void
    {
        $eigene = $this->familie();
        $fremde = $this->familie('zweite@example.test', 'Familie Zwei');

        $this->artisan('catalog:install-products', ['--family' => $eigene->public_id]);

        $this->assertGreaterThan(0, app(FamilyContext::class)->runFor($eigene->getKey(), fn () => Product::count()));
        $this->assertSame(0, app(FamilyContext::class)->runFor($fremde->getKey(), fn () => Product::count()));
    }

    public function test_der_alltagssatz_bringt_frisches_ohne_grundvorrat(): void
    {
        $family = $this->familie();

        $this->artisan('catalog:install-products', ['--family' => $family->public_id, '--satz' => ['alltag']])
            ->assertSuccessful();

        app(FamilyContext::class)->runFor($family->getKey(), function () {
            $this->assertGreaterThan(80, Product::count());
            $this->assertSame(0, Product::where('is_pantry_staple', true)->count(),
                'Alltagsartikel werden nachgekauft, nicht vorausgesetzt.');
            $this->assertSame(0, Product::whereNull('product_group_id')->count());

            $this->assertSame('MILCH', Product::matchingName('Crème fraîche')->firstOrFail()->productGroup->code);
            $this->assertSame('TIEFKUEHL', Product::matchingName('Pommes')->firstOrFail()->productGroup->code);
            $this->assertSame('GETRAENKE', Product::matchingName('Apfelschorle')->firstOrFail()->productGroup->code);
        });
    }

    public function test_ohne_angabe_kommen_beide_saetze(): void
    {
        $family = $this->familie();

        $this->artisan('catalog:install-products', ['--family' => $family->public_id])->assertSuccessful();

        app(FamilyContext::class)->runFor($family->getKey(), function () {
            $this->assertGreaterThan(120, Product::count());
            $this->assertGreaterThan(0, Product::where('is_pantry_staple', true)->count());
            $this->assertGreaterThan(0, Product::where('is_pantry_staple', false)->count());
        });
    }

    public function test_ein_unbekannter_satz_endet_mit_fehler(): void
    {
        $family = $this->familie();

        $this->artisan('catalog:install-products', ['--family' => $family->public_id, '--satz' => ['quatsch']])
            ->assertFailed();

        $this->assertSame(0, app(FamilyContext::class)->runFor($family->getKey(), fn () => Product::count()));
    }

    public function test_eine_unbekannte_familie_endet_mit_fehler(): void
    {
        $this->familie();

        $this->artisan('catalog:install-products', ['--family' => 'GIBTESNICHT'])
            ->assertFailed();
    }

    protected function familie(string $email = 'owner@example.test', string $name = 'Familie Test'): Family
    {
        $user = User::factory()->create(['email' => $email]);

        return app(FamilyProvisioner::class)->createFor($user, $name);
    }
}
