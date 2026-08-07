<?php

namespace App\Http\Controllers;

use App\Modules\ShoppingList\Data\ListSection;
use App\Modules\ShoppingList\Services\ListSorter;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use Illuminate\Http\Request;

/**
 * Die Einkaufsansicht -- die Liste, wie man sie im Laden braucht.
 *
 * Sie kann genau zwei Dinge: abhaken und den Einkauf beenden. Alles andere
 * fehlt mit Absicht. Auf der Pflegeseite ist ein Eingabefeld selbstverständlich;
 * hier waere es der schnellste Weg, sich mit einer aufgeklappten Tastatur die
 * halbe Liste zu verdecken.
 *
 * Ausgelegt fuer ein Telefon im Hochformat (rund 412 x 915 Punkte): grosse
 * Trefferflaechen, Bedienung unten in Daumenreichweite, wenig zu laden.
 */
class ShoppingModeController extends Controller
{
    public function show(Request $request, ShoppingListManager $manager, ListSorter $sorter)
    {
        $this->currentFamily($request);

        $liste = $manager->current($request->user());

        // Erledigtes ist normalerweise ausgeblendet -- im Laden zaehlt, was
        // noch fehlt. Der Schalter ist ein einfacher Link, damit die Seite
        // ohne JavaScript auskommt.
        $mitErledigten = $request->boolean('erledigt');

        $abschnitte = $sorter->sections($liste)
            ->map(fn ($abschnitt) => $mitErledigten
                ? $abschnitt
                : new ListSection(
                    $abschnitt->name,
                    $abschnitt->items->where('is_checked', false)->values(),
                ))
            ->filter(fn ($abschnitt) => $abschnitt->items->isNotEmpty())
            ->values();

        $offen = $liste->items()->offen()->count();
        $erledigt = $liste->items()->where('is_checked', true)->count();

        return view('einkaufsliste.einkaufen', [
            'liste' => $liste,
            'abschnitte' => $abschnitte,
            'offen' => $offen,
            'erledigt' => $erledigt,
            'gesamt' => $offen + $erledigt,
            'mitErledigten' => $mitErledigten,
        ]);
    }
}
