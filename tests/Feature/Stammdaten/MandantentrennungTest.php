<?php

namespace Tests\Feature\Stammdaten;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use App\Modules\Markets\Models\MarketSection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Die Mandantentrennung der Stammdaten -- auf beiden Ebenen.
 *
 * Ebene 1: der Global Scope im Code (Konzept 2.1).
 * Ebene 2: die Verbund-Fremdschluessel in der Datenbank (Review-Befund R-03),
 *          die auch dann greifen, wenn jemand Eloquent umgeht.
 */
class MandantentrennungTest extends TestCase
{
    use RefreshDatabase;

    public function test_stammdaten_fremder_familien_sind_unsichtbar(): void
    {
        [$userA, $familyA] = $this->familie('a@example.test', 'Familie A');
        [, $familyB] = $this->familie('b@example.test', 'Familie B');

        $ctx = app(FamilyContext::class);
        $ctx->runFor($familyA->getKey(), fn () => Product::factory()->create(['name' => 'Artikel A']));
        $ctx->runFor($familyB->getKey(), fn () => Product::factory()->create(['name' => 'Artikel B']));

        $antwort = $this->actingAs($userA)->get('/stammdaten/produkte')->assertOk();

        $antwort->assertSee('Artikel A');
        $antwort->assertDontSee('Artikel B');
    }

    public function test_zugriff_auf_fremde_datensaetze_endet_in_404(): void
    {
        [$userA] = $this->familie('a@example.test', 'Familie A');
        [, $familyB] = $this->familie('b@example.test', 'Familie B');

        $ctx = app(FamilyContext::class);
        $fremdesProdukt = $ctx->runFor($familyB->getKey(), fn () => Product::factory()->create());
        $fremdeGruppe = $ctx->runFor($familyB->getKey(), fn () => ProductGroup::factory()->create());
        $fremderMarkt = $ctx->runFor($familyB->getKey(), fn () => Market::factory()->create());

        // Route-Model-Binding laeuft durch den Global Scope, deshalb reicht
        // 404 -- ohne dass in den Controllern eine Pruefung stehen muesste.
        $this->actingAs($userA)
            ->put('/stammdaten/produkte/'.$fremdesProdukt->id, ['name' => 'Übernommen'])
            ->assertNotFound();

        $this->actingAs($userA)
            ->put('/stammdaten/warengruppen/'.$fremdeGruppe->id, ['label' => 'Übernommen', 'sort' => 0])
            ->assertNotFound();

        $this->actingAs($userA)
            ->delete('/stammdaten/maerkte/'.$fremderMarkt->id)
            ->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $fremdesProdukt->id, 'name' => $fremdesProdukt->name]);
        $this->assertDatabaseHas('markets', ['id' => $fremderMarkt->id]);
    }

    public function test_datenbank_lehnt_familienuebergreifende_verknuepfung_ab(): void
    {
        // Der Kern von R-03. Hier wird Eloquent bewusst umgangen -- genau der
        // Fall, den ein Global Scope nicht abfangen kann.
        [, $familyA] = $this->familie('a@example.test', 'Familie A');
        [, $familyB] = $this->familie('b@example.test', 'Familie B');

        $ctx = app(FamilyContext::class);
        $gruppeA = $ctx->runFor($familyA->getKey(), fn () => ProductGroup::factory()->create());
        $bereichB = $ctx->runFor($familyB->getKey(), function () {
            $markt = Market::factory()->create();

            return MarketSection::factory()->create(['market_id' => $markt->getKey()]);
        });

        $this->expectException(QueryException::class);

        DB::table('market_section_groups')->insert([
            'family_id' => $familyB->getKey(),
            'market_section_id' => $bereichB->getKey(),
            'product_group_id' => $gruppeA->getKey(),
        ]);
    }

    public function test_zuordnung_ignoriert_untergeschobene_fremde_warengruppen(): void
    {
        [$userA, $familyA] = $this->familie('a@example.test', 'Familie A');
        [, $familyB] = $this->familie('b@example.test', 'Familie B');

        $ctx = app(FamilyContext::class);
        $fremdeGruppe = $ctx->runFor($familyB->getKey(), fn () => ProductGroup::factory()->create());
        [$eigeneGruppe, $bereich] = $ctx->runFor($familyA->getKey(), function () {
            $markt = Market::factory()->create();

            return [
                ProductGroup::factory()->create(),
                MarketSection::factory()->create(['market_id' => $markt->getKey()]),
            ];
        });

        // Manipuliertes Formular: eine fremde ID mitschicken.
        $this->actingAs($userA)->put('/stammdaten/bereiche/'.$bereich->id.'/warengruppen', [
            'gruppen' => [$eigeneGruppe->id, $fremdeGruppe->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('market_section_groups', [
            'market_section_id' => $bereich->id,
            'product_group_id' => $eigeneGruppe->id,
        ]);
        $this->assertDatabaseMissing('market_section_groups', [
            'product_group_id' => $fremdeGruppe->id,
        ]);
    }

    public function test_jede_stammdatentabelle_traegt_den_global_scope(): void
    {
        [, $familyA] = $this->familie('a@example.test', 'Familie A');
        $ctx = app(FamilyContext::class);

        $ctx->runFor($familyA->getKey(), function () {
            $markt = Market::factory()->create();
            MarketSection::factory()->create(['market_id' => $markt->getKey()]);
            ProductGroup::factory()->create();
            Product::factory()->create();
        });

        // Ohne Kontext darf keine der Tabellen etwas herausgeben.
        $this->assertSame(0, Market::count(), 'markets');
        $this->assertSame(0, MarketSection::count(), 'market_sections');
        $this->assertSame(0, ProductGroup::count(), 'product_groups');
        $this->assertSame(0, Product::count(), 'products');
    }

    public function test_familie_loeschen_raeumt_ihre_stammdaten_mit_ab(): void
    {
        [, $familyA] = $this->familie('a@example.test', 'Familie A');
        [, $familyB] = $this->familie('b@example.test', 'Familie B');

        $familyA->forceDelete();

        // Die Stammdaten von B bleiben unberuehrt.
        $this->assertDatabaseMissing('product_groups', ['family_id' => $familyA->getKey()]);
        $this->assertDatabaseMissing('markets', ['family_id' => $familyA->getKey()]);
        $this->assertDatabaseHas('product_groups', ['family_id' => $familyB->getKey()]);
        $this->assertDatabaseHas('markets', ['family_id' => $familyB->getKey()]);
    }

    /**
     * @return array{0: User, 1: Family}
     */
    protected function familie(string $email, string $name): array
    {
        $user = User::factory()->create(['email' => $email]);
        $family = app(FamilyProvisioner::class)->createFor($user, $name);

        return [$user->refresh(), $family];
    }
}
