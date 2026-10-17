<?php

namespace App\Modules\ShoppingList\Services;

use App\Core\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Services\ProductCategorizer;
use App\Modules\Markets\Models\Market;
use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Data\Units;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alles, was an einer Einkaufsliste geaendert wird, laeuft hier durch
 * (C-01 … C-05, C-17).
 *
 * Der Grund fuer die eigene Klasse: dieselben Regeln gelten fuer das Formular,
 * fuer den Sync-Endpunkt und fuer die Assistenten. Laegen sie im Controller,
 * haette jeder weitere Einstieg seine eigene, leicht abweichende Fassung --
 * genau das Muster, das in der alten App zu addItem, addOrMergeItem und
 * addRecipeToList gefuehrt hat.
 */
class ShoppingListManager
{
    public function __construct(protected ProductCategorizer $categorizer) {}

    /**
     * Die aktive Liste der Familie; existiert keine, entsteht sie hier.
     *
     * Dass es nur eine geben kann, sichert die Datenbank ueber den UNIQUE auf
     * (family_id, aktiv_marker) -- diese Methode muss deshalb nicht sperren.
     *
     * Eine frisch angelegte Liste bekommt die Standardvorlage der Familie
     * mit, sofern eine gesetzt ist. Wer das nicht will, weil er selbst noch
     * etwas hineinschreibt und die Vorlage erst danach passt, setzt
     * $mitVorlage auf false -- so macht es der ListArchiver, damit die
     * uebernommenen Fehlartikel nicht neben den gleichen Vorlagenpositionen
     * stehen.
     */
    public function current(?User $user = null, bool $mitVorlage = true): ShoppingList
    {
        $liste = ShoppingList::active()->first();

        if ($liste !== null) {
            return $liste;
        }

        $liste = new ShoppingList([
            'market_id' => Market::where('is_default', true)->value('id'),
        ]);

        $liste->created_by = $user?->getKey();
        $liste->save();

        if ($mitVorlage) {
            // Erst zur Laufzeit aufgeloest: der TemplateApplier braucht
            // seinerseits diesen Manager, im Konstruktor drehten sich beide
            // im Kreis.
            app(TemplateApplier::class)->applyDefault($liste);
        }

        return $liste;
    }

    /**
     * Artikel hinzufuegen -- oder mit einem vorhandenen Eintrag verschmelzen
     * (C-01, C-17).
     *
     * Zusammengefuehrt wird nur mit *offenen* Eintraegen. Was schon im
     * Einkaufswagen liegt, ist erledigt; wer denselben Artikel danach noch
     * einmal aufschreibt, meint eine zweite Packung und nicht die erste.
     */
    public function addOrMerge(
        ShoppingList $liste,
        ParsedItem $eingabe,
        string $source = 'manual',
        ?int $sourceRefId = null,
        ?int $productId = null,
        ?int $productGroupId = null,
        ?string $clientUuid = null,
        bool $angebot = false,
    ): ShoppingListItem {
        return DB::transaction(function () use (
            $liste, $eingabe, $source, $sourceRefId, $productId, $productGroupId, $clientUuid, $angebot
        ) {
            $productId ??= Product::matchingName($eingabe->name)->value('id');

            $vorhanden = $this->passenderOffenerEintrag($liste, $eingabe->name, $productId);

            if ($vorhanden !== null) {
                /*
                 * Das Angebot gewinnt in eine Richtung: Wird derselbe Artikel
                 * mit Haken nachgetragen, wird der vorhandene Eintrag zum
                 * Angebot. Ohne Haken bleibt ein Angebot eines -- ein zweites
                 * "Butter" aus der Vorlage soll die Aktionsbutter nicht
                 * stillschweigend wieder zur regulären machen.
                 *
                 * Vor zusammenfuehren() gesetzt, damit es mit derselben
                 * Speicherung hinausgeht und die Liste nur einmal ihre Version
                 * hochzählt.
                 */
                if ($angebot) {
                    $vorhanden->is_offer = true;
                }

                return $this->zusammenfuehren($vorhanden, $eingabe);
            }

            $item = new ShoppingListItem($eingabe->toAttributes() + [
                'source' => $source,
                'source_ref_id' => $sourceRefId,
                'sort' => $this->naechsteSortierung($liste),
            ]);

            $item->client_uuid = $clientUuid;
            $item->product_id = $productId;
            $item->product_group_id = $this->warengruppe($productGroupId, $productId, $eingabe->name);
            $item->is_offer = $angebot;
            $item->list_id = $liste->getKey();
            $item->family_id = $liste->family_id;
            $item->save();

            return $item;
        });
    }

