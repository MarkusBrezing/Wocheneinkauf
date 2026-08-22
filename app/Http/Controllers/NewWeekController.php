<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Models\ListTemplate;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use App\Modules\ShoppingList\Services\WeekStarter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Assistent "Neue Woche" (C-10).
 *
 * Zeigt, was der Assistent tun wird, bevor er es tut: welche Artikel auf
 * die neue Liste kommen und was mit dem laufenden Einkauf passiert.
 */
class NewWeekController extends Controller
{
    /** Alles, eine Auswahl, nichts. */
    public const MODI = ['alle', 'auswahl', 'keine'];

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
            'grundvorrat_modus' => ['nullable', Rule::in(self::MODI)],
            'grundvorrat' => ['array'],
            'grundvorrat.*' => ['integer'],
            'vorlagen' => ['array'],
            'vorlagen.*' => ['integer'],
            'archivieren' => ['nullable', 'boolean'],
            'offene_uebernehmen' => ['nullable', 'boolean'],
        ]);

        $starter->start(
            grundvorratIds: $this->grundvorratIds($daten),
            vorherArchivieren: $request->boolean('archivieren'),
            offeneUebernehmen: $request->boolean('offene_uebernehmen'),
            name: $daten['name'] ?? null,
            user: $request->user(),
            vorlagenIds: $daten['vorlagen'] ?? [],
        );

        return redirect()->route('einkaufsliste.index')->with('status', 'Neue Woche steht.');
    }

    /**
     * Welche Grundvorratsartikel auf die neue Liste kommen.
     *
     * Drei Moeglichkeiten statt 145 Haekchen: alles, eine Auswahl, nichts.
     * Vorher gab es nur die Haekchen -- "gar nichts" hiess damit, 145 Kaesten
     * einzeln abzuwaehlen, und das tut niemand.
     *
     * Massgeblich ist der Modus, nicht die Haekchen. Ohne JavaScript bleiben
     * die Kaesten sichtbar und angehakt; wer dann "Keinen" waehlt, bekommt
     * auch keinen. Andersherum -- die Haekchen entscheiden zu lassen -- waere
     * genau die Falle, in die ein leeres <select multiple> schon einmal
     * gefuehrt hat: Ein Feld, das der Browser gar nicht erst sendet, sieht
     * serverseitig aus wie "nicht angefasst".
     *
     * Bei "alle" wird ueber den Global Scope neu abgefragt statt den
     * gesendeten IDs zu trauen.
     *
     * Fehlt der Modus ganz, entscheidet die Anwesenheit der Liste: Wer
     * grundvorrat[] schickt, meint genau diese Artikel. Das war die Zusage
     * vor dieser Aenderung, und ein Aufruf von aussen -- ein Lesezeichen, ein
     * Skript -- darf daran nicht stillschweigend scheitern.
     *
     * @param  array<string, mixed>  $daten
     * @return array<int, int>
     */
    protected function grundvorratIds(array $daten): array
    {
        $modus = $daten['grundvorrat_modus']
            ?? (array_key_exists('grundvorrat', $daten) ? 'auswahl' : 'alle');

        return match ($modus) {
            'keine' => [],
            'auswahl' => $daten['grundvorrat'] ?? [],
            default => Product::grundvorrat()->pluck('id')->all(),
        };
    }
}
