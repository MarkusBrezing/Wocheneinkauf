<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Services\ProductCatalogInstaller;
use App\Modules\Catalog\Services\ProductCategorizer;
use App\Modules\Catalog\Services\ProductMerger;
use App\Modules\Markets\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Artikelkatalog (B-06): anlegen, bearbeiten, Dubletten zusammenfuehren.
 *
 * Anders als bei Warengruppen und Maerkten darf hier jedes Mitglied ran --
 * Artikel anzulegen ist Alltag, keine Strukturentscheidung.
 */
class ProductController extends Controller
{
    /**
     * @var array<int, string>
     */
    protected const SORTIERUNGEN = ['name', 'gruppe', 'neu'];

    /**
     * Der Artikelkatalog als Pflegetabelle.
     *
     * Diese Seite wird am Rechner gepflegt, nicht im Laden -- entsprechend
     * breit ist sie angelegt, und entsprechend viel darf sie auf einmal
     * zeigen. Filter und Sortierung ersetzen das Scrollen durch mehrere
     * hundert Zeilen.
     */
    public function index(Request $request)
    {
        $this->currentFamily($request);

        $suche = trim((string) $request->query('suche', ''));
        $gruppe = $request->integer('gruppe') ?: null;
        $sortierung = in_array($request->query('sortierung'), self::SORTIERUNGEN, true)
            ? $request->query('sortierung')
            : 'name';
        $nurVorrat = $request->boolean('vorrat');
        $nurEingeschraenkt = $request->boolean('eingeschraenkt');

        $produkte = Product::query()
            ->when($suche !== '', fn ($q) => $q->where('name', 'like', '%'.$suche.'%'))
            ->when($gruppe !== null, fn ($q) => $q->where('product_group_id', $gruppe))
            ->when($nurVorrat, fn ($q) => $q->where('is_pantry_staple', true))
            ->when($nurEingeschraenkt, fn ($q) => $q->has('markets'))
            ->with(['productGroup', 'markets'])
            ->tap(fn ($q) => $this->sortiere($q, $sortierung))
            ->paginate(100)
            ->withQueryString();

        return view('stammdaten.produkte', [
            'produkte' => $produkte,
            'gruppen' => ProductGroup::orderBy('sort')->orderBy('label')->get(),
            'maerkte' => Market::active()->orderBy('sort')->orderBy('name')->get(),
            'suche' => $suche,
            'gruppe' => $gruppe,
            'sortierung' => $sortierung,
            'nurVorrat' => $nurVorrat,
            'nurEingeschraenkt' => $nurEingeschraenkt,
            'gesamt' => Product::count(),
            // Nur wer die Familie verwaltet, sieht den Weg zum Zuruecksetzen.
            'darfVerwalten' => $request->user()->administers($request->user()->currentFamily),
        ]);
    }

