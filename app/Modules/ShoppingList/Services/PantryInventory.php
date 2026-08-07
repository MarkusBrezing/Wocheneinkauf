<?php

namespace App\Modules\ShoppingList\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

/**
 * Bestandsaufnahme des Grundvorrats: zaehlen, was da ist -- aufschreiben, was
 * fehlt.
 *
 * Das ist die kleine Schwester der Inventur aus Modul E (E-03 … E-09), auf den
 * Grundvorrat beschraenkt und ohne Bewegungskonto. Sie braucht keine eigenen
 * Tabellen: gezaehlt wird einmal, das Ergebnis ist die Fehlmenge auf der
 * Einkaufsliste. Wer die Historie der Bestaende will, wartet auf `inventories`.
 */
class PantryInventory
{
    public function __construct(protected ShoppingListManager $manager) {}

    /**
     * Zaehlung auswerten und Fehlmengen auf die Liste setzen.
     *
     * @param  array<int, array{bestand?: string|null, fehlt?: bool}>  $zaehlung  je Artikel-ID
     * @return array<int, string> Beschreibung dessen, was auf die Liste kam
     */
    public function uebernehmen(ShoppingList $liste, array $zaehlung): array
    {
        return DB::transaction(function () use ($liste, $zaehlung) {
            $uebernommen = [];

            // Ueber den Global Scope nachgeladen statt den uebermittelten IDs
            // zu vertrauen (Ebene 1½) -- und nur Grundvorrat: ein Artikel ohne
            // dieses Kennzeichen soll nicht ueber ein altes Formular
            // hereinkommen.
            $artikel = Product::grundvorrat()
                ->whereIn('id', array_keys($zaehlung))
                ->orderBy('name')
                ->get();

            foreach ($artikel as $fix) {
                $eingabe = $this->fehlmenge($fix, $zaehlung[$fix->getKey()] ?? []);

                if ($eingabe === null) {
                    continue;
                }

                $this->manager->addOrMerge(
                    liste: $liste,
                    eingabe: $eingabe,
                    source: 'standing',
                    sourceRefId: $fix->getKey(),
                    productId: $fix->getKey(),
                    productGroupId: $fix->product_group_id,
                );

                $uebernommen[] = trim(
                    ShoppingListItem::formatiereMenge($eingabe->amount).' '.($eingabe->unit ?? '').' '.$eingabe->name
                );
            }

            return $uebernommen;
        });
    }

    /**
     * Was von einem Grundvorratsartikel fehlt -- oder null, wenn nichts fehlt
     * oder nicht gezaehlt wurde.
     *
     * Drei Faelle, und der Unterschied zwischen den ersten beiden ist der
     * wichtigste: **Ein leeres Feld heisst "nicht gezaehlt", eine 0 heisst
     * "nichts mehr da".** Wer die Zaehlung unterbricht, findet seine Liste
     * sonst voller Artikel, die er nie geprueft hat.
     *
     * @param  array{bestand?: string|null, fehlt?: bool}  $angabe
     */
    protected function fehlmenge(Product $fix, array $angabe): ?ParsedItem
    {
        $soll = $fix->pantry_target_amount === null ? null : (float) $fix->pantry_target_amount;
        $bestand = $angabe['bestand'] ?? null;

        // Ohne Sollmenge laesst sich nichts rechnen -- dort entscheidet das
        // Kaestchen "fehlt".
        if ($soll === null || $soll <= 0.0) {
            return ($angabe['fehlt'] ?? false)
                ? new ParsedItem(name: $fix->name, unit: $fix->default_unit)
                : null;
        }

        if ($angabe['fehlt'] ?? false) {
            return new ParsedItem(name: $fix->name, amount: $soll, unit: $fix->default_unit);
        }

        if ($bestand === null || $bestand === '') {
            return null;
        }

        $fehlt = round($soll - (float) str_replace(',', '.', (string) $bestand), 3);

        return $fehlt > 0.0
            ? new ParsedItem(name: $fix->name, amount: $fehlt, unit: $fix->default_unit)
            : null;
    }
}
