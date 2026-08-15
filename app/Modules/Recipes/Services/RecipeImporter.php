<?php

namespace App\Modules\Recipes\Services;

use App\Core\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Recipes\Models\Recipe;
use App\Modules\Recipes\Models\RecipeIngredient;
use App\Modules\Recipes\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Rezepte aus einer strukturierten Datei uebernehmen (D-09).
 *
 * Der Weg, den die Anwendung dafuer vorsieht: Man laesst sich Rezepte von
 * einer Sprach-KI in genau dieses Format schreiben (der Prompt steht auf der
 * Importseite) und laedt die Datei hoch. Das ist der Grund, warum die Felder
 * deutsch heissen und flach liegen -- so kann man das Ergebnis vor dem
 * Hochladen noch lesen und von Hand nachbessern.
 *
 * Zwei Durchgaenge ueber dieselbe Datei: `pruefen()` sagt, was passieren
 * wuerde, `importieren()` tut es. Die Vorschau darf nicht raten, sonst
 * verspricht die Bestaetigungsseite etwas anderes, als hinterher geschieht.
 */
class RecipeImporter
{
    /** Mehr als das nimmt eine Familie nicht auf einmal auf. */
    public const MAX_REZEPTE = 200;

    /**
     * Datei einlesen und pruefen -- ohne etwas anzulegen.
     *
     * @return array{
     *     rezepte: array<int, array<string, mixed>>,
     *     neu: array<int, string>,
     *     vorhanden: array<int, string>,
     *     fehler: array<int, string>
     * }
     */
    public function pruefen(string $inhalt): array
    {
        $daten = json_decode($inhalt, true);

        if (! is_array($daten)) {
            return $this->leer(['Die Datei ist kein gültiges JSON: '.json_last_error_msg()]);
        }

        // Der Prompt liefert ein Objekt mit "rezepte"; eine blanke Liste
        // nehmen wir auch -- wer von Hand nachbessert, kuerzt gern.
        $rohe = $daten['rezepte'] ?? (array_is_list($daten) ? $daten : null);

        if (! is_array($rohe) || $rohe === []) {
            return $this->leer(['Die Datei enthält kein Feld „rezepte" mit Einträgen.']);
        }

        if (count($rohe) > self::MAX_REZEPTE) {
            return $this->leer([
                'Die Datei enthält '.count($rohe).' Rezepte. Mehr als '
                .self::MAX_REZEPTE.' auf einmal nimmt der Import nicht an.',
            ]);
        }

        $rezepte = [];
        $neu = [];
        $vorhanden = [];
        $fehler = [];

        foreach ($rohe as $i => $rezept) {
            $nummer = $i + 1;

            if (! is_array($rezept)) {
                $fehler[] = 'Rezept '.$nummer.': kein Objekt.';

                continue;
            }

            $pruefung = Validator::make($rezept, $this->regeln());

            if ($pruefung->fails()) {
                $name = is_string($rezept['name'] ?? null) ? $rezept['name'] : 'Nr. '.$nummer;
                $fehler[] = $name.': '.implode(' ', $pruefung->errors()->all());

                continue;
            }

            $sauber = $pruefung->validated();

            // Ein vorhandenes Rezept wird nicht ueberschrieben: Was von Hand
            // gepflegt wurde, hat Vorrang vor einer Datei.
            if (Recipe::where('name', $sauber['name'])->exists()) {
                $vorhanden[] = $sauber['name'];

                continue;
            }

            // Auch innerhalb einer Datei nur einmal.
            if (in_array($sauber['name'], $neu, true)) {
                $vorhanden[] = $sauber['name'];

                continue;
            }

            $rezepte[] = $sauber;
            $neu[] = $sauber['name'];
        }

        return ['rezepte' => $rezepte, 'neu' => $neu, 'vorhanden' => $vorhanden, 'fehler' => $fehler];
    }

    /**
     * Die geprueften Rezepte anlegen.
     *
     * @param  array<int, array<string, mixed>>  $rezepte  aus pruefen()
     * @return int Anzahl angelegter Rezepte
     */
    public function importieren(array $rezepte, ?User $user = null): int
    {
        return DB::transaction(function () use ($rezepte, $user) {
            $angelegt = 0;

            foreach ($rezepte as $eintrag) {
                // Zwischen Vorschau und Bestaetigung kann jemand anders
                // dasselbe Rezept angelegt haben.
                if (Recipe::where('name', $eintrag['name'])->exists()) {
                    continue;
                }

                $this->anlegen($eintrag, $user);
                $angelegt++;
            }

            return $angelegt;
        });
    }

