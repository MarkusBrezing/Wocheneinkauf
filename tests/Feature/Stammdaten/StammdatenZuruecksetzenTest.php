<?php

namespace Tests\Feature\Stammdaten;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Models\ProductGroupRule;
use App\Modules\Markets\Models\Market;
use App\Modules\Markets\Models\MarketSection;
use App\Modules\Pantry\Models\PantryItem;
use App\Modules\Pantry\Services\PantryManager;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stammdaten zurücksetzen: Artikel, Warengruppen und Märkte auf einmal löschen.
 */
class StammdatenZuruecksetzenTest extends TestCase
{
    use RefreshDatabase;

    public function test_die_abfrage_zeigt_was_verschwindet(): void
    {
        [$user] = $this->familie();
        $this->artikel($user, 'Mehl');

        $this->actingAs($user)->get('/stammdaten/zuruecksetzen')
            ->assertOk()
            ->assertSee('Stammdaten zurücksetzen')
            ->assertSee('Wird gelöscht')
            // Die Folge, die niemand erwartet, muss dastehen.
            ->assertSee('Der Vorrat geht mit.')
            ->assertSee('Familie Test');
    }

    public function test_ohne_abgetippten_familiennamen_passiert_nichts(): void
    {
        [$user] = $this->familie();
        $this->artikel($user, 'Mehl');

        $this->actingAs($user)->from('/stammdaten/zuruecksetzen')
            ->delete('/stammdaten/zuruecksetzen', ['bestaetigung' => 'ja bitte'])
            ->assertRedirect('/stammdaten/zuruecksetzen')
            ->assertSessionHasErrors('bestaetigung');

        $this->assertSame(1, $this->imKontext($user, fn () => Product::count()));
        $this->assertGreaterThan(0, $this->imKontext($user, fn () => ProductGroup::count()));
    }

    public function test_mit_abgetipptem_namen_ist_alles_weg(): void
    {
        [$user] = $this->familie();
        $this->artikel($user, 'Mehl');
        $this->artikel($user, 'Milch');

        $this->actingAs($user)
            ->delete('/stammdaten/zuruecksetzen', ['bestaetigung' => 'Familie Test'])
            ->assertRedirect(route('stammdaten.produkte.index'))
            ->assertSessionHasNoErrors();

        $this->imKontext($user, function () {
            $this->assertSame(0, Product::withTrashed()->count());
            $this->assertSame(0, ProductGroup::count());
            $this->assertSame(0, Market::count());
            // Was an den Stammdaten hing, ist über die Datenbank mitgegangen.
            $this->assertSame(0, ProductGroupRule::count());
            $this->assertSame(0, MarketSection::count());
        });
    }

    /**
     * Der Vorrat hängt an den Artikeln und verschwindet mit ihnen. Das ist die
     * Folge, vor der die Abfrage warnt -- hier wird sie festgeschrieben.
     */
    public function test_der_vorrat_verschwindet_mit_den_artikeln(): void
    {
        [$user] = $this->familie();
        $mehl = $this->artikel($user, 'Mehl');

        $this->imKontext($user, function () use ($mehl, $user) {
            $item = app(PantryManager::class)->ensureItem($mehl, 'Vorrat', ['target_qty' => 3]);
            app(PantryManager::class)->book($item, 2, 'purchase', $user);
        });

        $this->assertSame(1, $this->imKontext($user, fn () => PantryItem::count()));

        $this->actingAs($user)->delete('/stammdaten/zuruecksetzen', ['bestaetigung' => 'Familie Test']);

        $this->assertSame(0, $this->imKontext($user, fn () => PantryItem::count()));
    }

