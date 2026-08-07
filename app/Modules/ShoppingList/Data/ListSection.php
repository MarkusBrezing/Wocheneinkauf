<?php

namespace App\Modules\ShoppingList\Data;

use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Support\Collection;

/**
 * Ein Abschnitt der sortierten Liste: der Regalbereich eines Marktes, oder --
 * wenn kein Markt gewaehlt ist -- eine Warengruppe.
 */
class ListSection
{
    /**
     * @param  Collection<int, ShoppingListItem>  $items
     */
    public function __construct(
        public readonly string $name,
        public readonly Collection $items,
    ) {}

    public function offen(): int
    {
        return $this->items->where('is_checked', false)->count();
    }

    public function erledigt(): int
    {
        return $this->items->where('is_checked', true)->count();
    }
}
