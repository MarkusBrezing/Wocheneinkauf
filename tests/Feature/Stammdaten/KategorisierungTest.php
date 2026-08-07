<?php

namespace Tests\Feature\Stammdaten;

use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Models\ProductGroupRule;
use App\Modules\Catalog\Services\ProductCategorizer;
use App\Modules\Markets\Models\Market;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * B-07 und B-09: Auto-Kategorisierung und Startdatensatz.
 */
class KategorisierungTest extends TestCase
{
    use RefreshDatabase;

    public function test_startdatensatz_versorgt_eine_neue_familie(): void
    {
        $user = User::factory()->create(['email' => 'neu@example.test']);
        $family = app(FamilyProvisioner::class)->createFor($user, 'Familie Neu');

        app(FamilyContext::class)->runFor($family->getKey(), function () {
            $this->assertSame(15, ProductGroup::count(), 'Warengruppen');
            $this->assertGreaterThan(50, ProductGroupRule::count(), 'Stichwortregeln');

            $markt = Market::where('is_default', true)->firstOrFail();
            $this->assertSame(10, $markt->sections()->count(), 'Regalbereiche');

            // B-04: die Bereiche muessen bereits Warengruppen buendeln.
            $kuehlregal = $markt->sections()->where('name', 'Kühlregal')->firstOrFail();
            $this->assertEqualsCanonicalizing(
                ['MILCH', 'EIER'],
                $kuehlregal->productGroups->pluck('code')->all(),
            );
        });
    }

    public function test_startdatensatz_ist_wiederholbar_ohne_dubletten(): void
    {
        // Der Konsolenbefehl soll auf Familien nachlaufen koennen, die vor
        // Schritt 3 entstanden sind.
        $user = User::factory()->create(['email' => 'neu@example.test']);
        $family = app(FamilyProvisioner::class)->createFor($user, 'Familie Neu');

        $this->artisan('catalog:install-starter-data', ['--family' => $family->public_id])
            ->assertSuccessful();

        app(FamilyContext::class)->runFor($family->getKey(), function () {
            $this->assertSame(15, ProductGroup::count());
            $this->assertSame(1, Market::count());
            $this->assertSame(10, Market::firstOrFail()->sections()->count());
        });
    }

    public function test_startdatensatz_ueberschreibt_keine_getroffene_wahl(): void
    {
        $user = User::factory()->create(['email' => 'neu@example.test']);
        $family = app(FamilyProvisioner::class)->createFor($user, 'Familie Neu');

        app(FamilyContext::class)->runFor($family->getKey(), function () {
            $eigener = Market::create(['name' => 'Storchenhof']);
            $eigener->makeDefault();
        });

        $this->artisan('catalog:install-starter-data', ['--family' => $family->public_id]);

        app(FamilyContext::class)->runFor($family->getKey(), function () {
            $this->assertSame('Storchenhof', Market::where('is_default', true)->value('name'));
        });
    }

