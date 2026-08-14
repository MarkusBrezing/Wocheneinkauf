<?php

namespace App\Modules\Pantry\Services;

use App\Core\Models\User;
use App\Modules\Pantry\Models\PantryItem;
use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fehlmengen gesammelt auf die Einkaufsliste (E-09).
 *
 * Gerechnet wird Soll minus Ist -- was darueber liegt, bleibt im Schrank und
 * nicht auf dem Zettel.
 */
class ShortfallToList
{
    public function __construct(protected ShoppingListManager $manager) {}

    /**
     * Was auf die Liste kaeme -- ohne es zu tun.
     *
     * Grundlage der Bestaetigung. Eine Uebernahme, die ungefragt fuenfzehn
     * Zeilen auf die Einkaufsliste schreibt, ist schwer rueckgaengig zu
     * machen: Danach steht man vor einer Liste und weiss nicht mehr, was von
     * Hand daraufkam und was aus dem Vorrat.
     *
     * @return Collection<int, array{id: int, name: string, ort: string, bestand: string, soll: string, fehlt: string, einheit: string}>
     */
    public function vorschau(): Collection
    {
        return $this->positionen()
            ->filter(fn (PantryItem $item) => $item->fehlmenge() !== null && $item->product !== null)
            ->map(fn (PantryItem $item) => [
                'id' => $item->getKey(),
                'name' => $item->product->name,
                'ort' => $item->location,
                'bestand' => ShoppingListItem::formatiereMenge($item->current_qty),
                'soll' => ShoppingListItem::formatiereMenge($item->target_qty),
                'fehlt' => ShoppingListItem::formatiereMenge($item->fehlmenge()),
                'einheit' => $item->unit ?? $item->product->default_unit ?? '',
            ])
            ->values();
    }

    /**
     * Alle Positionen unter Soll auf die Liste setzen.
     *
     * @param  array<int, int>|null  $nurDiese  Auswahl von Vorratspositionen
     * @return array<int, string> was uebernommen wurde
     */
    public function fromPantry(?User $user = null, ?array $nurDiese = null): array
    {
        return $this->uebernehmen($this->positionen($nurDiese), $user);
    }

    /**
     * Die Vorratspositionen, um die es geht.
     *
     * Die IDs einer Auswahl werden ueber den Global Scope nachgeladen statt
     * ihnen zu vertrauen -- eine fremde trifft ins Leere.
     *
     * @param  array<int, int>|null  $nurDiese
     * @return Collection<int, PantryItem>
     */
    protected function positionen(?array $nurDiese = null): Collection
    {
        return PantryItem::active()
            ->when($nurDiese !== null, fn ($q) => $q->whereIn('id', $nurDiese))
            ->with('product')
            ->orderBy('location')->orderBy('sort')
            ->get();
    }

    /**
     * @param  Collection<int, PantryItem>  $positionen
     * @return array<int, string>
     */
    protected function uebernehmen($positionen, ?User $user): array
    {
        return DB::transaction(function () use ($positionen, $user) {
            $liste = $this->manager->current($user);
            $uebernommen = [];

            foreach ($positionen as $item) {
                $fehlt = $item->fehlmenge();

                if ($fehlt === null || $item->product === null) {
                    continue;
                }

                $this->manager->addOrMerge(
                    liste: $liste,
                    eingabe: new ParsedItem(
                        name: $item->product->name,
                        amount: $fehlt,
                        unit: $item->unit ?? $item->product->default_unit,
                    ),
                    source: 'pantry',
                    sourceRefId: $item->getKey(),
                    productId: $item->product_id,
                    productGroupId: $item->product->product_group_id,
                );

                $uebernommen[] = trim(
                    ShoppingListItem::formatiereMenge($fehlt)
                    .' '.($item->unit ?? '').' '.$item->product->name
                );
            }

            return $uebernommen;
        });
    }
}
