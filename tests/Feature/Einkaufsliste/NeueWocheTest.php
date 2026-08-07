<?php

namespace Tests\Feature\Einkaufsliste;

use App\Core\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Models\ShoppingList;
use Illuminate\Support\Collection;

/**
 * Der Assistent "Neue Woche" (C-10).
 */
class NeueWocheTest extends EinkaufslisteTestCase
{
    public function test_assistent_archiviert_und_setzt_die_grundvorrat_auf_die_neue_liste(): void
    {
        [$user] = $this->familie();

        $grundvorratArtikel = $this->grundvorrat($user, [
            ['raw_name' => 'Vollmilch', 'amount' => 2, 'unit' => 'l'],
            ['raw_name' => 'Butter', 'amount' => null, 'unit' => null],
        ]);

        $this->aufDieListe($user, 'Brot');
        $this->aufDieListe($user, 'Bananen');
        $bananen = $this->eintrag($user, 'Bananen');
        $this->actingAs($user)->post('/einkaufsliste/eintraege/'.$bananen->client_uuid.'/haken');

        $this->actingAs($user)->post('/einkaufsliste/neue-woche', [
            'name' => 'Woche 32',
            'grundvorrat' => $grundvorratArtikel->pluck('id')->all(),
            'archivieren' => 1,
            'offene_uebernehmen' => 1,
        ])->assertRedirect(route('einkaufsliste.index'))->assertSessionHasNoErrors();

        $neue = $this->aktiveListe($user);

        $this->assertSame('Woche 32', $neue->name);
        $this->assertSame(1, $this->imKontext($user, fn () => ShoppingList::archived()->count()));

        $namen = $this->eintraege($user, $neue)->pluck('raw_name')->sort()->values()->all();

        // Brot fehlte und kommt mit, Bananen lagen im Wagen und bleiben im
        // Archiv, dazu die beiden Grundvorrat.
        $this->assertSame(['Brot', 'Butter', 'Vollmilch'], $namen);

        $milch = $this->eintraege($user, $neue)->firstWhere('raw_name', 'Vollmilch');
        $this->assertSame('2.000', $milch->amount);
        $this->assertSame('l', $milch->unit);
        $this->assertSame('standing', $milch->source, 'Die Herkunft bleibt nachvollziehbar.');
    }

    public function test_nur_ausgewaehlte_grundvorrat_kommen_auf_die_liste(): void
    {
        [$user] = $this->familie();

        $grundvorratArtikel = $this->grundvorrat($user, [
            ['raw_name' => 'Vollmilch'],
            ['raw_name' => 'Butter'],
        ]);

        $this->actingAs($user)->post('/einkaufsliste/neue-woche', [
            'grundvorrat' => [$grundvorratArtikel->first()->id],
        ])->assertRedirect();

        $this->assertSame(['Vollmilch'], $this->eintraege($user)->pluck('raw_name')->all());
    }

    public function test_ohne_archivieren_bleibt_die_laufende_liste_bestehen(): void
    {
        [$user] = $this->familie();

        $this->grundvorrat($user, [['raw_name' => 'Butter']]);
        $this->aufDieListe($user, 'Brot');
        $alte = $this->aktiveListe($user);

        $this->actingAs($user)->post('/einkaufsliste/neue-woche', [
            'grundvorrat' => $this->imKontext($user, fn () => Product::grundvorrat()->pluck('id')->all()),
        ])->assertRedirect();

        $this->assertSame($alte->id, $this->aktiveListe($user)->id);
        $this->assertSame(['Brot', 'Butter'], $this->eintraege($user)->pluck('raw_name')->sort()->values()->all());
    }

    public function test_ein_grundvorrat_verdoppelt_nichts_was_schon_auf_der_liste_steht(): void
    {
        [$user] = $this->familie();

        $this->grundvorrat($user, [['raw_name' => 'Vollmilch', 'amount' => 2, 'unit' => 'l']]);
        $this->aufDieListe($user, '1 l Vollmilch');

        $this->actingAs($user)->post('/einkaufsliste/neue-woche', [
            'grundvorrat' => $this->imKontext($user, fn () => Product::grundvorrat()->pluck('id')->all()),
        ])->assertRedirect();

        $eintraege = $this->eintraege($user);

        $this->assertCount(1, $eintraege);
        $this->assertSame('3.000', $eintraege->first()->amount);
    }

    public function test_der_assistent_zeigt_vorher_was_er_tun_wird(): void
    {
        [$user] = $this->familie();

        $this->grundvorrat($user, [['raw_name' => 'Vollmilch', 'amount' => 2, 'unit' => 'l']]);
        $this->aufDieListe($user, 'Brot');

        $this->actingAs($user)->get('/einkaufsliste/neue-woche')
            ->assertOk()
            ->assertSee('Vollmilch')
            ->assertSee('fehlenden Artikel');
    }

    /**
     * Grundvorrat anlegen: Artikel mit Kennzeichen und Sollmenge.
     *
     * @param  array<int, array<string, mixed>>  $definitionen
     * @return Collection<int, Product>
     */
    protected function grundvorrat(User $user, array $definitionen): Collection
    {
        return $this->imKontext($user, fn () => collect($definitionen)->map(
            function (array $daten) {
                $produkt = Product::factory()->create(['name' => $daten['raw_name']]);

                $produkt->update([
                    'is_pantry_staple' => $daten['is_active'] ?? true,
                    'pantry_target_amount' => $daten['amount'] ?? null,
                    'default_unit' => $daten['unit'] ?? null,
                ]);

                return $produkt->refresh();
            }
        ));
    }
}
