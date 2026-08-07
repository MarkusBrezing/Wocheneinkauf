<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

/**
 * Fuehrt zwei Artikel zusammen (B-06).
 *
 * Dubletten entstehen unvermeidlich: "Tomaten" und "Tomate" tippt niemand
 * absichtlich zweimal. Beim Zusammenfuehren werden alle Verweise umgehaengt
 * und der aufgeloeste Artikel weich geloescht.
 *
 * Weitere Tabellen, die auf products zeigen (Rezeptzutaten,
 * Vorratspositionen), gehoeren in verweiseUmhaengen() -- die Methode ist
 * bewusst die einzige Stelle dafuer.
 */
class ProductMerger
{
    public function merge(Product $behalten, Product $aufloesen): Product
    {
        if ($behalten->is($aufloesen)) {
            return $behalten;
        }

        return DB::transaction(function () use ($behalten, $aufloesen) {
            $this->verweiseUmhaengen($behalten, $aufloesen);

            // Fehlende Angaben vom aufgeloesten Artikel uebernehmen, statt sie
            // zu verlieren.
            $behalten->fill([
                'product_group_id' => $behalten->product_group_id ?? $aufloesen->product_group_id,
                'default_unit' => $behalten->default_unit ?? $aufloesen->default_unit,
                'is_pantry_staple' => $behalten->is_pantry_staple || $aufloesen->is_pantry_staple,
                'pantry_target_amount' => $behalten->pantry_target_amount ?? $aufloesen->pantry_target_amount,
            ])->save();

            $this->verfuegbarkeitUebernehmen($behalten, $aufloesen);

            // Das Umbenennen auf "~{id}" erledigt der deleting-Hook des
            // Models -- die R-04-Konvention gilt fuer jeden Soft Delete, nicht
            // nur fuer den beim Zusammenfuehren.
            $aufloesen->delete();

            return $behalten->refresh();
        });
    }

    /**
     * Die Marktzuordnung ist gepflegte Information, keine Nebensache.
     *
     * Steht sie nur beim aufgeloesten Artikel ("Miso Paste: nur Asialaden")
     * und beim behaltenen gar nichts, hat jemand sie genau einmal eingetragen
     * -- dann gilt sie weiter. Haben beide eine, gilt die Vereinigung.
     */
    protected function verfuegbarkeitUebernehmen(Product $behalten, Product $aufloesen): void
    {
        $vonAufloesen = $aufloesen->markets()->pluck('markets.id');

        if ($vonAufloesen->isEmpty()) {
            return;
        }

        $behalten->markets()->syncWithoutDetaching(
            $vonAufloesen->mapWithKeys(fn ($id) => [$id => ['family_id' => $behalten->family_id]])->all()
        );

        $aufloesen->markets()->detach();
    }

    protected function verweiseUmhaengen(Product $behalten, Product $aufloesen): void
    {
        // Auch die Eintraege archivierter Listen und geloeschte Eintraege: das
        // Archiv soll nach dem Zusammenfuehren nicht auf einen Artikel zeigen,
        // den es nicht mehr gibt.
        ShoppingListItem::withTrashed()
            ->where('product_id', $aufloesen->getKey())
            ->update(['product_id' => $behalten->getKey()]);
    }
}
