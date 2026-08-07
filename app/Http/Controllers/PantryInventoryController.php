<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Services\PantryInventory;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use Illuminate\Http\Request;

/**
 * Bestandsaufnahme des Grundvorrats.
 *
 * Der Ablauf, den sie abbildet: Man geht mit dem Handy oder einem Ausdruck an
 * den Vorratsschrank, traegt ein, was noch da ist, und bekommt die Differenz
 * zur Sollmenge auf die Einkaufsliste. Die vollstaendige Inventur mit
 * Zaehlhistorie und Bewegungskonto ist Modul E in Schritt 7 -- hier geht es
 * nur um den Vorrat, der ohnehin jede Woche gebraucht wird.
 */
class PantryInventoryController extends Controller
{
    public function show(Request $request)
    {
        $this->currentFamily($request);

        return view('einkaufsliste.inventur', [
            'vorrat' => Product::grundvorrat()->with('productGroup')
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ShoppingListManager $manager, PantryInventory $inventur)
    {
        $this->currentFamily($request);

        $request->validate([
            'bestand' => ['array'],
            'bestand.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'fehlt' => ['array'],
        ]);

        $zaehlung = $this->zaehlung($request);

        if ($zaehlung === []) {
            return back()->with('status', 'Es war nichts eingetragen — die Liste bleibt, wie sie war.');
        }

        $uebernommen = $inventur->uebernehmen(
            liste: $manager->current($request->user()),
            zaehlung: $zaehlung,
        );

        if ($uebernommen === []) {
            return redirect()->route('einkaufsliste.index')
                ->with('status', 'Alles ausreichend vorhanden — es fehlt nichts.');
        }

        return redirect()->route('einkaufsliste.index')->with('status',
            count($uebernommen).' '.(count($uebernommen) === 1 ? 'Artikel steht' : 'Artikel stehen')
            .' auf der Liste: '.implode(', ', $uebernommen));
    }

    /**
     * Die Eingaben in eine Zaehlung je Artikel zusammenfassen.
     *
     * Ein leeres Feld ohne Haken bedeutet "nicht gezaehlt" und faellt hier
     * schon heraus -- so bleibt im Dienst der Unterschied zwischen "nicht
     * gezaehlt" und "nichts mehr da" (Bestand 0) erhalten.
     *
     * @return array<int, array{bestand: ?string, fehlt: bool}>
     */
    protected function zaehlung(Request $request): array
    {
        $bestaende = $request->input('bestand', []);
        $fehlt = $request->input('fehlt', []);

        $zaehlung = [];

        foreach (array_unique([...array_keys($bestaende), ...array_keys($fehlt)]) as $id) {
            $wert = $bestaende[$id] ?? null;
            $angehakt = (bool) ($fehlt[$id] ?? false);

            if (($wert === null || $wert === '') && ! $angehakt) {
                continue;
            }

            $zaehlung[(int) $id] = ['bestand' => $wert, 'fehlt' => $angehakt];
        }

        return $zaehlung;
    }
}
