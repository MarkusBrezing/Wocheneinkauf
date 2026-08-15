<?php

namespace App\Http\Controllers;

use App\Modules\Recipes\Services\RecipeImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rezepte aus einer Datei übernehmen (D-09).
 *
 * Drei Schritte statt einem: Prompt und Einfügen, dann die Vorschau mit den
 * Befunden, dann das Anlegen. Text aus einer Sprach-KI ist der Fall, in dem
 * eine Vorschau am meisten wert ist -- man sieht dort zum ersten Mal, was die
 * Maschine tatsächlich geschrieben hat.
 */
class RecipeImportController extends Controller
{
    /** Wo der geprüfte Stand zwischen Vorschau und Bestätigung liegt. */
    protected const ORDNER = 'rezept-import';

    public function __construct(protected RecipeImporter $importer) {}

    public function show(Request $request)
    {
        $this->currentFamily($request);

        return view('rezepte.import', ['vorschau' => null]);
    }

    /**
     * Eingefügten Text prüfen -- ohne etwas anzulegen.
     *
     * Eingefügt statt hochgeladen: Die Antwort der KI steht ohnehin schon im
     * Zwischenspeicher. Der Umweg über eine Datei -- speichern, wiederfinden,
     * auswählen -- kostet drei Schritte und bringt nichts.
     */
    public function preview(Request $request)
    {
        $family = $this->currentFamily($request);

        $request->validate([
            'inhalt' => ['required', 'string', 'max:2000000'],
        ], [
            'inhalt.required' => 'Bitte fügen Sie die Antwort der KI in das Feld ein.',
            'inhalt.max' => 'Der eingefügte Text ist zu lang (höchstens 2 MB).',
        ]);

        $vorschau = $this->importer->pruefen($request->string('inhalt')->toString());

        // Die geprüften Rezepte wandern in eine Datei statt in die Sitzung:
        // Zweihundert Rezepte samt Zutaten sind nichts, was in ein
        // Sitzungscookie oder eine Sitzungszeile gehört.
        $kennung = null;

        if ($vorschau['rezepte'] !== []) {
            $kennung = (string) Str::ulid();

            Storage::put(
                $this->pfad($family->getKey(), $kennung),
                json_encode($vorschau['rezepte'], JSON_UNESCAPED_UNICODE),
            );
        }

        return view('rezepte.import', [
            'vorschau' => $vorschau,
            'kennung' => $kennung,
        ]);
    }

    /**
     * Die geprüften Rezepte anlegen.
     */
    public function store(Request $request)
    {
        $family = $this->currentFamily($request);

        $request->validate(['kennung' => ['required', 'string', 'max:40', 'regex:/^[0-9A-Za-z]+$/']]);

        // Der Pfad enthält die Familie -- eine fremde Kennung trifft damit
        // selbst dann ins Leere, wenn jemand sie erraten hat.
        $pfad = $this->pfad($family->getKey(), $request->string('kennung')->toString());

        if (! Storage::exists($pfad)) {
            return redirect()->route('rezepte.import')
                ->withErrors(['inhalt' => 'Der geprüfte Stand ist nicht mehr da. Bitte noch einmal einfügen.']);
        }

        $rezepte = json_decode(Storage::get($pfad), true) ?: [];
        $angelegt = $this->importer->importieren($rezepte, $request->user());

        Storage::delete($pfad);

        return redirect()->route('rezepte.index')->with(
            'status',
            $angelegt === 0
                ? 'Es wurde nichts angelegt — die Rezepte gab es bereits.'
                : $angelegt.' '.($angelegt === 1 ? 'Rezept' : 'Rezepte').' übernommen.'
        );
    }

    protected function pfad(int $familyId, string $kennung): string
    {
        return self::ORDNER.'/'.$familyId.'-'.$kennung.'.json';
    }
}
