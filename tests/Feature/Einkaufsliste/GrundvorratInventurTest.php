<?php

namespace Tests\Feature\Einkaufsliste;

use App\Core\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Collection;

/**
 * Bestandsaufnahme des Grundvorrats: zählen, was da ist -- aufschreiben, was
 * fehlt.
 */
class GrundvorratInventurTest extends EinkaufslisteTestCase
{
    public function test_die_fehlmenge_kommt_auf_die_liste(): void
    {
        [$user] = $this->familie();
        $fix = $this->grundvorrat($user, [['raw_name' => 'Vollmilch', 'amount' => 6, 'unit' => 'l']])->first();

        $this->actingAs($user)->post('/einkaufsliste/inventur', [
            'bestand' => [$fix->id => 2],
        ])->assertRedirect(route('einkaufsliste.index'))->assertSessionHasNoErrors();

        $item = $this->eintraege($user)->firstWhere('raw_name', 'Vollmilch');

        $this->assertSame('4.000', $item->amount, 'Soll 6 minus Bestand 2.');
        $this->assertSame('l', $item->unit);
        $this->assertSame('standing', $item->source);
        $this->assertSame($fix->id, $item->source_ref_id, 'Die Herkunft bleibt nachvollziehbar.');
    }

    public function test_ausreichender_bestand_kommt_nicht_auf_die_liste(): void
    {
        [$user] = $this->familie();
        $fix = $this->grundvorrat($user, [['raw_name' => 'Vollmilch', 'amount' => 2, 'unit' => 'l']])->first();

        $this->actingAs($user)->post('/einkaufsliste/inventur', [
            'bestand' => [$fix->id => 3],
        ])->assertRedirect()->assertSessionHas('status', fn (string $s) => str_contains($s, 'fehlt nichts'));

        $this->assertCount(0, $this->eintraege($user));
    }

    public function test_ein_leeres_feld_heisst_nicht_gezaehlt(): void
    {
        [$user] = $this->familie();
        $artikel = $this->grundvorrat($user, [
            ['raw_name' => 'Vollmilch', 'amount' => 2, 'unit' => 'l'],
            ['raw_name' => 'Butter', 'amount' => 1, 'unit' => 'Pck'],
        ]);

        // Nur der erste Artikel wurde gezählt, der zweite nicht angefasst.
        $this->actingAs($user)->post('/einkaufsliste/inventur', [
            'bestand' => [$artikel[0]->id => 0, $artikel[1]->id => ''],
        ])->assertRedirect();

        $namen = $this->eintraege($user)->pluck('raw_name')->all();

        // Die 0 heißt "nichts mehr da" und ergibt die volle Sollmenge, das
        // leere Feld heißt "nicht gezählt" -- der Unterschied ist der Grund,
        // warum man die Zählung unterbrechen kann.
        $this->assertSame(['Vollmilch'], $namen);
        $this->assertSame('2.000', $this->eintraege($user)->first()->amount);
    }

    public function test_ohne_sollmenge_entscheidet_das_kaestchen(): void
    {
        [$user] = $this->familie();
        $artikel = $this->grundvorrat($user, [
            ['raw_name' => 'Klopapier', 'amount' => null, 'unit' => null],
            ['raw_name' => 'Zahnpasta', 'amount' => null, 'unit' => null],
        ]);

        $this->actingAs($user)->post('/einkaufsliste/inventur', [
            'fehlt' => [$artikel[0]->id => 1],
        ])->assertRedirect();

        $eintraege = $this->eintraege($user);

        $this->assertSame(['Klopapier'], $eintraege->pluck('raw_name')->all());
        $this->assertNull($eintraege->first()->amount, 'Ohne Sollmenge gibt es keine Fehlmenge.');
    }

    public function test_das_kaestchen_schlaegt_den_bestand(): void
    {
        [$user] = $this->familie();
        $fix = $this->grundvorrat($user, [['raw_name' => 'Vollmilch', 'amount' => 2, 'unit' => 'l']])->first();

        // Angehakt heißt: ganz aus, unabhängig davon, was im Feld steht.
        $this->actingAs($user)->post('/einkaufsliste/inventur', [
            'bestand' => [$fix->id => 1],
            'fehlt' => [$fix->id => 1],
        ])->assertRedirect();

        $this->assertSame('2.000', $this->eintraege($user)->first()->amount);
    }

    public function test_die_fehlmenge_wird_mit_der_liste_zusammengefuehrt(): void
    {
        [$user] = $this->familie();
        $fix = $this->grundvorrat($user, [['raw_name' => 'Vollmilch', 'amount' => 6, 'unit' => 'l']])->first();

        $this->aufDieListe($user, '1 l Vollmilch');

        $this->actingAs($user)->post('/einkaufsliste/inventur', ['bestand' => [$fix->id => 2]])->assertRedirect();

        $eintraege = $this->eintraege($user);

        $this->assertCount(1, $eintraege, 'C-17 gilt auch hier.');
        $this->assertSame('5.000', $eintraege->first()->amount, '1 l von Hand plus 4 l Fehlmenge.');
    }

    public function test_deaktivierte_und_fremde_grundvorrat_werden_ignoriert(): void
    {
        [$user] = $this->familie();
        [$fremd] = $this->familie('fremd@example.test', 'Familie Fremd');

        // Kein Grundvorrat: das Kennzeichen fehlt.
        $inaktiv = $this->grundvorrat($user, [
            ['raw_name' => 'Saisonware', 'amount' => 3, 'unit' => 'Stk', 'is_active' => false],
        ])->first();

        $fremderArtikel = $this->grundvorrat($fremd, [['raw_name' => 'Fremdartikel', 'amount' => 5]])->first();

        $this->actingAs($user)->post('/einkaufsliste/inventur', [
            'bestand' => [$inaktiv->id => 0, $fremderArtikel->id => 0],
        ])->assertRedirect();

        // Die IDs werden über den Global Scope und scopeGrundvorrat nachgeladen
        // -- ein fremder oder nicht gekennzeichneter Artikel trifft ins Leere.
        $this->assertCount(0, $this->eintraege($user));
    }

    public function test_die_maske_zeigt_die_aktiven_grundvorrat_mit_sollmenge(): void
    {
        [$user] = $this->familie();
        $this->grundvorrat($user, [
            ['raw_name' => 'Vollmilch', 'amount' => 2, 'unit' => 'l'],
            ['raw_name' => 'Kein Vorrat', 'amount' => 1, 'is_active' => false],
        ]);

        $this->actingAs($user)->get('/einkaufsliste/inventur')
            ->assertOk()
            ->assertSee('Vollmilch')
            ->assertSee('2 l')
            ->assertDontSee('Kein Vorrat');
    }

    public function test_eine_leere_zaehlung_aendert_nichts(): void
    {
        [$user] = $this->familie();
        $this->grundvorrat($user, [['raw_name' => 'Vollmilch', 'amount' => 2, 'unit' => 'l']]);

        $this->actingAs($user)->post('/einkaufsliste/inventur', ['bestand' => []])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'nichts eingetragen'));
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
