<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Models\ListTemplate;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use App\Modules\ShoppingList\Services\WeekStarter;
use Illuminate\Http\Request;

/**
 * Assistent "Neue Woche" (C-10).
 *
 * Zeigt, was der Assistent tun wird, bevor er es tut: welche Artikel auf
 * die neue Liste kommen und was mit dem laufenden Einkauf passiert.
 */
class NewWeekController extends Controller
{
    public function show(Request $request, ShoppingListManager $manager)
    {
        $this->currentFamily($request);

        $liste = $manager->current($request->user());

        return view('einkaufsliste.neue-woche', [
            'grundvorrat' => Product::grundvorrat()->with('productGroup')->orderBy('name')->get(),
            'vorlagen' => ListTemplate::withCount('items')->orderBy('sort')->orderBy('name')->get(),
            'liste' => $liste,
            'offen' => $liste->items()->offen()->count(),
            'erledigt' => $liste->items()->where('is_checked', true)->count(),
        ]);
    }

    public function start(Request $request, WeekStarter $starter)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'grundvorrat' => ['array'],
            'grundvorrat.*' => ['integer'],
            'vorlagen' => ['array'],
            'vorlagen.*' => ['integer'],
            'archivieren' => ['nullable', 'boolean'],
            'offene_uebernehmen' => ['nullable', 'boolean'],
        ]);

        $starter->start(
            grundvorratIds: $daten['grundvorrat'] ?? [],
            vorherArchivieren: $request->boolean('archivieren'),
            offeneUebernehmen: $request->boolean('offene_uebernehmen'),
            name: $daten['name'] ?? null,
            user: $request->user(),
            vorlagenIds: $daten['vorlagen'] ?? [],
        );

        return redirect()->route('einkaufsliste.index')->with('status', 'Neue Woche steht.');
    }
}
