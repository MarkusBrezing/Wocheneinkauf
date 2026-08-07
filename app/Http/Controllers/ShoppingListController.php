<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use App\Modules\ShoppingList\Data\ListSection;
use App\Modules\ShoppingList\Models\ListTemplate;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use App\Modules\ShoppingList\Services\ItemTextParser;
use App\Modules\ShoppingList\Services\ListArchiver;
use App\Modules\ShoppingList\Services\ListSorter;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Die aktive Einkaufsliste (C-01 … C-06, C-09, C-11, C-15, C-16).
 *
 * Rechte: Einkaufen ist Alltag, deshalb darf jedes Mitglied hinzufuegen,
 * abhaken, bearbeiten und archivieren -- auch die Rolle 'child'. Nur das
 * endgueltige Loeschen eines Archiveintrags bleibt Owner und Admin
 * vorbehalten (siehe ShoppingListArchiveController).
 */
class ShoppingListController extends Controller
{
    public function __construct(
        protected ShoppingListManager $manager,
        protected ItemTextParser $parser,
    ) {}

    public function index(Request $request, ListSorter $sorter)
    {
        $this->currentFamily($request);

        $liste = $this->manager->current($request->user());

        return view('einkaufsliste.index', [
            'liste' => $liste,
            'abschnitte' => $sorter->sections($liste),
            'maerkte' => Market::active()->orderBy('sort')->orderBy('name')->get(),
            'gruppen' => ProductGroup::orderBy('sort')->orderBy('label')->get(),
            'vorlagen' => ListTemplate::orderBy('sort')->orderBy('name')->get(),
            // Vorschläge für die Eingabe -- mit Warengruppe, damit die Auswahl
            // im Formular sie einschränken kann. 500 ist keine fachliche
            // Grenze, sondern der Punkt, ab dem die Liste im Seitenquelltext
            // mehr kostet, als sie beim Tippen einspart.
            'artikelnamen' => Product::orderBy('name')->limit(500)->get(['name', 'product_group_id']),
            'offen' => $liste->items()->offen()->count(),
            'erledigt' => $liste->items()->where('is_checked', true)->count(),
        ]);
    }

