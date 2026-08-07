<?php

namespace App\Modules\ShoppingList\Services;

use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use App\Modules\ShoppingList\Data\ListSection;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Support\Collection;

/**
 * Bringt die Liste in die Reihenfolge, in der man den Markt durchlaeuft
 * (C-06), und schiebt Erledigtes nach unten (C-16).
 *
 * Die Zuordnung kommt aus Schritt 3: market_sections.sort ist die
 * Laufreihenfolge, market_section_groups sagt, welche Warengruppen in welchem
 * Regalbereich liegen. Ohne gewaehlten Markt bleibt die Warengruppe selbst die
 * Gliederung -- besser als eine Liste in Eingabereihenfolge.
 */
class ListSorter
{
    public const OHNE_BEREICH = 'Ohne Regalbereich';

    public const OHNE_GRUPPE = 'Ohne Warengruppe';

    public const ANDERSWO = 'Gibt es hier nicht';

    /**
     * @return Collection<int, ListSection>
     */
    public function sections(ShoppingList $liste, ?Market $markt = null): Collection
    {
        $markt ??= $liste->market;

        $items = $liste->items()->with(['productGroup', 'checkedBy', 'product.markets'])->get();

        return $markt === null
            ? $this->nachWarengruppen($items)
            : $this->nachRegalbereichen($items, $markt);
    }

    /**
     * @param  Collection<int, ShoppingListItem>  $items
     * @return Collection<int, ListSection>
     */
    protected function nachRegalbereichen(Collection $items, Market $markt): Collection
    {
        // Was es in diesem Markt gar nicht gibt, hat in keinem Regal etwas
        // verloren -- es kaeme sonst unter "Ohne Regalbereich" zu liegen und
        // saehe aus wie ein Pflegefehler.
        $anderswo = $items->filter(fn (ShoppingListItem $item) => ! $item->istErhaeltlichIn($markt));
        $items = $items->reject(fn (ShoppingListItem $item) => ! $item->istErhaeltlichIn($markt));

        $bereiche = $markt->sections()->with('productGroups')->get();

        // Eine Warengruppe kann in mehreren Bereichen liegen. Es gewinnt der
        // erste in Laufreihenfolge -- sonst haenge der Artikel davon ab, in
        // welcher Reihenfolge die Zuordnungen gespeichert wurden.
        $bereichJeGruppe = [];

        foreach ($bereiche as $bereich) {
            foreach ($bereich->productGroups as $gruppe) {
                $bereichJeGruppe[$gruppe->getKey()] ??= $bereich->getKey();
            }
        }

        $abschnitte = collect();

        foreach ($bereiche as $bereich) {
            $treffer = $items->filter(
                fn (ShoppingListItem $item) => $item->product_group_id !== null
                    && ($bereichJeGruppe[$item->product_group_id] ?? null) === $bereich->getKey()
            );

            if ($treffer->isNotEmpty()) {
                $abschnitte->push(new ListSection($bereich->name, $this->reihenfolge($treffer)));
            }
        }

        $rest = $items->filter(
            fn (ShoppingListItem $item) => $item->product_group_id === null
                || ! isset($bereichJeGruppe[$item->product_group_id])
        );

        if ($rest->isNotEmpty()) {
            $abschnitte->push(new ListSection(self::OHNE_BEREICH, $this->reihenfolge($rest)));
        }

        // Ganz ans Ende: die Artikel, die man hier nicht suchen muss.
        if ($anderswo->isNotEmpty()) {
            $abschnitte->push(new ListSection(self::ANDERSWO, $this->reihenfolge($anderswo)));
        }

        return $abschnitte;
    }

    /**
     * @param  Collection<int, ShoppingListItem>  $items
     * @return Collection<int, ListSection>
     */
    protected function nachWarengruppen(Collection $items): Collection
    {
        $gruppen = ProductGroup::orderBy('sort')->orderBy('label')->get();
        $abschnitte = collect();

        foreach ($gruppen as $gruppe) {
            $treffer = $items->where('product_group_id', $gruppe->getKey());

            if ($treffer->isNotEmpty()) {
                $abschnitte->push(new ListSection($gruppe->label, $this->reihenfolge($treffer)));
            }
        }

        $rest = $items->whereNull('product_group_id');

        if ($rest->isNotEmpty()) {
            $abschnitte->push(new ListSection(self::OHNE_GRUPPE, $this->reihenfolge($rest)));
        }

        return $abschnitte;
    }

    /**
     * Innerhalb eines Abschnitts sinkt Erledigtes nach unten (C-16). Bewusst
     * nicht in einen eigenen Block am Listenende: vor dem Regal will man
     * sehen, was hier schon im Wagen liegt.
     *
     * @param  Collection<int, ShoppingListItem>  $items
     * @return Collection<int, ShoppingListItem>
     */
    protected function reihenfolge(Collection $items): Collection
    {
        return $items->sortBy([
            fn (ShoppingListItem $a, ShoppingListItem $b) => ($a->is_checked ? 1 : 0) <=> ($b->is_checked ? 1 : 0),
            fn (ShoppingListItem $a, ShoppingListItem $b) => $a->sort <=> $b->sort,
            fn (ShoppingListItem $a, ShoppingListItem $b) => strcasecmp($a->raw_name, $b->raw_name),
        ])->values();
    }
}
