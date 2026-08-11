<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use App\Modules\Markets\Models\MarketSection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $maerkte = Market::with('sections.productGroups')->orderBy('sort')->orderBy('name')->get();
        $gruppen = ProductGroup::orderBy('sort')->orderBy('label')->get();

        return view('stammdaten.maerkte', [
            'maerkte' => $maerkte,
            'gruppen' => $gruppen,
            /*
             * Je Markt die Warengruppen, die dort noch in keinem Regal liegen.
             * Nur sie stehen im Auswahlfeld -- eine Warengruppe gehoert je
             * Markt in genau ein Regal, und was schon liegt, soll gar nicht
             * erst zur Wahl stehen.
             */
            'freieGruppen' => $maerkte->mapWithKeys(function (Market $markt) use ($gruppen) {
                $belegt = $markt->sections->flatMap(
                    fn (MarketSection $b) => $b->productGroups->modelKeys()
                )->all();

                return [$markt->getKey() => $gruppen->whereNotIn('id', $belegt)->values()];
            }),
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
        ]);

        // Nur Name und Reihenfolge. Die Zuordnung der Warengruppen laeuft
        // seither ueber attachGroup und detachGroup -- eine Warengruppe nach
        // der anderen, statt einer Mehrfachauswahl, die alles auf einmal
        // ueberschreibt.
        $bereich->update($daten);

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
     * Eine einzelne Warengruppe einem Regalbereich zuordnen (B-04).
     *
     * Zur Auswahl steht nur, was in diesem Markt noch nirgends liegt. Das ist
     * keine Bequemlichkeit, sondern die Regel selbst: Eine Warengruppe gehoert
     * je Markt in genau ein Regal. Die fruehere Mehrfachauswahl liess den
     * Widerspruch zu, und die Sortierung musste ihn hinterher aufloesen ("der
     * erste Bereich in Laufreihenfolge gewinnt"). Jetzt kann er gar nicht mehr
     * entstehen.
     */
    public function attachGroup(Request $request, MarketSection $bereich)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $request->validate(['product_group_id' => ['required', 'integer']]);

        $gruppe = $this->warengruppe($request);
        $belegt = $this->belegteGruppen($bereich->market_id);

        // Der Fall tritt im Formular nicht auf -- dort steht Belegtes gar nicht
        // zur Wahl. Zwei offene Browserfenster reichen aber, damit er doch
        // eintritt, und dann ist eine Meldung besser als ein zweiter Eintrag.
        if ($belegt->has($gruppe->getKey())) {
            throw ValidationException::withMessages([
                'product_group_id' => $gruppe->label.' liegt in diesem Markt schon im Regalbereich „'
                    .$belegt->get($gruppe->getKey()).'“.',
            ]);
        }

        $bereich->productGroups()->attach($gruppe->getKey(), ['family_id' => $bereich->family_id]);

        return back()->with('status', $gruppe->label.' liegt jetzt im Bereich „'.$bereich->name.'“.');
    }

    /**
     * Eine einzelne Warengruppe wieder herausnehmen -- der Gegenweg zum
     * Zuordnen, mit demselben Zuschnitt. Danach steht sie in diesem Markt
     * wieder zur Auswahl.
     */
    public function detachGroup(Request $request, MarketSection $bereich)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $request->validate(['product_group_id' => ['required', 'integer']]);

        $gruppe = $this->warengruppe($request);

        $bereich->productGroups()->detach($gruppe->getKey());

        return back()->with('status', $gruppe->label.' liegt nicht mehr im Bereich „'.$bereich->name.'“.');
    }

    /**
     * Die Warengruppe aus der Anfrage -- ueber den Global Scope nachgeladen
     * statt der Eingabe vertraut.
     *
     * So trifft eine untergeschobene fremde ID ins Leere (404), statt spaeter
     * am Verbund-Fremdschluessel mit einem Datenbankfehler zu scheitern.
     */
    protected function warengruppe(Request $request): ProductGroup
    {
        return ProductGroup::whereKey($request->integer('product_group_id'))->firstOrFail();
    }

    /**
     * Welche Warengruppen in diesem Markt schon in einem Regal liegen.
     *
     * @return Collection<int, string> Warengruppen-ID => Name des Bereichs
     */
    protected function belegteGruppen(int $marktId): Collection
    {
        // Bewusst put() in einer Schleife statt flatMap(): flatMap wirft die
        // Schluessel weg, und genau die sind hier der Inhalt -- die Zuordnung
        // Warengruppe => Regalbereich.
        return MarketSection::where('market_id', $marktId)
            ->with('productGroups')
            ->get()
            ->reduce(function (Collection $belegt, MarketSection $bereich) {
                foreach ($bereich->productGroups as $gruppe) {
                    $belegt->put($gruppe->getKey(), $bereich->name);
                }

                return $belegt;
            }, collect());
    }
}
