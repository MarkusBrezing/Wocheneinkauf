@extends('layouts.app')
@section('title', 'Einkaufsliste')

@section('inhalt')
    <style>
        /* Die Abschnittsköpfe tragen die Laufreihenfolge durch den Markt --
           sie dürfen die kräftigste Farbe der Seite haben. */
        .abschnitt-block { margin-top: 1.25rem; }
        .abschnitt-block > summary { cursor: pointer; display: flex; align-items: baseline;
            gap: 0.6rem; padding: 0.25rem 0 0.2rem; color: var(--primaer);
            font-size: 1.15rem; font-weight: 600;
            border-bottom: 1px solid var(--rand); }
        .abschnitt-block > summary:hover { border-bottom-color: var(--primaer); }
        .abschnitt-block .stand { margin-left: auto; font-size: 0.85rem; font-weight: 400;
            color: var(--gedaempft); }
        /* Ein abgeräumter Abschnitt tritt zurück -- er steht zugeklappt da und
           soll den Blick nicht mehr auf sich ziehen. */
        .abschnitt-block:not([open]) > summary { color: var(--gedaempft);
            border-bottom-style: dashed; }
        /* Was es hier nicht gibt, ist keine Warengruppe, sondern ein Hinweis.
           Terrakotta trennt das sichtbar vom Rest der Liste. */
        .block-anderswo > summary { color: var(--akzent); border-bottom-color: var(--akzent); }
        .liste tbody:nth-of-type(even) tr { background: var(--flaeche); }
        .liste .erledigt { color: var(--gedaempft); text-decoration: line-through; }
        .haken { accent-color: var(--primaer); }

        /*
         * Kompakte Zeilen: Eine Einkaufsliste wird überflogen, nicht gelesen.
         * Je mehr Artikel ohne Scrollen sichtbar sind, desto weniger muss man
         * suchen. Deshalb enge Zellen, eine Zeile je Eintrag -- und alles,
         * was nur manchmal gebraucht wird, ausgeklappt statt danebengestellt.
         */
        .liste td { padding: 0.18rem 0.4rem; vertical-align: baseline; }
        .liste tr:hover td { background: var(--primaer-hell); }
        .liste .sp-haken { width: 2.2rem; padding-right: 0; }
        /* Mengen rechtsbündig: So stehen die Zahlen untereinander und lassen
           sich im Vorbeigehen vergleichen. */
        .liste .sp-menge { width: 5.5rem; text-align: right; white-space: nowrap;
            font-variant-numeric: tabular-nums; }
        .liste .sp-name { width: auto; }
        /* Der Bearbeiten-Griff sitzt am rechten Rand -- weit weg vom Haken,
           der beim Einkaufen tausendmal häufiger getroffen wird. */
        .liste .sp-werkzeug { width: 6rem; text-align: right; white-space: nowrap; }
        .liste .sp-werkzeug summary { list-style: none; }
        .liste .sp-werkzeug summary::-webkit-details-marker { display: none; }
        .liste .griff { cursor: pointer; font-size: 0.82rem; color: var(--gedaempft);
            padding: 0.1rem 0.4rem; border: 1px solid transparent; border-radius: 5px; }
        .liste .griff:hover { border-color: var(--rand); color: var(--primaer); }
        .liste .zusatz { font-size: 0.8rem; }
        .liste .werkzeugzeile td { padding-top: 0; }

        /*
         * Die Bearbeitungszeile hängt am <details> der Zeile darüber. CSS kann
         * nicht rückwärts schauen, wohl aber vorwärts -- deshalb :has() am
         * Eintrag und der Nachbarselektor auf die Werkzeugzeile.
         *
         * Der @supports-Rahmen ist der Rückfallweg: Wo :has() fehlt, bleiben
         * die Formulare sichtbar. Ausgeklappt ist unschön, unerreichbar wäre
         * ein Fehler.
         */
        @supports selector(tr:has(details[open])) {
            .liste .werkzeugzeile { display: none; }
            .liste tr.eintrag:has(details[open]) + .werkzeugzeile { display: table-row; }
        }
    </style>

    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <h1 style="margin-bottom:0">{{ $liste->anzeigename() }}</h1>
        {{-- Der Weg in den Laden: eine Ansicht, die nur abhaken kann. --}}
        <a href="{{ route('einkaufsliste.einkaufen') }}" class="knopf" style="margin:0">Einkaufen</a>
    </div>

    <p class="gedaempft">
        {{ $offen }} offen, {{ $erledigt }} erledigt ·
        @if ($liste->market)
            sortiert nach {{ $liste->market->name }}
        @else
            sortiert nach Warengruppen
        @endif
        · <a href="{{ route('einkaufsliste.drucken') }}">Drucken oder teilen</a>
        · <a href="{{ route('einkaufsliste.inventur') }}">Bestandsaufnahme</a>
    </p>

    {{--
        Artikel hinzufuegen (C-01, C-17).

        Die Reihenfolge ist Absicht: erst die Warengruppe, dann die Menge, dann
        der Artikel. Die Warengruppe steht vorn, weil sie die Vorschläge im
        Namensfeld einschränkt -- wer "Molkereiprodukte" wählt, sucht danach
        nicht mehr im ganzen Katalog. Menge und Name sind getrennte Felder:
        Wer eine Zahl tippt, soll nicht im selben Feld nach dem Namen suchen.
    --}}
    <form method="POST" action="{{ route('einkaufsliste.eintraege.store') }}"
          style="display:flex;gap:0.4rem;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div>
            <label for="product_group_id">Warengruppe</label>
            <select id="product_group_id" name="product_group_id" style="margin:0;width:auto">
                <option value="">— automatisch —</option>
                @foreach ($gruppen as $gruppe)
                    <option value="{{ $gruppe->id }}">{{ $gruppe->label }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:0 0 6rem">
            <label for="menge">Menge</label>
            <input id="menge" name="menge" type="text" value="{{ old('menge') }}"
                   placeholder="2 l" style="margin:0" autocomplete="off">
        </div>
        <div style="flex:1 1 14rem">
            <label for="text">Artikel</label>
            <input id="text" name="text" type="text" value="{{ old('text') }}" required autofocus
                   placeholder="Milch" style="margin:0"
                   list="artikel-vorschlaege" data-gruppe="#product_group_id" autocomplete="off">
        </div>
        <button type="submit" style="margin:0">Auf die Liste</button>
    </form>

    <p class="gedaempft" style="margin-top:0.4rem">
        In die Menge gehört alles Zahlenhafte: <code>500g</code>, <code>1-2</code>, <code>2 l</code>.
        Ein Artikel, der schon offen auf der Liste steht, wird nicht doppelt eingetragen —
        die Mengen werden addiert.
        @if ($artikelnamen->isNotEmpty())
            Beim Tippen schlägt der <a href="{{ route('stammdaten.produkte.index') }}">Artikelkatalog</a> vor;
            mit gewählter Warengruppe nur deren Artikel.
        @endif
    </p>

    {{-- Die Vorschlagsliste für alle Eingabefelder dieser Seite --}}
    <x-artikel-vorschlaege :artikel="$artikelnamen" />

    {{-- Die Liste selbst (C-06, C-16).

         Jeder Abschnitt ist klappbar und steht offen: Eine Einkaufsliste, die
         ihre Artikel versteckt, hat ihren Zweck verfehlt. Zuklappen ist für
         den Fall gedacht, dass man gerade nur einen Bereich bearbeitet --
         nicht für den ersten Blick.

         Was durchgehend abgehakt ist, klappt von selbst zu. Es steht ja
         schon im Wagen. --}}
    @forelse ($abschnitte as $abschnitt)
        @php($erledigt = $abschnitt->offen() === 0)
        <details @class(['abschnitt-block', 'block-anderswo' => $abschnitt->name === \App\Modules\ShoppingList\Services\ListSorter::ANDERSWO])
                 {{ $erledigt ? '' : 'open' }}>
            <summary>
                <span class="name">{{ $abschnitt->name }}</span>
                <span class="stand">
                    @if ($erledigt)
                        alles erledigt
                    @else
                        {{ $abschnitt->offen() }} offen
                    @endif
                </span>
            </summary>

        <div class="tabellenrahmen">
            <table class="liste">
                {{--
                    Ein tbody je Eintrag statt eines gemeinsamen: Die
                    Bearbeitungszeile gehört zu ihrem Artikel, und die
                    Zebrastreifen zählen dadurch Artikel statt Zeilen.
                --}}
                @foreach ($abschnitt->items as $item)
                    <tbody>
                        <tr class="eintrag">
                            <td class="sp-haken">
                                <form method="POST" action="{{ route('einkaufsliste.eintraege.haken', $item) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="knopf-schlicht"
                                            style="margin:0;padding:0 0.35rem;font-size:1.05rem;line-height:1.4"
                                            title="{{ $item->is_checked ? 'Haken entfernen' : 'Abhaken' }}"
                                            aria-label="{{ $item->is_checked ? 'Haken entfernen' : 'Abhaken' }}">
                                        {{ $item->is_checked ? '☑' : '☐' }}
                                    </button>
                                </form>
                            </td>

                            {{-- Menge in eigener Spalte: rechtsbündig stehen die
                                 Zahlen untereinander und lassen sich vergleichen. --}}
                            <td class="sp-menge">
                                <span @class(['erledigt' => $item->is_checked])><strong>{{ $item->mengeAnzeige() }}</strong></span>
                            </td>

                            <td class="sp-name">
                                <span @class(['erledigt' => $item->is_checked])>{{ $item->raw_name }}</span>
                                @if ($item->note)
                                    <span class="gedaempft zusatz">— {{ $item->note }}</span>
                                @endif

                                {{-- Wo es den Artikel gibt, falls eingeschränkt --}}
                                @if ($item->verfuegbarkeitshinweis())
                                    <span class="gedaempft zusatz">· {{ $item->verfuegbarkeitshinweis() }}</span>
                                @endif

                                {{-- C-09: wer hat abgehakt --}}
                                @if ($item->is_checked && $item->checkedBy)
                                    <span class="gedaempft zusatz">
                                        · {{ $item->checkedBy->name }}@if ($item->checked_at), {{ $item->checked_at->format('d.m. H:i') }}@endif
                                    </span>
                                @endif
                            </td>

                            {{-- Der Griff sitzt am rechten Rand, weit weg vom
                                 Haken -- der wird tausendmal häufiger getroffen. --}}
                            <td class="sp-werkzeug">
                                <details>
                                    <summary class="griff">bearbeiten</summary>
                                </details>
                            </td>
                        </tr>

                        {{-- Bearbeiten (C-03, C-05) und Löschen (C-04). Eigene
                             Zeile über die volle Breite: In der schmalen
                             Werkzeugspalte wäre das Formular unbenutzbar. --}}
                        <tr class="werkzeugzeile">
                            <td colspan="4">
                                <form method="POST" action="{{ route('einkaufsliste.eintraege.update', $item) }}"
                                      style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;margin:0.2rem 0 0.4rem">
                                    @csrf
                                    @method('PUT')
                                    <select name="product_group_id" style="margin:0;width:auto" aria-label="Warengruppe"
                                            id="g{{ $item->getKey() }}">
                                        <option value="">— ohne —</option>
                                        @foreach ($gruppen as $gruppe)
                                            <option value="{{ $gruppe->id }}" @selected($item->product_group_id === $gruppe->id)>
                                                {{ $gruppe->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input name="menge" type="text" value="{{ $item->mengeAnzeige() }}"
                                           style="margin:0;width:5.5rem" aria-label="Menge" placeholder="Menge"
                                           autocomplete="off">
                                    <input name="text" type="text" value="{{ $item->raw_name }}" required
                                           style="margin:0;width:auto;flex:1 1 10rem" aria-label="Artikel"
                                           list="artikel-vorschlaege" data-gruppe="#g{{ $item->getKey() }}"
                                           autocomplete="off">
                                    <input name="note" type="text" value="{{ $item->note }}" placeholder="Notiz"
                                           style="margin:0;width:auto" aria-label="Notiz">
                                    <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.3rem 0.7rem">Speichern</button>
                                    <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.3rem 0.7rem"
                                            form="loeschen{{ $item->getKey() }}"
                                            onclick="return confirm('„{{ $item->raw_name }}“ von der Liste nehmen?')">Löschen</button>
                                </form>

                                {{-- Getrenntes Formular, damit der Löschknopf in
                                     derselben Zeile stehen kann, ohne im
                                     Bearbeitungsformular zu liegen. --}}
                                <form method="POST" action="{{ route('einkaufsliste.eintraege.destroy', $item) }}"
                                      id="loeschen{{ $item->getKey() }}">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
        </details>
    @empty
        <p class="gedaempft" style="margin-top:1.5rem">
            Die Liste ist leer. Schreib oben etwas auf, oder starte mit dem
            <a href="{{ route('einkaufsliste.neue-woche') }}">Assistenten „Neue Woche"</a>.
        </p>
    @endforelse

    @if ($vorlagen->isNotEmpty())
        <h2>Vorlage übernehmen</h2>
        <p class="gedaempft">
            Was schon auf der Liste steht, wird zusammengeführt statt verdoppelt.
            Verwaltet werden Vorlagen unter <a href="{{ route('vorlagen.index') }}">Vorlagen</a>.
        </p>
        <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
            @foreach ($vorlagen as $vorlage)
                <form method="POST" action="{{ route('vorlagen.anwenden', $vorlage) }}" class="inline">
                    @csrf
                    <button type="submit" class="knopf-schlicht" style="margin:0">
                        {{ $vorlage->name }}@if ($vorlage->is_default) ★ @endif
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <h2>Markt</h2>
    <form method="POST" action="{{ route('einkaufsliste.markt') }}"
          style="display:flex;gap:0.4rem;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div>
            <label for="market_id">Sortierung nach Laufreihenfolge</label>
            <select id="market_id" name="market_id" style="margin:0;width:auto">
                <option value="">— nach Warengruppen —</option>
                @foreach ($maerkte as $markt)
                    <option value="{{ $markt->id }}" @selected($liste->market_id === $markt->id)>{{ $markt->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="knopf-schlicht" style="margin:0">Umschalten</button>
    </form>

    <h2>Einkauf abschließen</h2>
    <p class="gedaempft">
        Die Liste wandert ins <a href="{{ route('einkaufsliste.archiv.index') }}">Archiv</a>.
        Was nicht abgehakt ist, hast du nicht bekommen — diese Artikel schreibt
        der Abschluss auf die neue Liste.
    </p>
    <form method="POST" action="{{ route('einkaufsliste.archivieren') }}"
          onsubmit="return confirm('Einkauf archivieren?')">
        @csrf
        <label style="font-weight:400">
            <input type="checkbox" name="offene_uebernehmen" value="1" checked style="width:auto">
            Fehlende Artikel ({{ $offen }}) in die neue Liste übernehmen
        </label>
        <button type="submit">Archivieren</button>
        <a href="{{ route('einkaufsliste.neue-woche') }}" class="knopf knopf-schlicht">Assistent „Neue Woche"</a>
    </form>
@endsection
