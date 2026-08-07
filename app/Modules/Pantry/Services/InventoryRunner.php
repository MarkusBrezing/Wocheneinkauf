<?php

namespace App\Modules\Pantry\Services;

use App\Core\Models\User;
use App\Modules\Pantry\Models\Inventory;
use App\Modules\Pantry\Models\InventoryLine;
use App\Modules\Pantry\Models\PantryItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Der Inventurlauf (E-03 … E-08).
 *
 * Der Ablauf hat drei Zustaende und nur zwei Ausgaenge: Man startet einen Lauf,
 * zaehlt -- gern zu mehreren und ueber Stunden verteilt -- und schliesst ihn
 * ab oder bricht ihn ab. Ein abgebrochener Lauf laesst die Bestaende in Ruhe;
 * das ist die Zusage, die es braucht, damit jemand ueberhaupt anfaengt.
 */
class InventoryRunner
{
    public function __construct(protected PantryManager $pantry) {}

    /**
     * Inventur starten (E-03).
     *
     * Die Zeilen entstehen sofort und frieren dabei Ist und Soll ein. Wuerde
     * man sie erst beim Zaehlen anlegen, waere die Abweichung nachher nicht
     * mehr berechenbar -- der Ist-Stand haette sich ja inzwischen geaendert.
     *
     * @param  array<int, int>|null  $auswahl  Vorratspositionen bei scope='selection'
     */
    public function start(string $umfang = 'full', ?string $ort = null, ?array $auswahl = null, ?string $titel = null, ?User $user = null): Inventory
    {
        return DB::transaction(function () use ($umfang, $ort, $auswahl, $titel, $user) {
            $inventur = new Inventory([
                'title' => $titel,
                'scope' => $umfang,
                'scope_value' => $umfang === 'location' ? $ort : null,
            ]);

            $inventur->started_by = $user?->getKey();
            $inventur->started_at = now();
            $inventur->save();

            foreach ($this->positionen($umfang, $ort, $auswahl) as $item) {
                $zeile = new InventoryLine;

                $zeile->inventory_id = $inventur->getKey();
                $zeile->pantry_item_id = $item->getKey();
                $zeile->family_id = $item->family_id;
                $zeile->expected_qty = $item->current_qty;
                $zeile->target_qty = $item->target_qty;
                $zeile->save();
            }

            return $inventur->refresh();
        });
    }

    /**
     * Zaehlung einer Zeile erfassen (E-04).
     *
     * Eine leere Angabe setzt die Zeile zurueck auf "noch nicht gezaehlt" --
     * wer sich vertippt hat, soll das ruecknehmen koennen, ohne raten zu
     * muessen, ob dann eine Null gilt.
     */
    public function count(InventoryLine $zeile, ?float $menge, ?User $user = null, ?string $notiz = null): InventoryLine
    {
        $zeile->forceFill([
            'counted_qty' => $menge,
            'counted_by' => $menge === null ? null : $user?->getKey(),
            'counted_at' => $menge === null ? null : now(),
            'note' => $notiz,
        ])->save();

        return $zeile;
    }

    /**
     * Inventur abschliessen (E-06).
     *
     * Gezaehlte Zeilen werden zum neuen Bestand -- jede ueber eine Buchung mit
     * Grund 'inventory' und Verweis auf den Lauf. Ungezaehlte Zeilen bleiben
     * unangetastet: Was niemand angesehen hat, darf die Inventur nicht auf
     * null setzen.
     *
     * @return int Anzahl der Positionen, deren Bestand sich geaendert hat
     */
    public function complete(Inventory $inventur, ?User $user = null): int
    {
        return DB::transaction(function () use ($inventur, $user) {
            $gebucht = 0;

            foreach ($inventur->lines()->whereNotNull('counted_qty')->with('pantryItem')->get() as $zeile) {
                $buchung = $this->pantry->setTo(
                    item: $zeile->pantryItem,
                    menge: (float) $zeile->counted_qty,
                    grund: 'inventory',
                    user: $user,
                    refType: 'inventory',
                    refId: $inventur->getKey(),
                );

                $gebucht += $buchung === null ? 0 : 1;
            }

            $inventur->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            return $gebucht;
        });
    }

    /**
     * Inventur abbrechen (E-07) -- die Bestaende bleiben, wie sie waren.
     */
    public function cancel(Inventory $inventur): Inventory
    {
        $inventur->forceFill([
            'status' => 'cancelled',
            'completed_at' => now(),
        ])->save();

        return $inventur;
    }

    /**
     * Die Positionen, die in den Lauf gehoeren.
     *
     * @param  array<int, int>|null  $auswahl
     * @return Collection<int, PantryItem>
     */
    protected function positionen(string $umfang, ?string $ort, ?array $auswahl)
    {
        return PantryItem::active()
            ->when($umfang === 'location' && $ort !== null, fn ($q) => $q->where('location', $ort))
            // IDs aus einem Formular werden ueber den Global Scope nachgeladen
            // -- eine fremde trifft ins Leere (Ebene 1½).
            ->when($umfang === 'selection', fn ($q) => $q->whereIn('id', $auswahl ?? []))
            ->orderBy('location')
            ->orderBy('sort')
            ->get();
    }
}
