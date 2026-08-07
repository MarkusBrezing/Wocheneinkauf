<?php

namespace App\Modules\ShoppingList\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Models\ListTemplate;
use App\Modules\ShoppingList\Models\ListTemplateItem;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

/**
 * Vorlagen auf eine Einkaufsliste bringen -- und umgekehrt eine Liste als
 * Vorlage sichern.
 *
 * Angewendet wird ueber ShoppingListManager::addOrMerge und nicht per Kopie:
 * Eine Vorlage trifft haeufig auf eine Liste, auf der schon etwas steht. Wer
 * "2 l Milch" notiert hat und danach die Wochenvorlage mit "2 l Milch"
 * uebernimmt, will vier Liter -- aber nur einen Eintrag (C-17).
 */
class TemplateApplier
{
    public function __construct(protected ShoppingListManager $manager) {}

    /**
     * @return int Anzahl uebernommener Positionen
     */
    public function apply(ShoppingList $liste, ListTemplate $vorlage): int
    {
        return DB::transaction(function () use ($liste, $vorlage) {
            $anzahl = 0;

            foreach ($vorlage->items()->get() as $position) {
                $this->manager->addOrMerge(
                    liste: $liste,
                    eingabe: $position->alsEingabe(),
                    source: 'template',
                    sourceRefId: $vorlage->getKey(),
                    productId: $position->product_id,
                    productGroupId: $position->product_group_id,
                );

                $anzahl++;
            }

            return $anzahl;
        });
    }

    /**
     * Die Standardvorlage anwenden, falls die Familie eine gesetzt hat.
     *
     * Das ist der Griff, den ShoppingListManager::current() beim Anlegen einer
     * neuen Liste tut -- der eigentliche Sinn der Funktion: nach dem Abschluss
     * eines Einkaufs steht die naechste Liste nicht leer da.
     */
    public function applyDefault(ShoppingList $liste): int
    {
        $vorlage = ListTemplate::standard();

        return $vorlage === null ? 0 : $this->apply($liste, $vorlage);
    }

    /**
     * Eine Vorlage aus dem Stand einer Liste erzeugen.
     *
     * Uebernommen wird alles, was auf der Liste steht -- auch das bereits
     * Abgehakte. Wer den Einkauf gerade erledigt hat, will genau ihn beim
     * naechsten Mal wieder, nicht den Rest.
     */
    public function fromList(ShoppingList $liste, string $name, ?string $beschreibung = null): ListTemplate
    {
        return DB::transaction(function () use ($liste, $name, $beschreibung) {
            $vorlage = ListTemplate::create(['name' => $name, 'description' => $beschreibung]);

            foreach ($liste->items()->orderBy('sort')->orderBy('id')->get() as $item) {
                $this->position($vorlage, $item);
            }

            return $vorlage->refresh();
        });
    }

    /**
     * Eine einzelne Position anlegen -- aus einem Listeneintrag oder aus
     * einer Eingabezeile.
     */
    public function position(ListTemplate $vorlage, ShoppingListItem|ParsedItem $quelle, ?int $productId = null, ?int $productGroupId = null): ListTemplateItem
    {
        $daten = $quelle instanceof ShoppingListItem
            ? [
                'raw_name' => $quelle->raw_name,
                'amount' => $quelle->amount,
                'amount_raw' => $quelle->amount_raw,
                'unit' => $quelle->unit,
                'note' => $quelle->note,
                'product_id' => $quelle->product_id,
                'product_group_id' => $quelle->product_group_id,
            ]
            : $quelle->toAttributes() + [
                'product_id' => $productId ?? Product::matchingName($quelle->name)->value('id'),
                'product_group_id' => $productGroupId,
            ];

        $position = new ListTemplateItem($daten + ['sort' => $this->naechsteSortierung($vorlage)]);

        $position->template_id = $vorlage->getKey();
        $position->family_id = $vorlage->family_id;
        $position->save();

        return $position;
    }

    protected function naechsteSortierung(ListTemplate $vorlage): int
    {
        return ((int) $vorlage->items()->max('sort')) + 10;
    }
}
