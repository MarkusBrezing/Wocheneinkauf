<?php

namespace App\Http\Controllers;

use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rezepte durchsuchen, ansehen, pflegen (D-01, D-02, D-03, D-07).
 *
 * Rechte: wie beim Artikelkatalog darf jedes Mitglied Rezepte pflegen. Ein
 * Rezept ist kein Strukturdatum -- es ordnet nichts fuer andere an, es steht
 * einfach da.
 */
class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $this->currentFamily($request);

        $suche = trim((string) $request->query('suche', ''));
        $tagIds = array_filter((array) $request->query('tags', []));
        $nurFavoriten = $request->boolean('favoriten');

        $rezepte = Recipe::query()
            ->suche($suche)
            ->when($nurFavoriten, fn ($q) => $q->favoriten())
            // Mehrere Tags werden als UND verstanden: "vegetarisch" *und*
            // "schnell" grenzt ein, statt die Liste aufzublaehen.
            ->when($tagIds !== [], function ($q) use ($tagIds) {
                foreach ($tagIds as $tagId) {
                    $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tagId));
                }
            })
            ->with('tags')
            ->withCount('ingredients')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('rezepte.index', [
            'rezepte' => $rezepte,
            'tags' => Tag::orderBy('type')->orderBy('name')->get()->groupBy('type'),
            'suche' => $suche,
            'gewaehlteTags' => array_map('intval', $tagIds),
            'nurFavoriten' => $nurFavoriten,
        ]);
    }

    /**
     * Rezeptdetail (D-02) samt Uebernahmemaske (D-04).
     */
    public function show(Request $request, Recipe $rezept)
    {
        $this->currentFamily($request);

        return view('rezepte.detail', [
            'rezept' => $rezept->load(['ingredients.product', 'tags']),
            'portionen' => (int) $request->query('portionen', $rezept->servings),
            'tags' => Tag::orderBy('type')->orderBy('name')->get()->groupBy('type'),
        ]);
    }

    public function create(Request $request)
    {
        $this->currentFamily($request);

        return view('rezepte.formular', ['rezept' => new Recipe(['servings' => 4, 'effort' => 'normal'])]);
    }

    public function store(Request $request)
    {
        $this->currentFamily($request);

        $rezept = Recipe::create($this->daten($request));

        $rezept->forceFill(['created_by' => $request->user()->getKey()])->save();

        return redirect()->route('rezepte.show', $rezept)
            ->with('status', 'Rezept angelegt. Jetzt fehlen noch die Zutaten.');
    }

    public function edit(Request $request, Recipe $rezept)
    {
        $this->currentFamily($request);

        return view('rezepte.formular', ['rezept' => $rezept]);
    }

    public function update(Request $request, Recipe $rezept)
    {
        $this->currentFamily($request);

        $rezept->update($this->daten($request));

        return redirect()->route('rezepte.show', $rezept)->with('status', 'Rezept gespeichert.');
    }

    public function destroy(Request $request, Recipe $rezept)
    {
        $this->currentFamily($request);

        $rezept->delete();

        return redirect()->route('rezepte.index')->with('status', 'Rezept gelöscht.');
    }

    /**
     * Favorit an- und abschalten (D-07).
     */
    public function toggleFavorite(Request $request, Recipe $rezept)
    {
        $this->currentFamily($request);

        $rezept->forceFill(['is_favorite' => ! $rezept->is_favorite])->save();

        return back();
    }

    /**
     * Schlagworte setzen (D-01). Die IDs werden ueber den Global Scope
     * nachgeladen, damit sich keine fremden unterschieben lassen (Ebene 1½).
     */
    public function syncTags(Request $request, Recipe $rezept)
    {
        $this->currentFamily($request);

        $request->validate([
            'tags' => ['array'],
            'tags.*' => ['integer'],
            'neuer_tag' => ['nullable', 'string', 'max:60'],
            'neuer_tag_typ' => ['nullable', Rule::in(array_keys(Tag::TYPEN))],
        ]);

        $ids = Tag::whereIn('id', $request->input('tags', []))->pluck('id');

        if ($request->filled('neuer_tag')) {
            $neu = Tag::firstOrCreate([
                'type' => $request->input('neuer_tag_typ', 'other'),
                'name' => trim($request->string('neuer_tag')),
            ]);

            $ids->push($neu->getKey());
        }

        $rezept->tags()->sync(
            $ids->unique()->mapWithKeys(fn ($id) => [$id => ['family_id' => $rezept->family_id]])->all()
        );

        return back()->with('status', 'Schlagworte gespeichert.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function daten(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'servings' => ['required', 'integer', 'between:1,99'],
            'prep_minutes' => ['nullable', 'integer', 'between:0,9999'],
            'cook_minutes' => ['nullable', 'integer', 'between:0,9999'],
            'effort' => ['required', Rule::in(['quick', 'normal', 'elaborate'])],
            'instructions' => ['nullable', 'string', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'plant_points' => ['nullable', 'integer', 'between:0,255'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ]);
    }
}