    /**
     * Artikel per Freitext hinzufuegen (C-01, C-17).
     */
    public function store(Request $request)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'menge' => ['nullable', 'string', 'max:40'],
            'text' => ['required', 'string', 'max:200'],
            'product_group_id' => ['nullable', 'integer'],
        ]);

        $eingabe = $this->parser->parse($this->zusammensetzen($daten));
        $liste = $this->manager->current($request->user());

        $item = $this->manager->addOrMerge(
            liste: $liste,
            eingabe: $eingabe,
            productGroupId: $daten['product_group_id'] ?? null,
        );

        // Wer gerade nach einem Markt sortiert, soll gleich erfahren, dass er
        // diesen Artikel dort nicht bekommt -- und nicht erst im Laden.
        $hinweis = $item->istErhaeltlichIn($liste->market_id)
            ? ''
            : ' Gibt es '.$item->verfuegbarkeitshinweis().'.';

        return back()->with('status', $eingabe->name.' steht auf der Liste.'.$hinweis);
    }

    /**
     * Abhaken bzw. Haken entfernen (C-02).
     *
     * Die Einkaufsansicht schickt einen Anker mit: Ohne ihn stünde man nach
     * jedem Tippen wieder am Listenanfang -- bei dreissig Artikeln der Grund,
     * die Seite nicht mehr zu benutzen. Der Anker zeigt auf den Regalbereich,
     * nicht auf den Eintrag: Der verschwindet ja gerade aus der Ansicht.
     */
    public function toggle(Request $request, ShoppingListItem $eintrag)
    {
        $this->currentFamily($request);
        $this->pruefeAktiv($eintrag);

        $request->validate(['anker' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/']]);

        $this->manager->toggle($eintrag, ! $eintrag->is_checked, $request->user());

        return $request->filled('anker')
            ? back()->withFragment($request->string('anker')->toString())
            : back();
    }

    /**
     * Menge, Name, Notiz (C-03) und Warengruppe (C-05) bearbeiten.
     */
    public function update(Request $request, ShoppingListItem $eintrag)
    {
        $this->currentFamily($request);
        $this->pruefeAktiv($eintrag);

        $daten = $request->validate([
            'menge' => ['nullable', 'string', 'max:40'],
            'text' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:160'],
            'product_group_id' => ['nullable', 'integer'],
        ]);

        $eingabe = $this->parser->parse($this->zusammensetzen($daten))->mitNotiz($daten['note'] ?? null);

        $this->manager->update($eintrag, $eingabe, $daten['product_group_id'] ?? null);

        return back()->with('status', 'Eintrag gespeichert.');
    }

    /**
     * Menge und Artikelname wieder zu einer Zeile fuegen.
     *
     * Die Maske trennt beides in zwei Felder -- wer eine Menge tippt, soll
     * nicht im selben Feld den Namen suchen muessen. Gelesen wird trotzdem
     * mit demselben Parser: Er kennt Brueche, Bereiche und Einheiten bereits,
     * und wer aus Gewohnheit "2 l Milch" ins Namensfeld schreibt, bekommt
     * weiterhin das Richtige.
     *
     * @param  array<string, mixed>  $daten
     */
    protected function zusammensetzen(array $daten): string
    {
        return trim(($daten['menge'] ?? '').' '.$daten['text']);
    }

    /**
     * Artikel loeschen (C-04).
     */
    public function destroy(Request $request, ShoppingListItem $eintrag)
    {
        $this->currentFamily($request);
        $this->pruefeAktiv($eintrag);

        $this->manager->remove($eintrag);

        return back()->with('status', $eintrag->raw_name.' wurde von der Liste genommen.');
    }

    /**
     * Markt umschalten -- die Sortierung folgt (C-06).
     */
    public function switchMarket(Request $request)
    {
        $this->currentFamily($request);

        $request->validate(['market_id' => ['nullable', 'integer']]);

        $markt = $request->filled('market_id')
            ? Market::active()->whereKey($request->integer('market_id'))->first()
            : null;

        $this->manager->switchMarket($this->manager->current($request->user()), $markt?->getKey());

        return back()->with('status', $markt === null
            ? 'Die Liste ist jetzt nach Warengruppen sortiert.'
            : 'Sortiert nach '.$markt->name.'.');
    }

    /**
     * Einkauf abschliessen (C-11) und -- auf Wunsch -- die fehlenden Artikel
     * in die neue Liste uebernehmen.
     */
    public function archive(Request $request, ListArchiver $archiver)
    {
        $this->currentFamily($request);

        $request->validate(['offene_uebernehmen' => ['nullable', 'boolean']]);

        $alte = $this->manager->current($request->user());

        if ($alte->items()->doesntExist()) {
            return back()->with('status', 'Die Liste ist leer, es gibt nichts zu archivieren.');
        }

        $uebernehmen = $request->boolean('offene_uebernehmen');
        $neue = $archiver->archive($alte, $uebernehmen, $request->user());
        $uebernommen = $neue->items()->count();

        return redirect()->route('einkaufsliste.index')->with('status', $uebernommen > 0
            ? 'Einkauf archiviert. '.$uebernommen.' fehlende '.($uebernommen === 1 ? 'Artikel steht' : 'Artikel stehen').' auf der neuen Liste.'
            : 'Einkauf archiviert.');
    }

    /**
     * Drucken oder als Text teilen (C-15).
     */
    public function print(Request $request, ListSorter $sorter)
    {
        $this->currentFamily($request);

        $liste = $this->manager->current($request->user());
        $abschnitte = $sorter->sections($liste);

        return view('einkaufsliste.drucken', [
            'liste' => $liste,
            'abschnitte' => $abschnitte,
            'alsText' => $this->alsText($liste, $abschnitte),
        ]);
    }

    /**
     * Die Liste als Klartext -- zum Kopieren in eine Nachricht.
     *
     * @param  Collection<int, ListSection>  $abschnitte
     */
    protected function alsText(ShoppingList $liste, $abschnitte): string
    {
        $zeilen = [$liste->anzeigename(), ''];

        foreach ($abschnitte as $abschnitt) {
            $zeilen[] = $abschnitt->name.':';

            foreach ($abschnitt->items as $item) {
                $menge = $item->mengeAnzeige();

                $zeilen[] = '  '.($item->is_checked ? '[x] ' : '[ ] ')
                    .trim(($menge === '' ? '' : $menge.' ').$item->raw_name)
                    .($item->note ? ' ('.$item->note.')' : '');
            }

            $zeilen[] = '';
        }

        return trim(implode("\n", $zeilen));
    }

    /**
     * An einer archivierten Liste wird nichts mehr geaendert -- sie ist die
     * Historie des Einkaufs. Wer sie weiterverwenden will, laedt sie in die
     * aktuelle Liste (C-13).
     */
    protected function pruefeAktiv(ShoppingListItem $eintrag): void
    {
        abort_if($eintrag->list->isArchived(), 403, 'Diese Liste ist archiviert.');
    }
}
