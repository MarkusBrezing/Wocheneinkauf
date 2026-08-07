<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Models\RecipeIngredient;
use App\Modules\ShoppingList\Services\ItemTextParser;
use Illuminate\Http\Request;

/**
 * Die Zutaten eines Rezepts (D-03) samt Verknuepfung in den Artikelkatalog
 * (D-08).
 *
 * Eingegeben wird wie ueberall in dieser App: eine Zeile Freitext, aus der
 * Menge und Einheit gelesen werden. "200 g Feta" ergibt dieselbe Zerlegung wie
 * auf der Einkaufsliste -- ein Parser, ein Verhalten.
 */
class RecipeIngredientController extends Controller
{
    public function __construct(protected ItemTextParser $parser) {}

    public function store(Request $request, Recipe $rezept)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'text' => ['required', 'string', 'max:200'],
            'buy_label' => ['nullable', 'string', 'max:160'],
            'merge_key' => ['nullable', 'string', 'max:60'],
            'is_pantry' => ['nullable', 'boolean'],
            'is_optional' => ['nullable', 'boolean'],
        ]);

        $eingabe = $this->parser->parse($daten['text']);

        $zutat = new RecipeIngredient([
            'raw_name' => $eingabe->name,
            'amount' => $eingabe->amount,
            'unit' => $eingabe->unit,
            'buy_label' => $daten['buy_label'] ?? null,
            'merge_key' => $daten['merge_key'] ?? null,
            'is_pantry' => $request->boolean('is_pantry'),
            'is_optional' => $request->boolean('is_optional'),
            'sort' => ((int) $rezept->ingredients()->max('sort')) + 10,
            // Kennt der Katalog die Zutat, wird gleich verknuepft (D-08) --
            // dieselbe Klammer wie beim Grundvorrat.
            'product_id' => Product::matchingName($eingabe->name)->value('id'),
        ]);

        $zutat->recipe_id = $rezept->getKey();
        $zutat->family_id = $rezept->family_id;
        $zutat->save();

        return back()->with('status', $eingabe->name.' hinzugefügt.');
    }

    public function update(Request $request, RecipeIngredient $zutat)
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'text' => ['required', 'string', 'max:200'],
            'buy_label' => ['nullable', 'string', 'max:160'],
            'merge_key' => ['nullable', 'string', 'max:60'],
            'is_pantry' => ['nullable', 'boolean'],
            'is_optional' => ['nullable', 'boolean'],
            'product_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
        ]);

        $eingabe = $this->parser->parse($daten['text']);
        $nameGeaendert = Product::normalize($eingabe->name) !== Product::normalize($zutat->raw_name);

        $zutat->fill([
            'raw_name' => $eingabe->name,
            'amount' => $eingabe->amount,
            'unit' => $eingabe->unit,
            'buy_label' => $daten['buy_label'] ?? null,
            'merge_key' => $daten['merge_key'] ?? null,
            'is_pantry' => $request->boolean('is_pantry'),
            'is_optional' => $request->boolean('is_optional'),
            'sort' => $daten['sort'] ?? $zutat->sort,
        ]);

        // Eine ausdrueckliche Auswahl gewinnt; sonst wird bei geaendertem
        // Namen neu gesucht -- ueber den Global Scope, damit kein fremder
        // Artikel hereinkommt.
        if (array_key_exists('product_id', $daten)) {
            $zutat->product_id = $daten['product_id']
                ? Product::whereKey($daten['product_id'])->value('id')
                : null;
        } elseif ($nameGeaendert) {
            $zutat->product_id = Product::matchingName($eingabe->name)->value('id');
        }

        $zutat->save();

        return back()->with('status', 'Zutat gespeichert.');
    }

    public function destroy(Request $request, RecipeIngredient $zutat)
    {
        $this->currentFamily($request);

        $zutat->delete();

        return back()->with('status', $zutat->raw_name.' entfernt.');
    }
}