    #[DataProvider('beispiele')]
    public function test_stichwortregeln_ordnen_freitext_zu(string $eingabe, ?string $erwarteterCode): void
    {
        $family = $this->familie();

        app(FamilyContext::class)->runFor($family, function () use ($eingabe, $erwarteterCode) {
            $treffer = app(ProductCategorizer::class)->categorize($eingabe);

            $this->assertSame($erwarteterCode, $treffer?->code, 'Eingabe: '.$eingabe);
        });
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function beispiele(): array
    {
        return [
            'Menge davor stört nicht' => ['2 kg Kartoffeln', 'OBST_GEMUESE'],
            'Zusatz dahinter stört nicht' => ['Vollmilch 1,5%', 'MILCH'],
            'Klammerzusatz wird ignoriert' => ['Eier (10 Stück)', 'EIER'],
            'Zusammensetzung trifft Teilwort' => ['Kirschtomaten', 'OBST_GEMUESE'],
            'Ausdruck verhindert Fehlgriff bei Zwiebeln' => ['Zwiebeln', 'OBST_GEMUESE'],
            'Unbekanntes bleibt ohne Zuordnung' => ['Schraubenzieher', null],
            'Leereingabe bleibt ohne Zuordnung' => ['   ', null],

            // Getraenke stehen im Deutschen am Wortende und muessen deshalb
            // vor den allgemeinen Regeln greifen.
            'Getränk am Wortende schlägt das Obst' => ['Apfelsaft', 'GETRAENKE'],
            'Auch bei Gemüse' => ['Tomatensaft', 'GETRAENKE'],
            'Mineralwasser ist ein Getränk' => ['Mineralwasser', 'GETRAENKE'],
            'Eistee ist ein Getränk, keine Tiefkühlware' => ['Eistee', 'GETRAENKE'],
            'Rotwein ist ein Getränk' => ['Rotwein', 'GETRAENKE'],
            'Getränk vorn im Wort greift erst zuletzt' => ['Kaffeebohnen', 'GETRAENKE'],

            // ... aber nur am Wortende. Sonst wandert der halbe Einkauf in die
            // Getraenkeabteilung.
            'Wassermelone ist kein Getränk' => ['Wassermelone', 'OBST_GEMUESE'],
            'Weintrauben sind kein Getränk' => ['Weintrauben', 'OBST_GEMUESE'],
            'Weinessig ist kein Getränk' => ['Weinessig', 'GEWUERZE'],
            'Bierschinken ist kein Getränk' => ['Bierschinken', 'FLEISCH'],
            'Kaffeesahne bleibt beim Milchprodukt' => ['Kaffeesahne', 'MILCH'],
            'Teewurst bleibt beim Fleisch' => ['Teewurst', 'FLEISCH'],

            // Umlautplurale sind auf einem Einkaufszettel der Normalfall.
            'Äpfel werden erkannt' => ['Äpfel', 'OBST_GEMUESE'],
            'Apfel wird weiterhin erkannt' => ['Apfel', 'OBST_GEMUESE'],
            'Würstchen werden erkannt' => ['Würstchen', 'FLEISCH'],
            'Wurst wird weiterhin erkannt' => ['Fleischwurst', 'FLEISCH'],

            // "eis" steckt mitten in vielen Woertern -- ohne Wortgrenze landet
            // die halbe Liste in der Tiefkuehltruhe.
            'Eis bleibt Tiefkühlware' => ['Eis', 'TIEFKUEHL'],
            'Eiswürfel ebenso' => ['Eiswürfel', 'TIEFKUEHL'],
            'Basmatireis ist keine Tiefkühlware' => ['Basmatireis', 'TROCKEN'],
            'Hackfleisch ist keine Tiefkühlware' => ['Hackfleisch', 'FLEISCH'],
            'Eisbergsalat ist keine Tiefkühlware' => ['Eisbergsalat', 'OBST_GEMUESE'],

            // Alltagswörter, die beim Anlegen der Vorratsprodukte fehlten.
            'Klopapier ist Haushalt' => ['Klopapier', 'HAUSHALT'],
            'Paprikapulver ist Gewürz, kein Gemüse' => ['Paprikapulver', 'GEWUERZE'],
            'Kokosmilch ist Konserve, kein Milchprodukt' => ['Kokosmilch', 'KONSERVEN'],
            'Tomatenmark ist Konserve' => ['Tomatenmark', 'KONSERVEN'],
            'Gemüsebrühe ist Gewürz' => ['Gemüsebrühe', 'GEWUERZE'],
            'Backpulver ist Trockenware' => ['Backpulver', 'TROCKEN'],
            'Honig ist Süßes' => ['Honig', 'SUESS'],
        ];
    }

    public function test_der_katalog_schlaegt_die_stichwortregeln(): void
    {
        $family = $this->familie();

        app(FamilyContext::class)->runFor($family, function () {
            // Die Familie hat "Milchreis" bewusst als Trockenware einsortiert,
            // obwohl die Regel "milch" greifen wuerde.
            $trocken = ProductGroup::where('code', 'TROCKEN')->firstOrFail();
            $produkt = Product::factory()->create(['name' => 'Milchreis']);
            $produkt->update(['product_group_id' => $trocken->id]);

            $this->assertSame('TROCKEN', app(ProductCategorizer::class)->categorize('Milchreis')?->code);
        });
    }

    public function test_eine_fehlerhafte_regel_legt_die_kategorisierung_nicht_lahm(): void
    {
        $family = $this->familie();

        app(FamilyContext::class)->runFor($family, function () {
            $gruppe = ProductGroup::where('code', 'SONSTIGES')->firstOrFail();

            ProductGroupRule::create([
                'product_group_id' => $gruppe->id,
                'pattern' => '([unvollstaendig',   // ungültiger Ausdruck
                'is_regex' => true,
                'priority' => 1,                   // läuft als Erstes
            ]);

            // Die kaputte Regel wird übersprungen, die gültige greift weiter.
            $this->assertSame('MILCH', app(ProductCategorizer::class)->categorize('Vollmilch')?->code);
        });
    }

    public function test_regeln_wirken_nur_in_der_eigenen_familie(): void
    {
        $a = $this->familie('a@example.test');
        $b = $this->familie('b@example.test');
        $ctx = app(FamilyContext::class);

        $ctx->runFor($a, function () {
            $gruppe = ProductGroup::where('code', 'HAUSHALT')->firstOrFail();
            ProductGroupRule::create([
                'product_group_id' => $gruppe->id,
                'pattern' => 'schraubenzieher',
                'is_regex' => false,
                'priority' => 10,
            ]);
        });

        $this->assertSame('HAUSHALT', $ctx->runFor($a, fn () => app(ProductCategorizer::class)->categorize('Schraubenzieher')?->code));
        $this->assertNull($ctx->runFor($b, fn () => app(ProductCategorizer::class)->categorize('Schraubenzieher')));
    }

    protected function familie(string $email = 'owner@example.test'): int
    {
        $user = User::factory()->create(['email' => $email]);

        return app(FamilyProvisioner::class)->createFor($user, 'Familie '.$email)->getKey();
    }
}
