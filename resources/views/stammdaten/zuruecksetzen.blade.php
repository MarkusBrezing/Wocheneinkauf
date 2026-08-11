@extends('layouts.app')
@section('title', 'Stammdaten zurücksetzen')

@section('inhalt')
    <style>
        /*
         * Diese Seite darf aussehen wie eine Warnung. Terrakotta ist sonst dem
         * einen wichtigen Knopf vorbehalten -- hier trägt sie die ganze Seite,
         * weil hier nichts anderes wichtig ist.
         */
        .warnung { border: 2px solid var(--akzent); border-radius: 8px;
            padding: 1rem 1.2rem; background: var(--akzent-hell); margin-bottom: 1.5rem; }
        .warnung h2 { margin-top: 0; color: var(--akzent); font-size: 1.05rem; }
        .warnung ul { margin: 0.4rem 0 0; padding-left: 1.2rem; }
        .bilanz { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
            margin: 1.25rem 0; }
        .bilanz section { border: 1px solid var(--rand); border-radius: 8px; padding: 0.9rem 1rem;
            background: var(--flaeche); }
        .bilanz h3 { margin: 0 0 0.5rem; font-size: 0.95rem; }
        .bilanz table { margin: 0; }
        .bilanz td { border: none; padding: 0.15rem 0; }
        .bilanz td.zahl { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .loeschen { background: var(--akzent); border-color: var(--akzent); }
    </style>

    <p><a href="{{ route('stammdaten.produkte.index') }}">‹ Zurück zum Artikelkatalog</a></p>

    <h1>Stammdaten zurücksetzen</h1>

    <p>
        Diese Seite löscht <strong>alle Artikel, Warengruppen und Märkte</strong> der Familie
        <strong>{{ $family->name }}</strong> — nicht einzelne, sondern alle. Das lässt sich
        nicht rückgängig machen.
    </p>

    <div class="bilanz">
        <section>
            <h3>Wird gelöscht</h3>
            <table>
                @foreach ($vorschau['stammdaten'] as $was => $anzahl)
                    <tr><td>{{ $was }}</td><td class="zahl">{{ $anzahl }}</td></tr>
                @endforeach
            </table>
        </section>

        <section>
            <h3>Verschwindet mit</h3>
            <table>
                @foreach ($vorschau['mit_geloescht'] as $was => $anzahl)
                    <tr><td>{{ $was }}</td><td class="zahl">{{ $anzahl }}</td></tr>
                @endforeach
            </table>
        </section>

        <section>
            <h3>Bleibt, verliert aber Verweise</h3>
            <table>
                @foreach ($vorschau['verweise_verlieren'] as $was => $anzahl)
                    <tr><td>{{ $was }}</td><td class="zahl">{{ $anzahl }}</td></tr>
                @endforeach
            </table>
        </section>
    </div>

    {{-- Die eine Folge, die man nicht erwartet, gehört groß auf die Seite und
         nicht in eine Fußnote. --}}
    <div class="warnung">
        <h2>Das sollten Sie vorher wissen</h2>
        <ul>
            <li>
                <strong>Der Vorrat geht mit.</strong> Vorratspositionen hängen an Artikeln —
                mit den Artikeln verschwinden sie samt ihren Buchungen.
                @if ($vorschau['mit_geloescht']['Vorratspositionen'] > 0)
                    Betroffen sind {{ $vorschau['mit_geloescht']['Vorratspositionen'] }} Positionen.
                @endif
            </li>
            <li>
                <strong>Einkaufslisten und Rezepte bleiben.</strong> Ihre Einträge behalten Text
                und Menge, verlieren aber die Zuordnung zu Artikel und Warengruppe — auf der
                Liste stehen sie danach unter „Ohne Warengruppe".
            </li>
            <li>
                <strong>Die Stichwortregeln gehen mit.</strong> Neue Artikel werden danach nicht
                mehr automatisch einsortiert, bis wieder Regeln da sind.
            </li>
            <li>
                <strong>Andere Familien sind nicht betroffen.</strong> Gelöscht wird
                ausschließlich, was {{ $family->name }} gehört.
            </li>
        </ul>
    </div>

    <h2>Bestätigung</h2>

    <form method="POST" action="{{ route('stammdaten.zuruecksetzen.destroy') }}">
        @csrf
        @method('DELETE')

        <label for="bestaetigung">
            Tippen Sie zur Bestätigung den Namen der Familie ab: <code>{{ $family->name }}</code>
        </label>
        <input id="bestaetigung" name="bestaetigung" type="text" required autocomplete="off"
               placeholder="{{ $family->name }}" style="max-width:24rem"
               @error('bestaetigung') aria-invalid="true" @enderror>

        {{-- Ohne Startdatensatz stünde die Familie danach vor einer Anwendung
             ohne Warengruppen und ohne Markt -- funktionsfähig, aber unbenutzbar.
             Deshalb der Weg zurück gleich mit angeboten. --}}
        <label style="font-weight:400;margin-top:1rem">
            <input type="checkbox" name="startdatensatz" value="1" style="width:auto">
            Danach den Startdatensatz neu einspielen (15 Warengruppen, rund 90 Stichwortregeln,
            Markt „Supermarkt" mit 10 Regalbereichen)
        </label>

        <div style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;margin-top:1rem">
            <button type="submit" class="loeschen"
                    onclick="return confirm('Wirklich alle Stammdaten von {{ $family->name }} löschen? Das lässt sich nicht rückgängig machen.')">
                Alle Stammdaten löschen
            </button>
            <a href="{{ route('stammdaten.produkte.index') }}" class="knopf knopf-schlicht">Abbrechen</a>
        </div>
    </form>
@endsection