    /**
     * Dasselbe fuer einen Sync-Client, der seine eigene client_uuid mitbringt
     * (C-08).
     *
     * Der Unterschied zu addOrMerge ist die Wiederholbarkeit. Ein Client, der
     * nach einem Verbindungsabbruch erneut sendet, darf keine zweite Zeile und
     * erst recht keine doppelte Menge erzeugen. Wurde die uuid schon einmal
     * verarbeitet, passiert deshalb nichts mehr -- und wenn der Eintrag mit
     * einem vorhandenen verschmolzen ist, bleibt er als geloeschter Datensatz
     * stehen. Dieser Grabstein hat zwei Aufgaben: er belegt die uuid gegen
     * Wiederholungen, und er sagt den anderen Geraeten, dass ihr Eintrag
     * aufgegangen ist und nicht etwa verloren.
     *
     * @return ShoppingListItem|null der betroffene Eintrag, oder null wenn die
     *                               Mutation schon verarbeitet war
     */
    public function addFromClient(
        ShoppingList $liste,
        string $clientUuid,
        ParsedItem $eingabe,
        string $source = 'manual',
        ?int $productGroupId = null,
    ): ?ShoppingListItem {
        return DB::transaction(function () use ($liste, $clientUuid, $eingabe, $source, $productGroupId) {
            $schonVerarbeitet = $liste->items()->withTrashed()
                ->where('client_uuid', $clientUuid)->exists();

            if ($schonVerarbeitet) {
                return null;
            }

            $productId = Product::matchingName($eingabe->name)->value('id');
            $vorhanden = $this->passenderOffenerEintrag($liste, $eingabe->name, $productId);

            if ($vorhanden === null) {
                return $this->addOrMerge(
                    liste: $liste,
                    eingabe: $eingabe,
                    source: $source,
                    productId: $productId,
                    productGroupId: $productGroupId,
                    clientUuid: $clientUuid,
                );
            }

            $this->zusammenfuehren($vorhanden, $eingabe);
            $this->grabstein($liste, $clientUuid, $eingabe, $source);

            return $vorhanden;
        });
    }

    /**
     * Belegt eine client_uuid, ohne dass ein sichtbarer Eintrag entsteht.
     */
    protected function grabstein(ShoppingList $liste, string $clientUuid, ParsedItem $eingabe, string $source): void
    {
        $marker = new ShoppingListItem($eingabe->toAttributes() + ['source' => $source]);

        $marker->client_uuid = $clientUuid;
        $marker->list_id = $liste->getKey();
        $marker->family_id = $liste->family_id;
        $marker->save();
        $marker->delete();
    }

    /**
     * Abhaken und Haken entfernen (C-02, C-09).
     */
    public function toggle(ShoppingListItem $item, bool $abgehakt, ?User $user = null): ShoppingListItem
    {
        $item->forceFill([
            'is_checked' => $abgehakt,
            'checked_by' => $abgehakt ? $user?->getKey() : null,
            'checked_at' => $abgehakt ? now() : null,
        ])->save();

        return $item;
    }

    /**
     * Menge, Name oder Notiz bearbeiten (C-03).
     *
     * Wird der Name geaendert, wird auch neu zugeordnet: die Korrektur von
     * "Milch" zu "Brot" darf den Eintrag nicht im Kuehlregal stehen lassen.
     * Eine ausdrueckliche Warengruppe (C-05) bleibt davon unberuehrt.
     */
    /**
     * @param  bool|null  $angebot  null heisst: nicht anfassen. Nur die
     *                              Bearbeitungsmaske weiss, ob das Haekchen
     *                              gemeint war -- ein anderer Aufrufer, der
     *                              das Feld nicht kennt, soll ein Angebot
     *                              nicht versehentlich loeschen.
     */
    public function update(ShoppingListItem $item, ParsedItem $eingabe, ?int $productGroupId = null, ?bool $angebot = null): ShoppingListItem
    {
        $nameGeaendert = Product::normalize($eingabe->name) !== $item->vergleichsname();

        $item->fill($eingabe->toAttributes());

        if ($nameGeaendert) {
            $item->product_id = Product::matchingName($eingabe->name)->value('id');
            $item->product_group_id = $this->warengruppe(null, $item->product_id, $eingabe->name);
        }

        if ($productGroupId !== null) {
            $item->product_group_id = $this->gruppeAusEingabe($productGroupId);
        }

        if ($angebot !== null) {
            $item->is_offer = $angebot;
        }

        $item->save();

        return $item;
    }

    /**
     * Artikel loeschen (C-04) -- weich, damit andere Geraete es mitbekommen.
     */
    public function remove(ShoppingListItem $item): void
    {
        $item->delete();
    }

    /**
     * Warengruppe eines Eintrags manuell aendern (C-05).
     */
    public function moveToGroup(ShoppingListItem $item, ?int $productGroupId): ShoppingListItem
    {
        $item->forceFill(['product_group_id' => $this->gruppeAusEingabe($productGroupId)])->save();

        return $item;
    }

