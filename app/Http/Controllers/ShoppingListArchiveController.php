<?php

namespace App\Http\Controllers;

use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Services\ListArchiver;
use App\Modules\ShoppingList\Services\ListSorter;
use Illuminate\Http\Request;

/**
 * Das Archiv abgeschlossener Einkaeufe (C-12, C-13, C-14).
 */
class ShoppingListArchiveController extends Controller
{
    /**
     * Archiv durchblaettern (C-12).
     */
    public function index(Request $request)
    {
        $this->currentFamily($request);

        return view('einkaufsliste.archiv', [
            'listen' => ShoppingList::archived()
                ->withCount([
                    'items',
                    'items as offene_count' => fn ($q) => $q->where('is_checked', false),
                ])
                ->with('market')
                ->orderByDesc('archived_at')
                ->paginate(25),
            'darfLoeschen' => $request->user()->administers($request->user()->current_family_id),
        ]);
    }

    /**
     * Einen Archiveintrag ansehen (C-12).
     */
    public function show(Request $request, ShoppingList $liste, ListSorter $sorter)
    {
        $this->currentFamily($request);
        $this->pruefeArchiviert($liste);

        return view('einkaufsliste.archiv-eintrag', [
            'liste' => $liste,
            'abschnitte' => $sorter->sections($liste),
        ]);
    }

    /**
     * Archivierte Liste als aktuelle Liste laden (C-13).
     */
    public function load(Request $request, ShoppingList $liste, ListArchiver $archiver)
    {
        $this->currentFamily($request);
        $this->pruefeArchiviert($liste);

        $anzahl = $archiver->loadIntoCurrent($liste, $request->user());

        return redirect()->route('einkaufsliste.index')
            ->with('status', $anzahl.' '.($anzahl === 1 ? 'Artikel wurde' : 'Artikel wurden').' in die aktuelle Liste übernommen.');
    }

    /**
     * Archiveintrag loeschen (C-14).
     *
     * Die einzige Stelle im Modul, die Admin-Rechte verlangt: Der Eintrag ist
     * die Historie der ganzen Familie, und anders als ein Listeneintrag laesst
     * er sich nicht wieder aufschreiben.
     */
    public function destroy(Request $request, ShoppingList $liste)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);
        $this->pruefeArchiviert($liste);

        $liste->delete();

        return back()->with('status', 'Archiveintrag gelöscht.');
    }

    /**
     * Die aktive Liste gehoert nicht ins Archiv -- sonst liesse sie sich hier
     * loeschen, waehrend jemand anderes gerade einkauft.
     */
    protected function pruefeArchiviert(ShoppingList $liste): void
    {
        abort_unless($liste->isArchived(), 404);
    }
}
