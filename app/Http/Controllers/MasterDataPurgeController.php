<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Services\MasterDataPurger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Stammdaten zuruecksetzen: Artikel, Warengruppen und Maerkte auf einmal
 * loeschen.
 *
 * Zwei Seiten, nicht eine: erst die Vorschau mit allen Folgen, dann die
 * Ausfuehrung. Ein Knopf mit Bestaetigungsfenster waere schneller -- und
 * genau das ist das Problem. Wer hier landet, soll einen Moment gebremst
 * werden.
 */
class MasterDataPurgeController extends Controller
{
    public function __construct(protected MasterDataPurger $purger) {}

    /**
     * Die Sicherheitsabfrage: was verschwindet, was mit verschwindet, und was
     * seine Verweise verliert.
     */
    public function show(Request $request)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        return view('stammdaten.zuruecksetzen', [
            'family' => $family,
            'vorschau' => $this->purger->vorschau(),
        ]);
    }

    /**
     * Ausfuehren -- nur mit abgetipptem Familiennamen.
     */
    public function destroy(Request $request)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $daten = $request->validate([
            'bestaetigung' => ['required', 'string'],
            'startdatensatz' => ['nullable', 'boolean'],
        ]);

        // Den Namen der Familie abtippen zu muessen ist die einzige Huerde,
        // die man nicht aus Versehen nimmt. Ein Haken ist schneller
        // angeklickt als gelesen, und ein Bestaetigungsfenster erst recht.
        if (trim($daten['bestaetigung']) !== $family->name) {
            throw ValidationException::withMessages([
                'bestaetigung' => 'Zum Löschen muss der Familienname genau so dastehen: „'.$family->name.'".',
            ]);
        }

        $geloescht = $this->purger->purge($family, $request->boolean('startdatensatz'));

        $meldung = collect($geloescht)
            ->filter(fn (int $anzahl) => $anzahl > 0)
            ->map(fn (int $anzahl, string $was) => $anzahl.' '.$was)
            ->implode(', ');

        return redirect()->route('stammdaten.produkte.index')->with(
            'status',
            $meldung === ''
                ? 'Es gab nichts zu löschen.'
                : 'Gelöscht: '.$meldung.'.'.($request->boolean('startdatensatz') ? ' Startdatensatz neu eingespielt.' : '')
        );
    }
}
