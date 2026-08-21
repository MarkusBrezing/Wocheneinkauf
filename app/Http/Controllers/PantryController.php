<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Pantry\Models\PantryItem;
use App\Modules\Pantry\Services\PantryManager;
use App\Modules\Pantry\Services\ShortfallToList;
use Illuminate\Http\Request;

/**
 * Der Vorrat (E-01, E-02, E-09, E-11, E-12).
 *
 * Rechte wie beim Artikelkatalog: Vorratspflege ist Alltag, jedes Mitglied
 * darf ran.
 */
class PantryController extends Controller
{
    public function index(Request $request)
    {
        $this->currentFamily($request);

        $ort = trim((string) $request->query('ort', ''));
        $nurKnapp = $request->boolean('knapp');

        $positionen = PantryItem::active()
            ->when($ort !== '', fn ($q) => $q->where('location', $ort))
            ->when($nurKnapp, fn ($q) => $q->unterMeldebestand())
            ->with(['product.productGroup'])
            ->orderBy('location')->orderBy('sort')
            ->get();

        return view('vorrat.index', [
            'positionen' => $positionen->groupBy('location'),
            'orte' => PantryItem::active()->distinct()->orderBy('location')->pluck('location'),
            'knapp' => PantryItem::active()->unterMeldebestand()->count(),
            'laeuftAb' => PantryItem::active()
                ->whereNotNull('best_before')
                ->whereDate('best_before', '<=', now()->addDays(7))
                ->count(),
            'artikel' => Product::orderBy('name')->limit(500)->get(['id', 'name', 'default_unit']),
            'ort' => $ort,
            'nurKnapp' => $nurKnapp,
        ]);
    }

    /**
     * Vorratsposition anlegen (E-01).
     */
    public function store(Request $request, PantryManager $pantry)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'product_id' => ['required', 'integer'],
            'location' => ['nullable', 'string', 'max:80'],
            'unit' => ['nullable', 'string', 'max:20'],
            // Ganze Zahlen: Ein Meldebestand von 2,375 Packungen ist keine
            // Angabe, die jemand trifft oder nachhaelt.
            'target_qty' => ['required', 'integer', 'min:0', 'max:9999999'],
            'min_qty' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'current_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'best_before' => ['nullable', 'date'],
        ]);

        // Der Artikel wird ueber den Global Scope nachgeladen -- eine fremde
        // ID trifft ins Leere.
        $produkt = Product::whereKey($daten['product_id'])->firstOrFail();

        $item = $pantry->ensureItem($produkt, $daten['location'] ?: 'Vorrat', [
            'unit' => $daten['unit'] ?? null,
            'target_qty' => $daten['target_qty'],
            'min_qty' => $daten['min_qty'] ?? 0,
            'best_before' => $daten['best_before'] ?? null,
        ]);

        if (($daten['current_qty'] ?? null) !== null) {
            $pantry->setTo($item, (float) $daten['current_qty'], 'correction', $request->user());
        }

        return back()->with('status', $produkt->name.' steht jetzt im Vorrat.');
    }

    public function update(Request $request, PantryItem $position)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'location' => ['required', 'string', 'max:80'],
            'unit' => ['nullable', 'string', 'max:20'],
            // Ganze Zahlen: Ein Meldebestand von 2,375 Packungen ist keine
            // Angabe, die jemand trifft oder nachhaelt.
            'target_qty' => ['required', 'integer', 'min:0', 'max:9999999'],
            'min_qty' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'best_before' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $position->update($daten + [
            'min_qty' => $daten['min_qty'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Vorratsposition gespeichert.');
    }

    public function destroy(Request $request, PantryItem $position)
    {
        $this->currentFamily($request);

        $position->delete();

        return back()->with('status', 'Vorratsposition entfernt.');
    }

    /**
     * Bestand korrigieren -- als Buchung mit Grund (E-02).
     */
    public function book(Request $request, PantryItem $position, PantryManager $pantry)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'delta' => ['required', 'numeric', 'between:-9999999,9999999', 'not_in:0'],
            'reason' => ['required', 'in:purchase,consumption,correction,expired'],
        ]);

        $pantry->book($position, (float) $daten['delta'], $daten['reason'], $request->user());

        return back()->with('status', $position->product?->name.': Bestand ist jetzt '.$position->fresh()->bestandAnzeige().'.');
    }

    /**
     * Verlauf einer Position (E-02, E-10).
     */
    public function movements(Request $request, PantryItem $position)
    {
        $this->currentFamily($request);

        return view('vorrat.buchungen', [
            'position' => $position->load('product'),
            'buchungen' => $position->movements()->with('creator')->limit(100)->get(),
        ]);
    }

    /**
     * Fehlmengen gesammelt auf die Einkaufsliste (E-09).
     */
    /**
     * Die Bestaetigung vor der Uebernahme (E-09).
     *
     * Zeigt Zeile fuer Zeile, was auf die Liste kaeme -- mit Bestand, Soll und
     * Fehlmenge -- und laesst einzelne abwaehlen. Ungefragt fuenfzehn Zeilen
     * auf die Einkaufsliste zu schreiben ist schwer rueckgaengig zu machen:
     * Danach steht man vor einer Liste und weiss nicht mehr, was von Hand
     * daraufkam und was aus dem Vorrat.
     */
    public function showToList(Request $request, ShortfallToList $uebernahme)
    {
        $this->currentFamily($request);

        return view('vorrat.fehlmengen', ['fehlmengen' => $uebernahme->vorschau()]);
    }

    public function toList(Request $request, ShortfallToList $uebernahme)
    {
        $this->currentFamily($request);

        $request->validate([
            'positionen' => ['array'],
            'positionen.*' => ['integer'],
        ]);

        $uebernommen = $uebernahme->fromPantry(
            user: $request->user(),
            nurDiese: $request->filled('positionen') ? $request->input('positionen') : null,
        );

        if ($uebernommen === []) {
            return back()->with('status', 'Alles ausreichend vorhanden — es fehlt nichts.');
        }

        return redirect()->route('einkaufsliste.index')->with('status',
            count($uebernommen).' '.(count($uebernommen) === 1 ? 'Artikel steht' : 'Artikel stehen')
            .' auf der Liste: '.implode(', ', $uebernommen));
    }
}
