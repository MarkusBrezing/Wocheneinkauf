<?php

namespace App\Http\Controllers;

use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use App\Modules\ShoppingList\Services\ItemTextParser;
use App\Modules\ShoppingList\Services\ListSnapshot;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Geraeteuebergreifender Abgleich der Einkaufsliste (C-07, C-08).
 *
 * Drei Zusagen an den Client:
 *
 *  1. Er bekommt auf Wunsch nur die Aenderungen seit seiner letzten Abfrage.
 *  2. Sendet er auf Basis eines veralteten Standes, antwortet der Server mit
 *     409 und dem aktuellen Stand -- er ueberschreibt nichts blind.
 *  3. Jede Mutation traegt eine client_uuid und darf beliebig oft wiederholt
 *     werden. Genau das passiert nach einem Verbindungsabbruch.
 *
 * Die Endpunkte liegen bewusst hinter der normalen Sitzung mit CSRF-Schutz --
 * das Frontend laeuft auf derselben Domain, ein zweites Token-Verfahren waere
 * zusaetzliche Angriffsflaeche ohne Gewinn. Das CSRF-Token steht im
 * Meta-Element des Layouts bereit.
 */
class ShoppingListSyncController extends Controller
{
    public function __construct(
        protected ShoppingListManager $manager,
        protected ItemTextParser $parser,
        protected ListSnapshot $snapshot,
    ) {}

    /**
     * Stand abholen -- vollstaendig oder als Delta.
     */
    public function show(Request $request): JsonResponse
    {
        $this->currentFamily($request);

        $request->validate(['seit' => ['nullable', 'date']]);

        $liste = $this->manager->current($request->user());

        return response()->json($this->snapshot->fuer($liste, $request->date('seit')));
    }

    /**
     * Der kleine Abgleich fuer die Einkaufsansicht.
     *
     * Beim Einkaufen traegt oft jemand von zu Hause noch etwas nach. Die
     * Einkaufsansicht fragt deshalb im Hintergrund nach, ob sich etwas getan
     * hat -- und braucht dafuer genau drei Zahlen, nicht die ganze Liste.
     *
     * Bewusst getrennt von show(): Der vollstaendige Stand laedt jeden Eintrag
     * samt Artikel und Maerkten nach. Alle 25 Sekunden ueber eine
     * Mobilfunkverbindung waere das Verschwendung an einer Stelle, an der die
     * Verbindung ohnehin schlecht ist.
     *
     * Die Version zaehlen die Model-Hooks der Eintraege hoch -- jedes Anlegen,
     * Aendern und Loeschen schlaegt hier durch.
     */
    public function puls(Request $request): JsonResponse
    {
        $this->currentFamily($request);

        $liste = $this->manager->current($request->user());

        $offen = $liste->items()->offen()->count();
        $erledigt = $liste->items()->where('is_checked', true)->count();

        return response()->json([
            'version' => $liste->version,
            'offen' => $offen,
            'erledigt' => $erledigt,
            'gesamt' => $offen + $erledigt,
        ]);
    }

    /**
     * Aenderungen einspielen.
     */
    public function store(Request $request): JsonResponse
    {
        $this->currentFamily($request);

        $daten = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'liste_id' => ['nullable', 'string', 'size:26'],
            'aenderungen' => ['required', 'array', 'max:200'],
            'aenderungen.*.typ' => ['required', Rule::in(['hinzufuegen', 'bearbeiten', 'abhaken', 'loeschen'])],
            'aenderungen.*.uuid' => ['required', 'uuid'],
            'aenderungen.*.text' => ['required_if:aenderungen.*.typ,hinzufuegen,bearbeiten', 'nullable', 'string', 'max:200'],
            'aenderungen.*.notiz' => ['nullable', 'string', 'max:160'],
            'aenderungen.*.warengruppe_id' => ['nullable', 'integer'],
            'aenderungen.*.abgehakt' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($request, $daten) {
            // Gesperrt geladen: sonst koennten zwei gleichzeitige Batches beide
            // die Versionspruefung bestehen und sich gegenseitig ueberschreiben
            // -- der Fall, fuer den die Spalte ueberhaupt existiert.
            $liste = ShoppingList::active()->lockForUpdate()->first()
                ?? $this->manager->current($request->user());

            $listeGewechselt = isset($daten['liste_id']) && $daten['liste_id'] !== $liste->public_id;

            if ($listeGewechselt || (int) $daten['version'] !== $liste->version) {
                return response()->json([
                    'fehler' => $listeGewechselt ? 'liste_gewechselt' : 'version_konflikt',
                    'gesendete_version' => (int) $daten['version'],
                ] + $this->snapshot->fuer($liste), 409);
            }

            $angewendet = 0;
            $ignoriert = [];

            foreach ($daten['aenderungen'] as $aenderung) {
                if ($this->anwenden($request, $liste, $aenderung)) {
                    $angewendet++;

                    continue;
                }

                $ignoriert[] = $aenderung['uuid'];
            }

            return response()->json([
                'angewendet' => $angewendet,
                'ignoriert' => $ignoriert,
            ] + $this->snapshot->fuer($liste->refresh()));
        });
    }

    /**
     * @param  array<string, mixed>  $aenderung
     * @return bool false, wenn die Mutation ins Leere lief oder schon
     *              verarbeitet war -- beides ist kein Fehler, nur nichts zu tun
     */
    protected function anwenden(Request $request, ShoppingList $liste, array $aenderung): bool
    {
        if ($aenderung['typ'] === 'hinzufuegen') {
            return $this->manager->addFromClient(
                liste: $liste,
                clientUuid: $aenderung['uuid'],
                eingabe: $this->parser->parse($aenderung['text'])->mitNotiz($aenderung['notiz'] ?? null),
                productGroupId: $aenderung['warengruppe_id'] ?? null,
            ) !== null;
        }

        $item = $this->eintrag($liste, $aenderung['uuid']);

        if ($item === null) {
            return false;
        }

        switch ($aenderung['typ']) {
            case 'bearbeiten':
                $this->manager->update(
                    $item,
                    $this->parser->parse($aenderung['text'])->mitNotiz($aenderung['notiz'] ?? null),
                    $aenderung['warengruppe_id'] ?? null,
                );

                return true;

            case 'abhaken':
                $this->manager->toggle($item, (bool) ($aenderung['abgehakt'] ?? true), $request->user());

                return true;

            case 'loeschen':
                $this->manager->remove($item);

                return true;
        }

        return false;
    }

    protected function eintrag(ShoppingList $liste, string $uuid): ?ShoppingListItem
    {
        return $liste->items()->where('client_uuid', $uuid)->first();
    }
}
