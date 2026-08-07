<?php

namespace App\Modules\ShoppingList\Services;

use App\Core\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Models\ListTemplate;
use App\Modules\ShoppingList\Models\ShoppingList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Der Assistent "Neue Woche" (C-10).
 *
 * Er macht zwei Handgriffe, die sonst jede Woche von Hand anfallen: den alten
 * Einkauf abschliessen und den Grundvorrat wieder aufschreiben. Die
 * Rezeptauswahl, die der Use Case ebenfalls nennt, kommt mit dem Modul Recipes
 * in Schritt 6 dazu -- die Anschlussstelle ist ShoppingListManager::addOrMerge
 * mit source='recipe'.
 */
class WeekStarter
{
    public function __construct(
        protected ShoppingListManager $manager,
        protected ListArchiver $archiver,
        protected TemplateApplier $applier,
    ) {}

    /**
     * @param  array<int, int>|null  $grundvorratIds  null = der gesamte Grundvorrat
     * @param  array<int, int>  $vorlagenIds  Vorlagen, die zusaetzlich auf die
     *                                        neue Liste kommen
     */
    public function start(
        ?array $grundvorratIds = null,
        bool $vorherArchivieren = true,
        bool $offeneUebernehmen = true,
        ?string $name = null,
        ?User $user = null,
        array $vorlagenIds = [],
    ): ShoppingList {
        return DB::transaction(function () use (
            $grundvorratIds, $vorherArchivieren, $offeneUebernehmen, $name, $user, $vorlagenIds
        ) {
            $liste = $this->manager->current($user, mitVorlage: false);

            // Nur archivieren, wenn auf der Liste ueberhaupt etwas steht --
            // sonst haeuft der Assistent leere Archiveintraege an.
            //
            // Ohne Standardvorlage: Im Assistenten waehlt der Nutzer selbst,
            // welche Vorlagen mitkommen. Wuerde sie hier automatisch
            // angewendet und unten noch einmal bewusst gewaehlt, verdoppelten
            // sich ihre Mengen.
            if ($vorherArchivieren && $liste->items()->exists()) {
                $liste = $this->archiver->archive($liste, $offeneUebernehmen, $user, mitStandardvorlage: false);
            }

            if ($name !== null && $name !== '') {
                $liste->update(['name' => $name]);
            }

            foreach ($this->grundvorrat($grundvorratIds) as $artikel) {
                $this->manager->addOrMerge(
                    liste: $liste,
                    eingabe: new ParsedItem(
                        name: $artikel->name,
                        amount: $artikel->pantry_target_amount === null
                            ? null
                            : (float) $artikel->pantry_target_amount,
                        unit: $artikel->default_unit,
                    ),
                    source: 'standing',
                    sourceRefId: $artikel->getKey(),
                    productId: $artikel->getKey(),
                    productGroupId: $artikel->product_group_id,
                );
            }

            foreach ($this->vorlagen($vorlagenIds) as $vorlage) {
                $this->applier->apply($liste, $vorlage);
            }

            return $liste->refresh();
        });
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, ListTemplate>
     */
    protected function vorlagen(array $ids): Collection
    {
        if ($ids === []) {
            return ListTemplate::query()->whereRaw('1 = 0')->get();
        }

        // Wie beim Grundvorrat: ueber den Global Scope nachgeladen, damit
        // eine fremde ID ins Leere trifft.
        return ListTemplate::whereIn('id', $ids)->orderBy('sort')->orderBy('name')->get();
    }

    /**
     * @param  array<int, int>|null  $ids
     * @return Collection<int, Product>
     */
    protected function grundvorrat(?array $ids): Collection
    {
        return Product::grundvorrat()
            // IDs kommen aus einem Formular und werden ueber den Global Scope
            // nachgeladen -- eine fremde ID trifft ins Leere statt in eine
            // fremde Familie (Ebene 1½).
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }
}