    /**
     * Markt der Liste umschalten (C-06). Die Sortierung ergibt sich daraus,
     * die Eintraege selbst bleiben unberuehrt.
     */
    public function switchMarket(ShoppingList $liste, ?int $marketId): ShoppingList
    {
        $liste->forceFill([
            'market_id' => $marketId === null ? null : Market::whereKey($marketId)->value('id'),
        ])->save();

        $liste->bumpVersion();

        return $liste;
    }

    /**
     * Der offene Eintrag, der denselben Artikel meint -- ueber die
     * Katalogverknuepfung, sonst ueber die Normalform des Namens.
     */
    protected function passenderOffenerEintrag(ShoppingList $liste, string $name, ?int $productId): ?ShoppingListItem
    {
        $vergleichsname = Product::normalize($name);

        return $liste->items()->offen()->get()->first(
            fn (ShoppingListItem $item) => $productId !== null && $item->product_id === $productId
                ? true
                : $item->vergleichsname() === $vergleichsname
        );
    }

    /**
     * Zwei Angaben zu demselben Artikel zu einer machen.
     *
     * Addiert wird nur, was sich addieren laesst: gleiche Einheit und beide
     * Mengen rechenbar. Alles andere -- "1-2" plus "3", Stueck plus Gramm --
     * wandert als Zusatz in die Notiz. Das ist die stille Variante: es geht
     * nichts verloren, und niemand findet auf dem Zettel eine Menge, die er
     * nie aufgeschrieben hat.
     */
    protected function zusammenfuehren(ShoppingListItem $item, ParsedItem $eingabe): ShoppingListItem
    {
        $neueEinheit = Units::normalizeOrKeep($eingabe->unit);
        $einheitPasst = $item->unit === null || $neueEinheit === null || $item->unit === $neueEinheit;

        $vorhandeneMenge = $item->amount === null ? null : (float) $item->amount;
        $addierbar = $einheitPasst
            && $eingabe->amount !== null
            && $item->amount_raw === null
            && $eingabe->amountRaw === null;

        if ($addierbar) {
            $item->amount = ($vorhandeneMenge ?? 0.0) + $eingabe->amount;
            $item->unit ??= $neueEinheit;
        } elseif ($vorhandeneMenge === null && $item->amount_raw === null) {
            // Der vorhandene Eintrag hatte gar keine Menge -- dann ist die
            // neue Angabe schlicht die Menge.
            $item->amount = $eingabe->amount;
            $item->amount_raw = $eingabe->amountRaw;
            $item->unit ??= $neueEinheit;
        } else {
            $item->note = $this->notizErgaenzen($item->note, $this->mengenZusatz($eingabe));
        }

        $item->note = $this->notizErgaenzen($item->note, $eingabe->note);
        $item->save();

        return $item;
    }

    protected function mengenZusatz(ParsedItem $eingabe): ?string
    {
        $menge = $eingabe->amountRaw ?? ShoppingListItem::formatiereMenge($eingabe->amount);
        $menge = trim($menge.' '.Units::normalizeOrKeep($eingabe->unit));

        return $menge === '' ? null : 'auch '.$menge;
    }

    protected function notizErgaenzen(?string $vorhanden, ?string $zusatz): ?string
    {
        if ($zusatz === null || $zusatz === '') {
            return $vorhanden;
        }

        if ($vorhanden === null || $vorhanden === '') {
            return Str::limit($zusatz, 160, '');
        }

        if (str_contains($vorhanden, $zusatz)) {
            return $vorhanden;
        }

        return Str::limit($vorhanden.', '.$zusatz, 160, '');
    }

    /**
     * Ausdrueckliche Angabe schlaegt den Katalog, der Katalog schlaegt die
     * Stichwortregeln -- dieselbe Reihenfolge wie im Artikelkatalog (B-07).
     */
    protected function warengruppe(?int $productGroupId, ?int $productId, string $name): ?int
    {
        $gewaehlt = $this->gruppeAusEingabe($productGroupId);

        if ($gewaehlt !== null) {
            return $gewaehlt;
        }

        if ($productId !== null) {
            $ausKatalog = Product::whereKey($productId)->value('product_group_id');

            if ($ausKatalog !== null) {
                return $ausKatalog;
            }
        }

        return $this->categorizer->categorize($name)?->getKey();
    }

    /**
     * Warengruppen-IDs kommen aus Formularen. Ueber den Global Scope
     * nachgeladen kann niemand eine fremde unterschieben (Ebene 1½).
     */
    protected function gruppeAusEingabe(?int $id): ?int
    {
        return $id ? ProductGroup::whereKey($id)->value('id') : null;
    }

    protected function naechsteSortierung(ShoppingList $liste): int
    {
        return ((int) $liste->items()->max('sort')) + 10;
    }
}
