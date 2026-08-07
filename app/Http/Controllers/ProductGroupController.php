<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Warengruppen anlegen, umbenennen, sortieren, loeschen (B-01).
 */
class ProductGroupController extends Controller
{
    public function index(Request $request)
    {
        $this->currentFamily($request);

        return view('stammdaten.warengruppen', [
            // Kein where family_id noetig: der Global Scope filtert bereits.
            'gruppen' => ProductGroup::withCount('products')->orderBy('sort')->orderBy('label')->get(),
            'darfVerwalten' => $request->user()->administers($request->user()->currentFamily),
        ]);
    }

    public function store(Request $request)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $daten = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('product_groups')->where('family_id', $family->getKey())],
            'label' => ['required', 'string', 'max:80'],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
        ], [
            'code.regex' => 'Das Kürzel darf nur Großbuchstaben, Ziffern und Unterstriche enthalten.',
            'code.unique' => 'Dieses Kürzel gibt es in eurer Familie schon.',
        ]);

        ProductGroup::create($daten + ['sort' => $daten['sort'] ?? 0]);

        return back()->with('status', 'Warengruppe angelegt.');
    }

    public function update(Request $request, ProductGroup $warengruppe)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        $daten = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'sort' => ['required', 'integer', 'between:0,65535'],
        ]);

        // code bleibt unveraenderlich: Stichwortregeln und der Startdatensatz
        // beziehen sich darauf.
        $warengruppe->update($daten);

        return back()->with('status', 'Warengruppe gespeichert.');
    }

    public function destroy(Request $request, ProductGroup $warengruppe)
    {
        $family = $this->currentFamily($request);
        $this->requireAdmin($request, $family);

        // Produkte verlieren beim Loeschen nur ihre Zuordnung (ON DELETE SET
        // NULL). Bei vielen betroffenen Artikeln ist das aber selten gewollt,
        // deshalb erst nachfragen.
        if ($warengruppe->products()->count() > 0 && ! $request->boolean('trotzdem')) {
            throw ValidationException::withMessages([
                'gruppe' => 'Zu dieser Warengruppe gehören noch '.$warengruppe->products()->count()
                    .' Artikel. Sie verlieren ihre Zuordnung. Zum Bestätigen erneut senden.',
            ]);
        }

        $warengruppe->delete();

        return back()->with('status', 'Warengruppe gelöscht.');
    }
}
