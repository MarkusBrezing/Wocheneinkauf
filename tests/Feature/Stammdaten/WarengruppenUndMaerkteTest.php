<?php

namespace Tests\Feature\Stammdaten;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use App\Modules\Markets\Models\MarketSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-01 bis B-05: Warengruppen, Maerkte, Regalbereiche, Zuordnung, Standardmarkt.
 */
class WarengruppenUndMaerkteTest extends TestCase
{
    use RefreshDatabase;

    public function test_warengruppe_anlegen_und_umbenennen(): void
    {
        [$owner] = $this->familie();

        $this->actingAs($owner)->post('/stammdaten/warengruppen', [
            'code' => 'BLUMEN',
            'label' => 'Blumen',
            'sort' => 500,
        ])->assertRedirect();

        $gruppe = $this->imKontext($owner, fn () => ProductGroup::where('code', 'BLUMEN')->firstOrFail());

        $this->actingAs($owner)->put('/stammdaten/warengruppen/'.$gruppe->id, [
            'label' => 'Blumen und Pflanzen',
            'sort' => 510,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_groups', [
            'id' => $gruppe->id,
            'code' => 'BLUMEN',                     // Kürzel bleibt unveränderlich
            'label' => 'Blumen und Pflanzen',
            'sort' => 510,
        ]);
    }

    public function test_kuerzel_ist_je_familie_eindeutig_aber_zwischen_familien_frei(): void
    {
        [$ownerA] = $this->familie('a@example.test', 'Familie A');
        [$ownerB] = $this->familie('b@example.test', 'Familie B');

        $this->actingAs($ownerA)
            ->post('/stammdaten/warengruppen', ['code' => 'BLUMEN', 'label' => 'Blumen'])
            ->assertSessionHasNoErrors();

        $this->actingAs($ownerA)
            ->post('/stammdaten/warengruppen', ['code' => 'BLUMEN', 'label' => 'Nochmal'])
            ->assertSessionHasErrors('code');

        // Dieselbe Kennung in einer anderen Familie muss erlaubt sein.
        $this->actingAs($ownerB)
            ->post('/stammdaten/warengruppen', ['code' => 'BLUMEN', 'label' => 'Blumen'])
            ->assertSessionHasNoErrors();
    }

    public function test_kuerzel_muss_dem_erwarteten_muster_folgen(): void
    {
        [$owner] = $this->familie();

        $this->actingAs($owner)
            ->post('/stammdaten/warengruppen', ['code' => 'kleine buchstaben', 'label' => 'Test'])
            ->assertSessionHasErrors('code');
    }

    public function test_mitglied_ohne_verwaltungsrecht_darf_stammdaten_nicht_aendern(): void
    {
        [, $family] = $this->familie();
        $mitglied = $this->mitglied($family);

        $this->actingAs($mitglied)
            ->post('/stammdaten/warengruppen', ['code' => 'BLUMEN', 'label' => 'Blumen'])
            ->assertForbidden();

        // Lesen bleibt erlaubt -- die Gruppen bestimmen die Listensortierung.
        $this->actingAs($mitglied)->get('/stammdaten/warengruppen')->assertOk();
    }

    public function test_markt_anlegen_wird_automatisch_standard_wenn_er_der_erste_ist(): void
    {
        [$owner, $family] = $this->familie(mitStartdaten: false);

        $this->actingAs($owner)->post('/stammdaten/maerkte', ['name' => 'Storchenhof'])->assertRedirect();

        $this->assertDatabaseHas('markets', [
            'family_id' => $family->getKey(),
            'name' => 'Storchenhof',
            'is_default' => true,
        ]);
    }

    public function test_standardmarkt_wechseln_setzt_den_alten_zurueck(): void
    {
        [$owner, $family] = $this->familie();

        $this->actingAs($owner)->post('/stammdaten/maerkte', ['name' => 'Storchenhof']);
        $neuer = $this->imKontext($owner, fn () => Market::where('name', 'Storchenhof')->firstOrFail());

        $this->actingAs($owner)->post('/stammdaten/maerkte/'.$neuer->id.'/standard')->assertRedirect();

        $standards = $this->imKontext($owner, fn () => Market::where('is_default', true)->pluck('name'));

        $this->assertSame(['Storchenhof'], $standards->all());
    }

    public function test_standardmarkt_laesst_sich_nicht_deaktivieren(): void
    {
        [$owner] = $this->familie();
        $standard = $this->imKontext($owner, fn () => Market::where('is_default', true)->firstOrFail());

        $this->actingAs($owner)->put('/stammdaten/maerkte/'.$standard->id, [
            'name' => $standard->name,
            'sort' => 0,
            // is_active fehlt = deaktivieren
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($standard->fresh()->is_active);
    }

    public function test_regalbereich_anlegen_und_warengruppen_zuordnen(): void
    {
        [$owner] = $this->familie();
        $markt = $this->imKontext($owner, fn () => Market::firstOrFail());

        $this->actingAs($owner)
            ->post('/stammdaten/maerkte/'.$markt->id.'/bereiche', ['name' => 'Weinregal', 'sort' => 95])
            ->assertRedirect();

        [$bereich, $gruppen] = $this->imKontext($owner, fn () => [
            MarketSection::where('name', 'Weinregal')->firstOrFail(),
            ProductGroup::whereIn('code', ['GETRAENKE', 'SUESS'])->pluck('id'),
        ]);

        $this->actingAs($owner)
            ->put('/stammdaten/bereiche/'.$bereich->id.'/warengruppen', ['gruppen' => $gruppen->all()])
            ->assertRedirect();

        $this->assertSame(2, $bereich->productGroups()->count());

        // Erneutes Senden mit nur einer Gruppe muss die andere entfernen.
        $this->actingAs($owner)
            ->put('/stammdaten/bereiche/'.$bereich->id.'/warengruppen', ['gruppen' => [$gruppen->first()]])
            ->assertRedirect();

        $this->assertSame(1, $bereich->productGroups()->count());
    }

    public function test_regalbereichsname_ist_je_markt_eindeutig(): void
    {
        [$owner] = $this->familie();
        $markt = $this->imKontext($owner, fn () => Market::firstOrFail());

        $this->actingAs($owner)->post('/stammdaten/maerkte/'.$markt->id.'/bereiche', ['name' => 'Tiefkühl'])
            ->assertSessionHasErrors('name');   // legt der Startdatensatz bereits an
    }

    /**
     * @return array{0: User, 1: Family}
     */
    protected function familie(string $email = 'owner@example.test', string $name = 'Familie Test', bool $mitStartdaten = true): array
    {
        $owner = User::factory()->create(['email' => $email]);
        $family = app(FamilyProvisioner::class)->createFor($owner, $name);

        if (! $mitStartdaten) {
            app(FamilyContext::class)->runFor($family->getKey(), function () {
                Market::query()->delete();
                ProductGroup::query()->delete();
            });
        }

        return [$owner->refresh(), $family];
    }

    protected function mitglied(Family $family, string $email = 'mitglied@example.test'): User
    {
        $user = User::factory()->create(['email' => $email]);
        app(FamilyProvisioner::class)->join($user, $family, 'member');

        return $user->refresh();
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
