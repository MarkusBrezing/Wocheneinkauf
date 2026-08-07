<?php

namespace App\Http\Controllers;

use App\Modules\Pantry\Models\Inventory;
use App\Modules\Pantry\Models\InventoryLine;
use App\Modules\Pantry\Models\PantryItem;
use App\Modules\Pantry\Services\InventoryRunner;
use App\Modules\Pantry\Services\ShortfallToList;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Der Inventurlauf (E-03 … E-10).
 *
 * Die Zaehlmaske ist bewusst dieselbe fuer alle Mitzaehler: Wer eine Zeile
 * ausfuellt, sieht beim naechsten Laden die Zahlen der anderen. Das ist die
 * einfachste Form von "mehrere zaehlen gleichzeitig" (E-05) -- ohne Sperren,
 * ohne Zuweisung, ohne Absprache.
 */
class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->currentFamily($request);

        return view('vorrat.inventuren', [
            'laufend' => Inventory::offen()->withCount(['lines', 'lines as offene_count' => fn ($q) => $q->whereNull('counted_qty')])->first(),
            'historie' => Inventory::where('status', '!=', 'open')
                ->withCount('lines')
                ->orderByDesc('started_at')
                ->paginate(20),
            'orte' => PantryItem::active()->distinct()->orderBy('location')->pluck('location'),
            'positionen' => PantryItem::active()->count(),
        ]);
    }

    /**
     * Inventur starten (E-03).
     */
    public function store(Request $request, InventoryRunner $runner)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'scope' => ['required', Rule::in(['full', 'location', 'selection'])],
            'scope_value' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:120'],
            'positionen' => ['array'],
            'positionen.*' => ['integer'],
        ]);

        if (Inventory::offen()->exists()) {
            return back()->withErrors(['scope' => 'Es läuft bereits eine Inventur. Schließe sie ab oder brich sie ab.']);
        }

        $inventur = $runner->start(
            umfang: $daten['scope'],
            ort: $daten['scope_value'] ?? null,
            auswahl: $daten['positionen'] ?? null,
            titel: $daten['title'] ?? null,
            user: $request->user(),
        );

        if ($inventur->lines()->doesntExist()) {
            $inventur->delete();

            return back()->withErrors(['scope' => 'Zu dieser Auswahl gibt es keine Vorratsposition.']);
        }

        return redirect()->route('vorrat.inventur.show', $inventur)
            ->with('status', 'Inventur gestartet — '.$inventur->lines()->count().' Positionen zu zählen.');
    }

    /**
     * Die Zaehlmaske (E-04) oder der Bericht (E-08), je nach Zustand.
     */
    public function show(Request $request, Inventory $inventur)
    {
        $this->currentFamily($request);

        $zeilen = $inventur->lines()
            ->with(['pantryItem.product', 'counter'])
            ->get()
            ->sortBy([
                fn ($a, $b) => strcmp($a->pantryItem->location, $b->pantryItem->location),
                fn ($a, $b) => strcmp($a->pantryItem->product?->name ?? '', $b->pantryItem->product?->name ?? ''),
            ]);

        return view($inventur->istOffen() ? 'vorrat.zaehlen' : 'vorrat.bericht', [
            'inventur' => $inventur,
            'zeilen' => $zeilen->groupBy(fn ($zeile) => $zeile->pantryItem->location),
            'offen' => $zeilen->whereNull('counted_qty')->count(),
            'gezaehlt' => $zeilen->whereNotNull('counted_qty')->count(),
            'gesamt' => $zeilen->count(),
        ]);
    }

    /**
     * Zaehlung erfassen (E-04). Ein leeres Feld nimmt sie zurueck.
     */
    public function count(Request $request, InventoryLine $zeile, InventoryRunner $runner)
    {
        $this->currentFamily($request);

        abort_unless($zeile->inventory->istOffen(), 403, 'Diese Inventur ist abgeschlossen.');

        $daten = $request->validate([
            'counted_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:160'],
            'anker' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
        ]);

        $runner->count(
            zeile: $zeile,
            menge: ($daten['counted_qty'] ?? null) === null ? null : (float) $daten['counted_qty'],
            user: $request->user(),
            notiz: $daten['note'] ?? null,
        );

        return $request->filled('anker')
            ? back()->withFragment($request->string('anker')->toString())
            : back();
    }

    /**
     * Inventur abschliessen (E-06): gezaehlte Mengen werden zum Bestand.
     */
    public function complete(Request $request, Inventory $inventur, InventoryRunner $runner)
    {
        $this->currentFamily($request);

        abort_unless($inventur->istOffen(), 403);

        $gebucht = $runner->complete($inventur, $request->user());

        return redirect()->route('vorrat.inventur.show', $inventur)->with('status',
            'Inventur abgeschlossen. '.$gebucht.' '.($gebucht === 1 ? 'Bestand wurde' : 'Bestände wurden').' angepasst.');
    }

    /**
     * Inventur abbrechen (E-07): die Bestaende bleiben unveraendert.
     */
    public function cancel(Request $request, Inventory $inventur, InventoryRunner $runner)
    {
        $this->currentFamily($request);

        abort_unless($inventur->istOffen(), 403);

        $runner->cancel($inventur);

        return redirect()->route('vorrat.inventur.index')
            ->with('status', 'Inventur abgebrochen — die Bestände sind unverändert.');
    }

    /**
     * Fehlmengen der Inventur auf die Einkaufsliste (E-09).
     */
    public function toList(Request $request, Inventory $inventur, ShortfallToList $uebernahme)
    {
        $this->currentFamily($request);

        $uebernommen = $uebernahme->fromInventory($inventur, $request->user());

        if ($uebernommen === []) {
            return back()->with('status', 'Nach dieser Zählung fehlt nichts.');
        }

        return redirect()->route('einkaufsliste.index')->with('status',
            count($uebernommen).' '.(count($uebernommen) === 1 ? 'Artikel steht' : 'Artikel stehen')
            .' auf der Liste: '.implode(', ', $uebernommen));
    }
}
