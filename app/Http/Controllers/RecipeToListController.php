<?php

namespace App\Http\Controllers;

use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Services\RecipeToList;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use Illuminate\Http\Request;

/**
 * Rezept auf die Einkaufsliste setzen (D-04, D-05, D-06).
 */
class RecipeToListController extends Controller
{
    public function store(Request $request, Recipe $rezept, RecipeToList $transfer, ShoppingListManager $manager)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'portionen' => ['nullable', 'integer', 'between:1,99'],
            'zutaten' => ['array'],
            'zutaten.*' => ['integer'],
        ]);

        // Ein leeres Feld "zutaten" heisst hier wirklich "nichts": Das
        // Formular schickt fuer jede Zutat ein Kaestchen mit, wer alle
        // abwaehlt, meint das auch. Nur wenn das Feld ganz fehlt -- etwa beim
        // Aufruf ohne Formular -- greift die Voreinstellung "alles ausser
        // Vorratszutaten".
        $zutaten = $request->has('zutaten') ? ($daten['zutaten'] ?? []) : null;

        $anzahl = $transfer->transfer(
            rezept: $rezept,
            liste: $manager->current($request->user()),
            portionen: $daten['portionen'] ?? null,
            zutatenIds: $zutaten,
        );

        if ($anzahl === 0) {
            return back()->with('status', 'Es war keine Zutat ausgewählt.');
        }

        return redirect()->route('einkaufsliste.index')->with('status',
            $anzahl.' '.($anzahl === 1 ? 'Zutat' : 'Zutaten').' aus „'.$rezept->name.'" auf der Liste.');
    }
}