    /**
     * Einkaufslisten bleiben lesbar: Text und Menge stehen im Eintrag selbst,
     * nur die Verweise auf Artikel und Warengruppe fallen weg.
     */
    public function test_listeneintraege_bleiben_ohne_ihre_verweise(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->post('/einkaufsliste/eintraege', ['menge' => '2 l', 'text' => 'Milch']);

        $vorher = $this->imKontext($user, fn () => ShoppingListItem::firstOrFail());
        $this->assertNotNull($vorher->product_group_id);

        $this->actingAs($user)->delete('/stammdaten/zuruecksetzen', ['bestaetigung' => 'Familie Test']);

        $nachher = $this->imKontext($user, fn () => ShoppingListItem::firstOrFail());

        $this->assertSame('Milch', $nachher->raw_name);
        $this->assertSame('2.000', $nachher->amount);
        $this->assertNull($nachher->product_id);
        $this->assertNull($nachher->product_group_id);
    }

    public function test_der_startdatensatz_laesst_sich_gleich_neu_einspielen(): void
    {
        [$user] = $this->familie();
        $this->artikel($user, 'Mehl');

        $this->actingAs($user)->delete('/stammdaten/zuruecksetzen', [
            'bestaetigung' => 'Familie Test',
            'startdatensatz' => '1',
        ])->assertSessionHasNoErrors();

        $this->imKontext($user, function () {
            $this->assertSame(0, Product::withTrashed()->count());
            // Warengruppen, Regeln und der Markt stehen wieder da.
            $this->assertSame(15, ProductGroup::count());
            $this->assertGreaterThan(50, ProductGroupRule::count());
            $this->assertSame(1, Market::count());
            $this->assertSame(10, MarketSection::count());
        });
    }

    /**
     * Die schärfste Anforderung: Das Löschen endet an der Familiengrenze.
     */
    public function test_fremde_stammdaten_bleiben_unberuehrt(): void
    {
        [$eigen] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        $this->artikel($eigen, 'Mehl');
        $this->artikel($fremd, 'Kaviar');

        $this->actingAs($eigen)->delete('/stammdaten/zuruecksetzen', ['bestaetigung' => 'Familie Test'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->imKontext($eigen, fn () => Product::withTrashed()->count()));
        $this->assertSame(1, $this->imKontext($fremd, fn () => Product::count()));
        $this->assertSame(15, $this->imKontext($fremd, fn () => ProductGroup::count()));
        $this->assertSame(1, $this->imKontext($fremd, fn () => Market::count()));
    }

    /**
     * Stammdaten gelten für alle Mitglieder -- deshalb dürfen sie nur Owner
     * und Admin anfassen, und erst recht löschen.
     */
    public function test_ein_mitglied_darf_nicht_zuruecksetzen(): void
    {
        [$owner, $family] = $this->familie();
        $this->artikel($owner, 'Mehl');

        $kind = User::factory()->create(['email' => 'kind@example.test']);
        app(FamilyProvisioner::class)->join($kind, $family, 'child');
        $kind->refresh();

        $this->actingAs($kind)->get('/stammdaten/zuruecksetzen')->assertForbidden();
        $this->actingAs($kind)->delete('/stammdaten/zuruecksetzen', ['bestaetigung' => 'Familie Test'])
            ->assertForbidden();

        $this->assertSame(1, $this->imKontext($owner, fn () => Product::count()));
    }

    public function test_der_verweis_erscheint_nur_fuer_verwalter(): void
    {
        [$owner, $family] = $this->familie();

        $kind = User::factory()->create(['email' => 'kind@example.test']);
        app(FamilyProvisioner::class)->join($kind, $family, 'child');
        $kind->refresh();

        $this->actingAs($owner)->get('/stammdaten/produkte')
            ->assertOk()->assertSee(route('stammdaten.zuruecksetzen'));

        $this->actingAs($kind)->get('/stammdaten/produkte')
            ->assertOk()->assertDontSee(route('stammdaten.zuruecksetzen'));
    }

    // ── Helfer ──────────────────────────────────────────────────────────

    /**
     * @return array{0: User, 1: Family}
     */
    protected function familie(string $email = 'owner@example.test', string $name = 'Familie Test'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $family = app(FamilyProvisioner::class)->createFor($user, $name);

        return [$user->refresh(), $family];
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

    protected function artikel(User $user, string $name): Product
    {
        return $this->imKontext($user, fn () => Product::factory()->create(['name' => $name]));
    }
}
