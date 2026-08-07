<?php

namespace App\Modules\ShoppingList\Services;

use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Carbon\CarbonInterface;

/**
 * Der Stand einer Liste als Datensatz fuer den Sync (C-07, C-08).
 *
 * Ohne Zeitmarke ist es der vollstaendige Stand, mit Zeitmarke nur das, was
 * sich seither geaendert hat -- Loeschungen eingeschlossen. Deshalb sind
 * Loeschungen weich: eine ersatzlos verschwundene Zeile taucht in keinem Delta
 * auf, und das Geraet, das gerade offline war, wuerde sie nie wieder los.
 */
class ListSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public function fuer(ShoppingList $liste, ?CarbonInterface $seit = null): array
    {
        $eintraege = $liste->items()->with(['checkedBy', 'product.markets'])
            ->when($seit !== null, fn ($q) => $q->withTrashed()->where('shopping_list_items.updated_at', '>', $seit))
            ->orderBy('sort')->orderBy('id')->get();

        return [
            'liste' => [
                'id' => $liste->public_id,
                'name' => $liste->anzeigename(),
                'status' => $liste->status,
                'version' => $liste->version,
                'markt_id' => $liste->market_id,
                'aktualisiert_am' => $liste->updated_at?->toIso8601String(),
            ],
            'eintraege' => $eintraege->map(fn (ShoppingListItem $item) => $this->eintrag($item))->all(),
            'delta_seit' => $seit?->toIso8601String(),
            // Die Marke fuer die naechste Abfrage kommt vom Server, nicht vom
            // Client: eine abweichende Uhr im Handy wuerde sonst Aenderungen
            // ueberspringen.
            'server_zeit' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function eintrag(ShoppingListItem $item): array
    {
        return [
            'uuid' => $item->client_uuid,
            'name' => $item->raw_name,
            'menge' => $item->amount === null ? null : (float) $item->amount,
            'menge_roh' => $item->amount_raw,
            'einheit' => $item->unit,
            'notiz' => $item->note,
            'artikel_id' => $item->product_id,
            'warengruppe_id' => $item->product_group_id,
            // Leer heisst ueberall erhaeltlich -- eingetragen wird nur die
            // Ausnahme (siehe Product::markets()).
            'nur_bei_maerkten' => $item->product?->markets->modelKeys() ?? [],
            'abgehakt' => $item->is_checked,
            // C-09: wer den Artikel abgehakt hat.
            'abgehakt_von' => $item->checkedBy?->name,
            'abgehakt_am' => $item->checked_at?->toIso8601String(),
            'quelle' => $item->source,
            'sort' => $item->sort,
            'geloescht' => $item->trashed(),
            'aktualisiert_am' => $item->updated_at?->toIso8601String(),
        ];
    }
}
