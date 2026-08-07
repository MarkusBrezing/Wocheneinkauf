<?php

namespace App\Modules\Pantry\Services;

use App\Core\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pantry\Models\PantryItem;
use App\Modules\Pantry\Models\PantryMovement;
use Illuminate\Support\Facades\DB;

/**
 * Alles, was den Vorratsbestand veraendert (E-01, E-02).
 *
 * Der Bestand wird nie direkt gesetzt, sondern immer ueber eine Buchung
 * fortgeschrieben. Das ist der Unterschied zwischen einem Zahlenfeld und einem
 * Konto: Nur so laesst sich hinterher beantworten, warum aus drei Dosen zwei
 * wurden -- gegessen, weggeworfen oder nie da gewesen.
 */
class PantryManager
{
    /**
     * Vorratsposition anlegen oder die vorhandene finden (E-01).
     *
     * Der Artikel kommt aus dem Katalog: erst die gemeinsame Klammer macht aus
     * "Nudeln im Vorrat" und "Nudeln auf der Liste" dieselbe Sache.
     */
    public function ensureItem(Product $produkt, string $ort = 'Vorrat', array $daten = []): PantryItem
    {
        $item = PantryItem::where('product_id', $produkt->getKey())
            ->where('location', $ort)
            ->first();

        if ($item !== null) {
            return $daten === [] ? $item : tap($item)->update($daten);
        }

        $item = new PantryItem([
            'location' => $ort,
            'unit' => $daten['unit'] ?? $produkt->default_unit,
            'target_qty' => $daten['target_qty'] ?? ($produkt->pantry_target_amount ?? 1),
            'min_qty' => $daten['min_qty'] ?? 0,
            'best_before' => $daten['best_before'] ?? null,
            'is_active' => true,
            'sort' => $daten['sort'] ?? 0,
        ]);

        $item->product_id = $produkt->getKey();
        $item->family_id = $produkt->family_id;
        $item->save();

        return $item;
    }

    /**
     * Bestand um einen Betrag fortschreiben und die Buchung festhalten (E-02).
     *
     * @param  float  $delta  positiv = Zugang, negativ = Abgang
     */
    public function book(
        PantryItem $item,
        float $delta,
        string $grund = 'correction',
        ?User $user = null,
        ?string $refType = null,
        ?int $refId = null,
    ): PantryMovement {
        return DB::transaction(function () use ($item, $delta, $grund, $user, $refType, $refId) {
            // Der Bestand kann nicht unter null fallen: Eine negative Menge im
            // Schrank gibt es nicht, und die Datenbank wuerde die Pruefung
            // ohnehin abweisen.
            $neu = max(0.0, round((float) $item->current_qty + $delta, 3));
            $tatsaechlich = round($neu - (float) $item->current_qty, 3);

            $item->forceFill(['current_qty' => $neu])->save();

            $buchung = new PantryMovement([
                'qty_delta' => $tatsaechlich,
                'reason' => $grund,
                'ref_type' => $refType,
                'ref_id' => $refId,
            ]);

            $buchung->pantry_item_id = $item->getKey();
            $buchung->family_id = $item->family_id;
            $buchung->created_by = $user?->getKey();
            $buchung->save();

            return $buchung;
        });
    }

    /**
     * Bestand auf einen Wert setzen -- als Buchung ueber die Differenz.
     *
     * Wird beim Abschluss einer Inventur gebraucht: Dort ist die gezaehlte
     * Menge bekannt, nicht die Veraenderung.
     */
    public function setTo(PantryItem $item, float $menge, string $grund = 'inventory', ?User $user = null, ?string $refType = null, ?int $refId = null): ?PantryMovement
    {
        $delta = round($menge - (float) $item->current_qty, 3);

        if ($delta === 0.0) {
            // Nichts zu buchen -- aber die Zaehlung hat trotzdem
            // stattgefunden.
            $item->forceFill(['counted_at' => now()])->save();

            return null;
        }

        $buchung = $this->book($item, $delta, $grund, $user, $refType, $refId);

        $item->forceFill(['counted_at' => now()])->save();

        return $buchung;
    }
}
