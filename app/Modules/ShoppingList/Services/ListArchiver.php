<?php

namespace App\Modules\ShoppingList\Services;

use App\Core\Models\User;
use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

/**
 * Einkauf abschliessen: Liste ins Archiv, fehlende Artikel in die naechste
 * (C-11, C-13, C-14 und die Zusatzfunktion aus der Aufgabenstellung).
 *
 * Der Gedanke dahinter ist der Ablauf im Laden: abgehakt ist, was im Wagen
 * liegt. Was am Ende offen bleibt, war nicht da, wurde vergessen oder war zu
 * teuer -- und genau das braucht man beim naechsten Mal wieder. Es von Hand
 * abzuschreiben ist der Punkt, an dem eine Einkaufsliste unbenutzt bleibt.
 *
 * Kopiert wird, nicht verschoben: Das Archiv soll zeigen, was tatsaechlich auf
 * der Liste stand -- einschliesslich dessen, was nicht gekauft wurde.
 */
class ListArchiver
{
    public function __construct(protected ShoppingListManager $manager) {}

    /**
     * Liste archivieren und die Nachfolgerin anlegen (C-11).
     *
     * @param  bool  $offeneUebernehmen  fehlende Artikel in die neue Liste schreiben
     * @param  bool  $mitStandardvorlage  falsch, wenn der Aufrufer selbst
     *                                    entscheidet, welche Vorlagen auf die
     *                                    neue Liste kommen (Assistent "Neue
     *                                    Woche") -- sonst stuende die
     *                                    Standardvorlage doppelt darauf
     * @return ShoppingList die neue aktive Liste
     */
    public function archive(
        ShoppingList $liste,
        bool $offeneUebernehmen = true,
        ?User $user = null,
        bool $mitStandardvorlage = true,
    ): ShoppingList {
        return DB::transaction(function () use ($liste, $offeneUebernehmen, $user, $mitStandardvorlage) {
            $offene = $offeneUebernehmen
                ? $liste->items()->offen()->orderBy('sort')->orderBy('id')->get()
                : collect();

            $liste->forceFill([
                'status' => 'archived',
                'archived_at' => now(),
            ])->save();

            // Erst danach: der UNIQUE auf (family_id, aktiv_marker) laesst
            // keine zweite aktive Liste zu, solange diese noch aktiv ist.
            //
            // Ohne Standardvorlage angelegt und diese erst nach den fehlenden
            // Artikeln angewendet: So trifft eine Vorlagenposition auf den
            // bereits uebernommenen Artikel und wird mit ihm zusammengefuehrt
            // (C-17), statt als zweite Zeile danebenzustehen.
            $neue = $this->manager->current($user, mitVorlage: false);

            foreach ($offene as $item) {
                $this->uebernehmen($item, $neue);
            }

            if ($mitStandardvorlage) {
                app(TemplateApplier::class)->applyDefault($neue);
            }

            return $neue->refresh();
        });
    }

    /**
     * Archivierte Liste als aktuelle Liste laden (C-13).
     *
     * Die vorhandene Liste wird nicht ersetzt, sondern ergaenzt -- ueber
     * dieselbe Zusammenfuehrung wie beim Tippen (C-17). Wer eine leere Liste
     * will, archiviert vorher.
     *
     * @return int Anzahl uebernommener Artikel
     */
    public function loadIntoCurrent(ShoppingList $archiv, ?User $user = null): int
    {
        return DB::transaction(function () use ($archiv, $user) {
            $ziel = $this->manager->current($user);
            $uebernommen = 0;

            foreach ($archiv->items()->orderBy('sort')->orderBy('id')->get() as $item) {
                $this->manager->addOrMerge(
                    liste: $ziel,
                    eingabe: $this->alsEingabe($item),
                    source: $item->source,
                    sourceRefId: $item->source_ref_id,
                    productId: $item->product_id,
                    productGroupId: $item->product_group_id,
                );

                $uebernommen++;
            }

            return $uebernommen;
        });
    }

    /**
     * Einen offenen Eintrag in die neue Liste uebernehmen.
     *
     * Bewusst als Kopie des Datensatzes statt ueber addOrMerge: die neue Liste
     * ist leer, es gibt nichts zusammenzufuehren, und Menge, Notiz und
     * Warengruppe sollen unveraendert stehen bleiben -- auch dann, wenn die
     * Stichwortregeln inzwischen anders entscheiden wuerden.
     */
    protected function uebernehmen(ShoppingListItem $item, ShoppingList $ziel): ShoppingListItem
    {
        $kopie = $item->replicate(['client_uuid', 'is_checked', 'checked_by', 'checked_at', 'deleted_at']);

        $kopie->list_id = $ziel->getKey();
        $kopie->family_id = $ziel->family_id;
        $kopie->client_uuid = null;   // vergibt der creating-Hook
        $kopie->is_checked = false;
        $kopie->checked_by = null;
        $kopie->checked_at = null;
        $kopie->save();

        return $kopie;
    }

    protected function alsEingabe(ShoppingListItem $item): ParsedItem
    {
        return new ParsedItem(
            name: $item->raw_name,
            amount: $item->amount === null ? null : (float) $item->amount,
            amountRaw: $item->amount_raw,
            unit: $item->unit,
            note: $item->note,
        );
    }
}
