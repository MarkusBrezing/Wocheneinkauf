<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Services\ProductCategorizer;
use App\Modules\ShoppingList\Models\ListTemplate;
use App\Modules\ShoppingList\Models\ListTemplateItem;
use App\Modules\ShoppingList\Services\ItemTextParser;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use App\Modules\ShoppingList\Services\TemplateApplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vorlagen fuer neue Einkaufslisten: verwalten und anwenden.
 *
 * Mandantentrennung wie ueberall in Schritt 3/4 beschrieben: Beide Models
 * tragen BelongsToFamily, das Route-Model-Binding laeuft durch den Global
 * Scope -- eine fremde Vorlage endet in 404, ohne dass hier eine Pruefung
 * steht. Die Positionen haengen zusaetzlich ueber einen Verbund-Fremdschluessel
 * an ihrer Vorlage (R-03).
 *
 * Rechte: wie beim Artikelkatalog darf jedes Mitglied Vorlagen pflegen. Sie
 * sind Alltag, keine Strukturentscheidung -- anders als Warengruppen oder
 * Regalbereiche, die die Sortierung fuer alle festlegen.
 */
class ListTemplateController extends Controller
{
    public function __construct(
        protected ItemTextParser $parser,
        protected TemplateApplier $applier,
    ) {}

    public function index(Request $request)
    {
        $this->currentFamily($request);

        return view('vorlagen.index', [
            'vorlagen' => ListTemplate::withCount('items')->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, ListTemplate $vorlage)
    {
        $this->currentFamily($request);

        return view('vorlagen.bearbeiten', [
            'vorlage' => $vorlage->load(['items.productGroup']),
            'gruppen' => ProductGroup::orderBy('sort')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $family = $this->currentFamily($request);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120',
                Rule::unique('list_templates')->where('family_id', $family->getKey())],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Eine Vorlage mit diesem Namen gibt es schon.',
        ]);

        $vorlage = ListTemplate::create($daten);

        return redirect()->route('vorlagen.show', $vorlage)
            ->with('status', 'Vorlage „'.$vorlage->name.'" angelegt. Jetzt fehlen noch die Artikel.');
    }

    public function update(Request $request, ListTemplate $vorlage)
    {
        $family = $this->currentFamily($request);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120',
                Rule::unique('list_templates')->where('family_id', $family->getKey())->ignore($vorlage)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
        ]);

        $vorlage->update($daten + ['sort' => $daten['sort'] ?? $vorlage->sort]);

        return back()->with('status', 'Vorlage gespeichert.');
    }

    public function destroy(Request $request, ListTemplate $vorlage)
    {
        $this->currentFamily($request);

        $vorlage->delete();

        return redirect()->route('vorlagen.index')->with('status', 'Vorlage gelöscht.');
    }

    /**
     * Diese Vorlage beim Anlegen einer neuen Liste automatisch übernehmen --
     * oder die Automatik wieder abschalten.
     */
    public function makeDefault(Request $request, ListTemplate $vorlage)
    {
        $this->currentFamily($request);

        if ($vorlage->is_default) {
            $vorlage->clearDefault();

            return back()->with('status', 'Neue Listen beginnen wieder leer.');
        }

        $vorlage->makeDefault();

        return back()->with('status', 'Neue Listen beginnen jetzt mit „'.$vorlage->name.'".');
    }

    /**
     * Position per Freitext hinzufuegen -- dieselbe Eingabe wie auf der Liste.
     */
    public function storeItem(Request $request, ListTemplate $vorlage, ProductCategorizer $categorizer)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'text' => ['required', 'string', 'max:200'],
            'product_group_id' => ['nullable', 'integer'],
        ]);

        $eingabe = $this->parser->parse($daten['text']);

        $this->applier->position(
            vorlage: $vorlage,
            quelle: $eingabe,
            productGroupId: $this->gruppeAusEingabe($daten['product_group_id'] ?? null)
                ?? $categorizer->categorize($eingabe->name)?->getKey(),
        );

        return back()->with('status', $eingabe->name.' steht in der Vorlage.');
    }

    public function updateItem(Request $request, ListTemplateItem $position)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'text' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:160'],
            'product_group_id' => ['nullable', 'integer'],
        ]);

        $eingabe = $this->parser->parse($daten['text'])->mitNotiz($daten['note'] ?? null);

        $position->update($eingabe->toAttributes() + [
            'product_group_id' => $this->gruppeAusEingabe($daten['product_group_id'] ?? null),
        ]);

        return back()->with('status', 'Position gespeichert.');
    }

    public function destroyItem(Request $request, ListTemplateItem $position)
    {
        $this->currentFamily($request);

        $position->delete();

        return back()->with('status', $position->raw_name.' aus der Vorlage entfernt.');
    }

    /**
     * Vorlage auf die aktuelle Liste setzen.
     */
    public function apply(Request $request, ListTemplate $vorlage, ShoppingListManager $manager)
    {
        $this->currentFamily($request);

        $anzahl = $this->applier->apply($manager->current($request->user()), $vorlage);

        return redirect()->route('einkaufsliste.index')->with('status', $anzahl === 0
            ? 'Die Vorlage „'.$vorlage->name.'" ist leer.'
            : $anzahl.' '.($anzahl === 1 ? 'Artikel' : 'Artikel').' aus „'.$vorlage->name.'" übernommen.');
    }

    /**
     * Die aktuelle Liste als Vorlage sichern -- der bequemste Weg zu einer:
     * einmal von Hand zusammenstellen, dann behalten.
     */
    public function fromList(Request $request, ShoppingListManager $manager)
    {
        $family = $this->currentFamily($request);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120',
                Rule::unique('list_templates')->where('family_id', $family->getKey())],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Eine Vorlage mit diesem Namen gibt es schon.',
        ]);

        $liste = $manager->current($request->user());

        if ($liste->items()->doesntExist()) {
            return back()->withErrors(['name' => 'Die aktuelle Liste ist leer — daraus lässt sich keine Vorlage machen.']);
        }

        $vorlage = $this->applier->fromList($liste, $daten['name'], $daten['description'] ?? null);

        return redirect()->route('vorlagen.show', $vorlage)
            ->with('status', 'Vorlage „'.$vorlage->name.'" mit '.$vorlage->items()->count().' Artikeln angelegt.');
    }

    protected function gruppeAusEingabe(?int $id): ?int
    {
        return $id ? ProductGroup::whereKey($id)->value('id') : null;
    }
}