    /**
     * Die Sicherheitsabfrage vor dem Grundsortiment.
     *
     * Der Vorgang legt nur an und ueberschreibt nichts -- gefragt wird
     * trotzdem, und die Antwort ist mehr wert als ein "wirklich?": Die Seite
     * zeigt, wie viele Artikel dazukaemen, wie sie sich auf die Warengruppen
     * verteilen und welche vorhandenen Eintraege unangetastet bleiben.
     */
    public function showBasics(Request $request, ProductCatalogInstaller $installer)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        return view('stammdaten.grundsortiment', [
            'vorschau' => $installer->vorschau($family, ['grundsortiment']),
        ]);
    }

    /**
     * Grundsortiment in den Katalog laden.
     *
     * Additiv und wiederholbar: Was es unter diesem Namen schon gibt, bleibt
     * unangetastet -- ein gepflegter Eintrag hat Vorrang vor der Vorlage.
     */
    public function installBasics(Request $request, ProductCatalogInstaller $installer)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $ergebnis = $installer->install($family, ['grundsortiment']);

        return redirect()->route('stammdaten.produkte.index')->with(
            'status',
            $ergebnis['angelegt'].' Artikel angelegt'
                .($ergebnis['vorhanden'] > 0 ? ', '.$ergebnis['vorhanden'].' waren schon da und blieben unverändert' : '').'.'
        );
    }

    public function store(Request $request, ProductCategorizer $categorizer)
    {
        $family = $this->currentFamily($request);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'product_group_id' => ['nullable', 'integer'],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'is_pantry_staple' => ['nullable', 'boolean'],
            'pantry_target_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'verfuegbarkeit' => ['nullable', Rule::in(['ueberall', 'auswahl'])],
            'maerkte' => ['array'],
            'maerkte.*' => ['integer'],
        ]);

        $this->pruefeNameFrei($family->getKey(), $daten['name']);

        $product = new Product([
            'name' => $daten['name'],
            'default_unit' => $daten['default_unit'] ?? null,
            'is_pantry_staple' => $request->boolean('is_pantry_staple'),
            'pantry_target_amount' => $daten['pantry_target_amount'] ?? null,
        ]);

        // Ohne Angabe die Stichwortregeln fragen (B-07) -- das ist der Sinn
        // der Auto-Kategorisierung.
        $product->product_group_id = $this->gruppeAusEingabe($daten['product_group_id'] ?? null)
            ?? $categorizer->categorize($daten['name'])?->getKey();

        $product->save();

        $this->verfuegbarkeitSpeichern($product, $request);

        return back()->with('status', 'Artikel „'.$product->name.'" angelegt.');
    }

    public function update(Request $request, Product $produkt)
    {
        $family = $this->currentFamily($request);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'product_group_id' => ['nullable', 'integer'],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'is_pantry_staple' => ['nullable', 'boolean'],
            'pantry_target_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'verfuegbarkeit' => ['nullable', Rule::in(['ueberall', 'auswahl'])],
            'maerkte' => ['array'],
            'maerkte.*' => ['integer'],
        ]);

        $this->pruefeNameFrei($family->getKey(), $daten['name'], $produkt);

        $produkt->update([
            'name' => $daten['name'],
            'default_unit' => $daten['default_unit'] ?? null,
            'is_pantry_staple' => $request->boolean('is_pantry_staple'),
            'pantry_target_amount' => $daten['pantry_target_amount'] ?? null,
            'product_group_id' => $this->gruppeAusEingabe($daten['product_group_id'] ?? null),
        ]);

        $this->verfuegbarkeitSpeichern($produkt, $request);

        return back()->with('status', 'Artikel gespeichert.');
    }

    public function destroy(Request $request, Product $produkt)
    {
        $this->currentFamily($request);

        $produkt->delete();

        return back()->with('status', 'Artikel gelöscht.');
    }

    /**
     * Dubletten zusammenfuehren (B-06).
     */
    public function merge(Request $request, ProductMerger $merger)
    {
        $this->currentFamily($request);

        $request->validate([
            'behalten' => ['required', 'integer'],
            'aufloesen' => ['required', 'integer', 'different:behalten'],
        ], [
            'aufloesen.different' => 'Bitte zwei verschiedene Artikel auswählen.',
        ]);

        // Ueber den Global Scope laden -- fremde IDs enden in 404.
        $behalten = Product::findOrFail($request->integer('behalten'));
        $aufloesen = Product::findOrFail($request->integer('aufloesen'));

        $merger->merge(behalten: $behalten, aufloesen: $aufloesen);

        return back()->with('status', '„'.$aufloesen->name.'" wurde in „'.$behalten->name.'" aufgelöst.');
    }

    /**
     * Nach Warengruppe sortiert wird ueber deren Laufreihenfolge, nicht ueber
     * die ID -- so steht die Tabelle in derselben Ordnung wie die
     * Einkaufsliste. Innerhalb einer Gruppe bleibt es alphabetisch.
     *
     * @param  Builder<Product>  $query
     */
    protected function sortiere($query, string $sortierung): void
    {
        match ($sortierung) {
            'gruppe' => $query
                ->leftJoin('product_groups', 'products.product_group_id', '=', 'product_groups.id')
                ->orderBy('product_groups.sort')
                ->orderBy('product_groups.label')
                ->orderBy('products.name')
                ->select('products.*'),
            'neu' => $query->orderByDesc('products.created_at')->orderByDesc('products.id'),
            default => $query->orderBy('products.name'),
        };
    }

    /**
     * Wo es den Artikel gibt.
     *
     * "ueberall" ist der Normalfall und wird als *leere* Zuordnung
     * gespeichert -- eingetragen wird nur die Ausnahme (siehe Migration
     * market_product). Wird nichts uebermittelt, bleibt die vorhandene
     * Einstellung stehen: ein Formular ohne dieses Feld -- etwa aus einem
     * aelteren Frontend -- darf die Pflege nicht stillschweigend loeschen.
     */
    protected function verfuegbarkeitSpeichern(Product $produkt, Request $request): void
    {
        if (! $request->has('verfuegbarkeit')) {
            return;
        }

        if ($request->input('verfuegbarkeit') !== 'auswahl') {
            $produkt->markets()->sync([]);

            return;
        }

        // Wie bei der Zuordnung Warengruppe -> Regalbereich: die IDs werden
        // ueber den Global Scope nachgeladen, statt ihnen zu vertrauen.
        $ids = Market::whereIn('id', $request->input('maerkte', []))->pluck('id');

        $produkt->markets()->sync(
            $ids->mapWithKeys(fn ($id) => [$id => ['family_id' => $produkt->family_id]])->all()
        );
    }

    /**
     * Die Warengruppe muss zur eigenen Familie gehoeren; der Global Scope
     * erledigt die Pruefung.
     */
    protected function gruppeAusEingabe(?int $id): ?int
    {
        return $id ? ProductGroup::whereKey($id)->value('id') : null;
    }

    /**
     * Meldet einen sprechenden Fehler, wenn der normalisierte Name schon
     * vergeben ist. Die Datenbank haette ohnehin einen UNIQUE-Konflikt
     * geworfen, nur eben als Fehlerseite statt als Formularhinweis.
     *
     * Geprueft wird die Normalform, nicht die Eingabe -- sonst gaelten
     * "Tomaten" und "tomaten (bio)" als verschiedene Artikel, obwohl der
     * UNIQUE-Key sie gleichsetzt.
     */
    protected function pruefeNameFrei(int $familyId, string $name, ?Product $ausser = null): void
    {
        Validator::make(
            ['name' => Product::normalize($name)],
            [
                'name' => [
                    Rule::unique('products', 'name_normalized')
                        ->where('family_id', $familyId)
                        ->whereNull('deleted_at')
                        ->ignore($ausser),
                ],
            ],
            ['name.unique' => 'Einen Artikel mit diesem Namen gibt es schon.'],
        )->validate();
    }
}
