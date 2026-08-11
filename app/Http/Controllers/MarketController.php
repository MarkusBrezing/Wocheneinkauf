<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use App\Modules\Markets\Models\MarketSection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Maerkte, Regalbereiche und deren Zuordnung zu Warengruppen
 * (B-02, B-03, B-04, B-05).
 *
 * Route-Model-Binding greift hier auf Models mit BelongsToFamily zu. Der
 * Global Scope laeuft dabei mit -- ein Markt einer fremden Familie fuehrt
 * also zu 404, ohne dass hier eine einzige Pruefung stehen muesste.
 */
class MarketController extends Controller
{
    public function index(Request $request)
    {
        $family = $this->currentFamily($request);

        return view('stammdaten.maerkte', [
            'maerkte' => Market::with('sections.productGroups')->orderBy('sort')->orderBy('name')->get(),
            'gruppen' => ProductGroup::orderBy('sort')->orderBy('label')->get(),
            'darfVerwalten' => $request->user()->administers($family),
        ]);
    }

    public function store(Request $request)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:80',
                Rule::unique('markets')->where('family_id', $family->getKey())],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
        ], [
            'name.unique' => 'Einen Markt mit diesem Namen gibt es schon.',
        ]);

        $markt = Market::create($daten + ['sort' => $daten['sort'] ?? 0]);

        // Der erste Markt einer Familie wird automatisch Standard, sonst
        // stuende die Einkaufsliste ohne Sortierung da.
        if (! Market::where('is_default', true)->exists()) {
            $markt->makeDefault();
        }

        return back()->with('status', 'Markt angelegt.');
    }

    public function update(Request $request, Market $markt)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:80',
                Rule::unique('markets')->where('family_id', $family->getKey())->ignore($markt)],
            'sort' => ['required', 'integer', 'between:0,65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $aktiv = $request->boolean('is_active');

        // Ein deaktivierter Standardmarkt waere ein Widerspruch: die Liste
        // wuerde nach einem Markt sortiert, den es fuer den Nutzer nicht gibt.
        if (! $aktiv && $markt->is_default) {
            throw ValidationException::withMessages([
                'is_active' => 'Der Standardmarkt lässt sich nicht deaktivieren. Bestimme zuerst einen anderen Standard.',
            ]);
        }

        $markt->update(['name' => $daten['name'], 'sort' => $daten['sort'], 'is_active' => $aktiv]);

        return back()->with('status', 'Markt gespeichert.');
    }

    /**
     * Standardmarkt festlegen (B-05).
     */
    public function makeDefault(Request $request, Market $markt)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        if (! $markt->is_active) {
            throw ValidationException::withMessages([
                'is_default' => 'Ein deaktivierter Markt kann nicht Standard sein.',
            ]);
        }

        $markt->makeDefault();

        return back()->with('status', $markt->name.' ist jetzt der Standardmarkt.');
    }

    public function destroy(Request $request, Market $markt)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        if ($markt->is_default && Market::count() > 1) {
            throw ValidationException::withMessages([
                'markt' => 'Bestimme zuerst einen anderen Standardmarkt.',
            ]);
        }

        $markt->delete();

        return back()->with('status', 'Markt gelöscht.');
    }

    /**
     * Regalbereich anlegen (B-03).
     */
    public function storeSection(Request $request, Market $markt)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120',
                Rule::unique('market_sections')->where('market_id', $markt->getKey())],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
        ], [
            'name.unique' => 'Diesen Regalbereich gibt es in dem Markt schon.',
        ]);

        $markt->sections()->create([
            'name' => $daten['name'],
            'sort' => $daten['sort'] ?? 0,
            'family_id' => $markt->family_id,
        ]);

        return back()->with('status', 'Regalbereich angelegt.');
    }

    public function updateSection(Request $request, MarketSection $bereich)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120',
                Rule::unique('market_sections')->where('market_id', $bereich->market_id)->ignore($bereich)],
            'sort' => ['required', 'integer', 'between:0,65535'],
            'gruppen' => ['array'],
            'gruppen.*' => ['integer'],
            'zuordnung' => ['nullable', 'boolean'],
        ]);

        $bereich->update(['name' => $daten['name'], 'sort' => $daten['sort']]);

        /*
         * Die Zuordnung darf mitkommen, muss aber nicht -- so braucht eine
         * Tabellenzeile nur ein Formular, und Speichern und Loeschen koennen
         * beisammen am rechten Rand stehen.
         *
         * Ob sie gemeint ist, sagt das Merkmal "zuordnung" und nicht die
         * Anwesenheit von "gruppen": Eine Mehrfachauswahl, in der nichts
         * markiert ist, sendet ueberhaupt kein Feld. Am Feld selbst haengte
         * die Entscheidung, waere "alles abwaehlen und speichern" wirkungslos
         * -- und die letzte Warengruppe liesse sich nie mehr entfernen.
         */
        if ($request->boolean('zuordnung')) {
            $this->zuordnungSpeichern($bereich, $request->input('gruppen', []));
        }

        return back()->with('status', 'Regalbereich gespeichert.');
    }

    public function destroySection(Request $request, MarketSection $bereich)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $bereich->delete();

        return back()->with('status', 'Regalbereich gelöscht.');
    }

    /**
     * Warengruppen einem Regalbereich zuordnen (B-04).
     */
    public function syncGroups(Request $request, MarketSection $bereich)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $request->validate([
            'gruppen' => ['array'],
            'gruppen.*' => ['integer'],
        ]);

        $this->zuordnungSpeichern($bereich, $request->input('gruppen', []));

        return back()->with('status', 'Zuordnung gespeichert.');
    }

    /**
     * Warengruppen einem Regalbereich zuordnen.
     *
     * Die IDs werden ueber den Global Scope nachgeladen statt den
     * Eingabewerten zu vertrauen: So kann niemand per manipuliertem Formular
     * eine Warengruppe einer fremden Familie unterschieben. Die Verbund-
     * Fremdschluessel wuerden das zwar ohnehin abweisen (R-03), aber mit einem
     * Datenbankfehler statt einer sauberen Antwort.
     *
     * @param  array<int, mixed>  $gruppen
     */
    protected function zuordnungSpeichern(MarketSection $bereich, array $gruppen): void
    {
        $ids = ProductGroup::whereIn('id', $gruppen)->pluck('id');

        $bereich->productGroups()->sync(
            $ids->mapWithKeys(fn ($id) => [$id => ['family_id' => $bereich->family_id]])->all()
        );
    }
}
