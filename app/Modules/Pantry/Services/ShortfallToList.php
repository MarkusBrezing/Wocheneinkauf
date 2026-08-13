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
     * Alle Positionen unter Soll auf die Liste setzen.
     *
     * @param  array<int, int>|null  $nurDiese  Auswahl von Vorratspositionen
     * @return array<int, string> was uebernommen wurde
     */
    public function fromPantry(?User $user = null, ?array $nurDiese = null): array
    {
        $positionen = PantryItem::active()
            ->when($nurDiese !== null, fn ($q) => $q->whereIn('id', $nurDiese))
            ->with('product')
            ->orderBy('location')->orderBy('sort')
            ->get();

        return $this->uebernehmen($positionen, $user);
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
