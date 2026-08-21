@extends('layouts.app')
@section('title', 'Rezepte importieren')
@section('koerper', 'weit')

@section('inhalt')
    <style>
        .prompt { position: relative; }
        .prompt pre { background: var(--flaeche); border: 1px solid var(--rand); border-radius: 8px;
            padding: 1rem 1.1rem; overflow-x: auto; font-size: 0.82rem; line-height: 1.5;
            white-space: pre-wrap; word-break: break-word; max-height: 26rem; }
        .prompt .kopieren { position: absolute; top: 0.6rem; right: 0.6rem; margin: 0;
            padding: 0.25rem 0.7rem; font-size: 0.85rem; }
        .einfuegefeld { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: 0.82rem; line-height: 1.45; white-space: pre; overflow-wrap: normal;
            overflow-x: auto; }
        .befund { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
            margin: 1.25rem 0; }
        .befund section { border: 1px solid var(--rand); border-radius: 8px; padding: 0.9rem 1rem;
            background: var(--flaeche); }
        .befund h3 { margin: 0 0 0.4rem; font-size: 0.95rem; }
        .befund .gross { font-size: 2rem; font-weight: 600; line-height: 1.1; display: block;
            color: var(--primaer); }
        .befund .warnung .gross { color: var(--akzent); }
        .befund ul { margin: 0.4rem 0 0; padding-left: 1.1rem; font-size: 0.88rem;
            color: var(--gedaempft); }
        .rezeptliste td { vertical-align: top; }
        .rezeptliste .zutaten { font-size: 0.85rem; color: var(--gedaempft); }

        /*
         * Der eigentliche Auftrag -- der Satz, den man selbst schreibt.
         * Er stand mitten in einem grauen Absatz und ging darin unter, dabei
         * ist er das Einzige auf dieser Seite, das vom Anwender kommt.
         * Terrakotta ist laut Palette die Farbe für das, was auffallen soll.
         */
        .auftragsbeispiel { margin: 0.5rem 0 1.25rem; padding: 0.7rem 1rem;
            border-left: 4px solid var(--akzent); background: var(--akzent-hell);
            border-radius: 0 8px 8px 0; color: var(--text); font-size: 1.05rem; }
        .auftragsbeispiel .wozu { display: block; font-size: 0.8rem; color: var(--gedaempft);
            margin-bottom: 0.15rem; }
    </style>

    <p><a href="{{ route('rezepte.index') }}">‹ Zurück zu den Rezepten</a></p>

    <h1>Rezepte importieren</h1>

    @if ($vorschau === null)
        <p>
            Rezepte lassen sich als <strong>JSON</strong> übernehmen. Der Weg, für den das
            gedacht ist: Sie lassen sich die Rezepte von einer Sprach-KI in genau dieses
            Format schreiben, kopieren die Antwort und fügen sie unten ein.
        </p>

        <h2>Schritt 1 · Den Auftrag an die KI kopieren</h2>
        <p class="gedaempft">
            Der Text unten beschreibt das Format so genau, dass das Ergebnis ohne Nacharbeit
            hierher passt. Hängen Sie am Ende an, welche Rezepte Sie möchten:
        </p>

        <p class="auftragsbeispiel">
            <span class="wozu">Zum Beispiel</span>
            zehn schnelle Abendessen für vier Personen
        </p>

        <div class="prompt">
            <button type="button" class="knopf-schlicht kopieren"
                    onclick="navigator.clipboard.writeText(document.getElementById('prompt').textContent).then(() => this.textContent = 'Kopiert')">
                Kopieren
            </button>
            <pre id="prompt">@include('rezepte.partials.import-prompt')</pre>
        </div>

        <h2>Schritt 2 · Die Antwort einfügen</h2>
        <p class="gedaempft">
            Antwort der KI markieren, kopieren, hier einfügen. Höchstens
            {{ \App\Modules\Recipes\Services\RecipeImporter::MAX_REZEPTE }} Rezepte auf einmal.
            Angelegt wird noch nichts — die nächste Seite zeigt zuerst, was drinsteht.
        </p>

        <form method="POST" action="{{ route('rezepte.import.vorschau') }}">
            @csrf
            <label for="inhalt">JSON aus der KI</label>
            <textarea id="inhalt" name="inhalt" rows="14" required spellcheck="false"
                      class="einfuegefeld"
                      placeholder='&#123; "version": 1, "rezepte": [ … ] &#125;'>{{ old('inhalt') }}</textarea>
            <button type="submit">Eingabe prüfen</button>
        </form>
    @else
        <h2>Befund</h2>

        <div class="befund">
            <section>
                <h3>Wird angelegt</h3>
                <span class="gross">{{ count($vorschau['neu']) }}</span>
                <span class="gedaempft">Rezepte</span>
            </section>

            <section>
                <h3>Übersprungen</h3>
                <span class="gross">{{ count($vorschau['vorhanden']) }}</span>
                <span class="gedaempft">gibt es schon</span>
                @if ($vorschau['vorhanden'] !== [])
                    <ul>
                        @foreach ($vorschau['vorhanden'] as $name)
                            <li>{{ $name }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section @class(['warnung' => $vorschau['fehler'] !== []])>
                <h3>Nicht verwendbar</h3>
                <span class="gross">{{ count($vorschau['fehler']) }}</span>
                <span class="gedaempft">Einträge</span>
                @if ($vorschau['fehler'] !== [])
                    <ul>
                        @foreach ($vorschau['fehler'] as $fehler)
                            <li>{{ $fehler }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        @if ($vorschau['rezepte'] !== [])
            {{-- Wer eine Maschine schreiben lässt, sollte vor dem Anlegen sehen,
                 was sie geschrieben hat. --}}
            <div class="tabellenrahmen">
                <table class="stammtabelle rezeptliste">
                    <thead>
                        <tr>
                            <th>Rezept</th>
                            <th class="zahl">Portionen</th>
                            <th class="zahl">Zeit</th>
                            <th>Zutaten</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vorschau['rezepte'] as $rezept)
                            <tr>
                                <td>
                                    <strong>{{ $rezept['name'] }}</strong>
                                    @foreach ($rezept['schlagworte'] ?? [] as $schlagwort)
                                        <span class="marke-geraet">{{ $schlagwort['name'] }}</span>
                                    @endforeach
                                </td>
                                <td class="zahl">{{ $rezept['portionen'] ?? 4 }}</td>
                                <td class="zahl">
                                    {{ ($rezept['vorbereitung_minuten'] ?? 0) + ($rezept['koch_minuten'] ?? 0) ?: '—' }} min
                                </td>
                                <td class="zutaten">
                                    {{ collect($rezept['zutaten'])->pluck('name')->implode(', ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="hinweis" style="margin-top:1rem">
                Zutaten werden mit dem Artikelkatalog <strong>verknüpft, wo es einen Treffer gibt</strong> —
                neue Artikel legt der Import nicht an. Ohne Treffer bleibt die Zutat Text; auf der
                Einkaufsliste landet sie trotzdem.
            </div>

            <form method="POST" action="{{ route('rezepte.import.store') }}"
                  style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;margin-top:1rem">
                @csrf
                <input type="hidden" name="kennung" value="{{ $kennung }}">
                <button type="submit">{{ count($vorschau['neu']) }} Rezepte anlegen</button>
                <a href="{{ route('rezepte.import') }}" class="knopf knopf-schlicht">Neue Eingabe</a>
            </form>
        @else
            <div class="hinweis">
                <strong>Es bleibt nichts zu übernehmen.</strong>
                Bessern Sie die Eingabe nach oder lassen Sie sie neu erzeugen.
            </div>
            <p><a href="{{ route('rezepte.import') }}" class="knopf">Neue Eingabe</a></p>
        @endif
    @endif
@endsection
