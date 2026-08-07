<?php

namespace App\Modules\Recipes\Services;

use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Models\RecipeIngredient;
use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Data\Units;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ein Rezept auf die Einkaufsliste setzen (D-04, D-05, D-06).
 *
 * Drei Dinge passieren dabei, und jedes einzelne war in der alten App eine
 * eigene Fehlerquelle:
 *
 *  1. **Skalieren.** Wer fuer sechs statt vier kocht, braucht anderthalb mal
 *     so viel von allem. Der Faktor gilt nur fuer Zutaten mit Menge -- "etwas
 *     Petersilie" bleibt "etwas Petersilie".
 *  2. **Auswaehlen.** Was im Schrank steht, muss nicht mit. Zutaten mit
 *     is_pantry sind in der Maske vorab abgewaehlt (D-05).
 *  3. **Zusammenfassen.** Zwei Rezepte mit Zwiebeln ergeben einen
 *     Listeneintrag, nicht zwei (D-06) -- ueber merge_key, sonst ueber die
 *     Normalform des Einkaufsnamens.
 */
class RecipeToList
{
    public function __construct(protected ShoppingListManager $manager) {}

    /**
     * @param  array<int, int>|null  $zutatenIds  null = alles ausser Vorratszutaten
     * @return int Anzahl der Eintraege, die auf der Liste gelandet sind
     */
    public function transfer(Recipe $rezept, ShoppingList $liste, ?int $portionen = null, ?array $zutatenIds = null): int
    {
        $faktor = $this->faktor($rezept, $portionen);

        return DB::transaction(function () use ($rezept, $liste, $faktor, $zutatenIds) {
            $eintraege = $this->sammle($rezept, $faktor, $zutatenIds);

            foreach ($eintraege as $eintrag) {
                $this->manager->addOrMerge(
                    liste: $liste,
                    eingabe: $eintrag['eingabe'],
                    source: 'recipe',
                    sourceRefId: $rezept->getKey(),
                    productId: $eintrag['product_id'],
                );
            }

            return $eintraege->count();
        });
    }

    /**
     * Die Zutaten eines Rezepts als fertige Listeneingaben -- skaliert,
     * gefiltert und zusammengefasst.
     *
     * Oeffentlich, weil die Vorschau in der Maske denselben Stand zeigen soll,
     * der spaeter auf der Liste landet.
     *
     * @param  array<int, int>|null  $zutatenIds
     * @return Collection<string, array{eingabe: ParsedItem, product_id: ?int}>
     */
    public function sammle(Recipe $rezept, float $faktor, ?array $zutatenIds = null): Collection
    {
        $gesammelt = collect();

        foreach ($rezept->ingredients()->get() as $zutat) {
            if (! $this->gewaehlt($zutat, $zutatenIds)) {
                continue;
            }

            $schluessel = $zutat->zusammenfassungsschluessel();
            $eingabe = $zutat->alsEingabe($faktor);

            $gesammelt->put($schluessel, $gesammelt->has($schluessel)
                ? $this->verschmelzen($gesammelt->get($schluessel), $eingabe, $zutat)
                : ['eingabe' => $eingabe, 'product_id' => $zutat->product_id]);
        }

        return $gesammelt;
    }

    /**
     * Der Skalierungsfaktor. Ohne Angabe oder bei unsinnigen Werten bleibt es
     * bei den Portionen des Rezepts.
     */
    public function faktor(Recipe $rezept, ?int $portionen): float
    {
        $basis = max(1, (int) $rezept->servings);

        return $portionen === null || $portionen < 1 ? 1.0 : $portionen / $basis;
    }

    /**
     * Ohne ausdrueckliche Auswahl kommt alles mit, was nicht als Vorrat
     * gekennzeichnet ist (D-05).
     *
     * @param  array<int, int>|null  $zutatenIds
     */
    protected function gewaehlt(RecipeIngredient $zutat, ?array $zutatenIds): bool
    {
        return $zutatenIds === null
            ? ! $zutat->is_pantry
            : in_array($zutat->getKey(), $zutatenIds, false);
    }

    /**
     * Zwei Zutaten unter demselben Schluessel zu einer Eingabe machen.
     *
     * Addiert wird nur bei gleicher Einheit -- dieselbe Zurueckhaltung wie auf
     * der Liste selbst (C-17). Sonst bleibt die erste Angabe stehen und die
     * zweite wandert in die Notiz.
     *
     * @param  array{eingabe: ParsedItem, product_id: ?int}  $bisher
     * @return array{eingabe: ParsedItem, product_id: ?int}
     */
    protected function verschmelzen(array $bisher, ParsedItem $neu, RecipeIngredient $zutat): array
    {
        $alt = $bisher['eingabe'];
        $einheitPasst = $alt->unit === $neu->unit;

        if ($einheitPasst && $alt->amount !== null && $neu->amount !== null) {
            $eingabe = new ParsedItem(
                name: $alt->name,
                amount: round($alt->amount + $neu->amount, 3),
                unit: $alt->unit,
                note: $alt->note,
            );
        } else {
            $zusatz = trim(($neu->amount === null ? '' : $neu->amount.' ').Units::normalizeOrKeep($neu->unit).' '.$neu->name);
            $eingabe = $alt->mitNotiz(trim(($alt->note ? $alt->note.', ' : '').'auch '.$zusatz));
        }

        return [
            'eingabe' => $eingabe,
            'product_id' => $bisher['product_id'] ?? $zutat->product_id,
        ];
    }
}