    protected function anlegen(array $eintrag, ?User $user): Recipe
    {
        $rezept = new Recipe([
            'name' => $eintrag['name'],
            'servings' => $eintrag['portionen'] ?? 4,
            'prep_minutes' => $eintrag['vorbereitung_minuten'] ?? null,
            'cook_minutes' => $eintrag['koch_minuten'] ?? null,
            'effort' => $eintrag['aufwand'] ?? 'normal',
            'instructions' => $eintrag['zubereitung'] ?? null,
            'notes' => $eintrag['anmerkungen'] ?? null,
            'nutrition' => $eintrag['naehrwerte'] ?? null,
            'plant_points' => $eintrag['pflanzenpunkte'] ?? 0,
            'source_url' => $eintrag['quelle'] ?? null,
        ]);

        $rezept->created_by = $user?->getKey();
        $rezept->save();

        foreach ($eintrag['zutaten'] ?? [] as $sort => $zutat) {
            $this->zutat($rezept, $zutat, $sort);
        }

        foreach ($eintrag['schlagworte'] ?? [] as $schlagwort) {
            $this->schlagwort($rezept, $schlagwort);
        }

        return $rezept;
    }

    /**
     * @param  array<string, mixed>  $zutat
     */
    protected function zutat(Recipe $rezept, array $zutat, int $sort): void
    {
        $eintrag = new RecipeIngredient([
            'raw_name' => $zutat['name'],
            'amount' => $zutat['menge'] ?? null,
            'unit' => $zutat['einheit'] ?? null,
            'is_pantry' => (bool) ($zutat['vorrat'] ?? false),
            'is_optional' => (bool) ($zutat['optional'] ?? false),
            'buy_label' => $zutat['einkaufsname'] ?? null,
            'merge_key' => $zutat['sammelbegriff'] ?? null,
            'sort' => $sort,
        ]);

        // Der Artikelkatalog wird nur *verknuepft*, nicht erweitert: Ein
        // Import von zwanzig Rezepten wuerde sonst hundert Artikel anlegen,
        // die niemand geprueft hat. Ohne Treffer bleibt die Zutat Text --
        // die Uebernahme auf die Einkaufsliste kommt damit zurecht.
        $eintrag->product_id = Product::matchingName($zutat['name'])->value('id');

        $eintrag->recipe_id = $rezept->getKey();
        $eintrag->family_id = $rezept->family_id;
        $eintrag->save();
    }

    /**
     * @param  array<string, mixed>  $schlagwort
     */
    protected function schlagwort(Recipe $rezept, array $schlagwort): void
    {
        $tag = Tag::firstOrCreate(
            ['type' => $schlagwort['art'] ?? 'other', 'name' => $schlagwort['name']],
        );

        $rezept->tags()->syncWithoutDetaching([
            $tag->getKey() => ['family_id' => $rezept->family_id],
        ]);
    }

    /**
     * Die Regeln je Rezept. Sie bilden das Datenbankschema ab -- deshalb
     * stehen dieselben Grenzen hier wie im Formular.
     *
     * @return array<string, mixed>
     */
    protected function regeln(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'portionen' => ['nullable', 'integer', 'between:1,99'],
            'vorbereitung_minuten' => ['nullable', 'integer', 'between:0,9999'],
            'koch_minuten' => ['nullable', 'integer', 'between:0,9999'],
            'aufwand' => ['nullable', Rule::in(['quick', 'normal', 'elaborate'])],
            'zubereitung' => ['nullable', 'string', 'max:65535'],
            'anmerkungen' => ['nullable', 'string', 'max:5000'],
            'pflanzenpunkte' => ['nullable', 'integer', 'between:0,255'],
            'quelle' => ['nullable', 'url', 'max:500'],
            'naehrwerte' => ['nullable', 'array'],

            'zutaten' => ['required', 'array', 'min:1', 'max:80'],
            'zutaten.*.name' => ['required', 'string', 'max:200'],
            'zutaten.*.menge' => ['nullable', 'numeric', 'between:0,9999999'],
            'zutaten.*.einheit' => ['nullable', 'string', 'max:20'],
            'zutaten.*.vorrat' => ['nullable', 'boolean'],
            'zutaten.*.optional' => ['nullable', 'boolean'],
            'zutaten.*.einkaufsname' => ['nullable', 'string', 'max:160'],
            'zutaten.*.sammelbegriff' => ['nullable', 'string', 'max:60'],

            'schlagworte' => ['nullable', 'array', 'max:20'],
            'schlagworte.*.art' => ['nullable', Rule::in(array_keys(Tag::TYPEN))],
            'schlagworte.*.name' => ['required', 'string', 'max:60'],
        ];
    }

    /**
     * @param  array<int, string>  $fehler
     * @return array{rezepte: array<int, array<string, mixed>>, neu: array<int, string>, vorhanden: array<int, string>, fehler: array<int, string>}
     */
    protected function leer(array $fehler): array
    {
        return ['rezepte' => [], 'neu' => [], 'vorhanden' => [], 'fehler' => $fehler];
    }
}
