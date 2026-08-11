@extends('layouts.app')
@section('title', 'Artikel')

@section('inhalt')
    @include('partials.stammdaten-schritte')

    {{--
        Diese Seite wird am Rechner gepflegt, nicht im Supermarkt. Deshalb
        bricht sie aus der Lesebreite des Layouts aus und arbeitet mit echten
        Tabellenspalten: Beim Durchsehen von hundert Artikeln zählt, dass
        Einheit unter Einheit und Warengruppe unter Warengruppe steht.
    --}}
    <style>
        body { max-width: 78rem; }

        .katalog { font-size: 0.9rem; }
        .katalog th, .katalog td { padding: 0.3rem 0.5rem; vertical-align: middle; }
        /* Der Kopf bleibt beim Scrollen stehen -- ohne ihn ist ab Zeile 30
           nicht mehr erkennbar, welche Spalte welche ist. */
        .katalog thead th {
            position: sticky; top: 0; z-index: 1;
            background: var(--flaeche); text-align: left;
            border-bottom: 2px solid var(--primaer); white-space: nowrap;
        }
        /* Wechselnde Zeilen: Bei sechs Spalten und hundert Zeilen ist das
           der Unterschied zwischen Lesen und Verrutschen. Das Beige der
           Palette ist dafür hell genug, um die Eingabefelder nicht zu
           stören. */
        .katalog tbody tr:nth-child(even) { background: var(--flaeche); }
        .katalog tbody tr:hover { background: var(--primaer-hell); }
        .katalog tbody tr:focus-within { background: var(--primaer-hell); }
        .katalog input[type=text], .katalog select { margin: 0; padding: 0.25rem 0.4rem; width: 100%;
            background: var(--hintergrund); }
        .katalog input[type=checkbox], .katalog input[type=radio] { width: auto; margin: 0;
            accent-color: var(--primaer); }
        .katalog .schmal { width: 6rem; }
        .katalog .mittel { width: 11rem; }
        .katalog .knopfspalte { white-space: nowrap; width: 1%; }
        .katalog button { margin: 0; padding: 0.2rem 0.55rem; font-size: 0.85rem; }
        /* Der Grundvorrat ist eine Eigenschaft, keine Handlung -- deshalb
           trägt die Spalte den warmen Akzent statt des Handlungsgrüns. */
        .katalog .vorrat-an { accent-color: var(--akzent); }

        .filter { display: flex; gap: 0.6rem; align-items: flex-end; flex-wrap: wrap;
                  padding: 0.7rem 0.9rem; border: 1px solid var(--rand); border-radius: 6px;
                  background: var(--flaeche); }
        .filter label { margin: 0; font-size: 0.85rem; }
        .filter input, .filter select { margin: 0.15rem 0 0; padding: 0.3rem 0.4rem; }
        .filter .kaestchen { font-weight: 400; align-self: center; }
        .filter input[type=checkbox] { width: auto; accent-color: var(--primaer); }

        .verfuegbarkeit summary { cursor: pointer; font-size: 0.85rem; color: var(--gedaempft); }
        .verfuegbarkeit[open] summary { color: var(--akzent); font-weight: 600; }
        .verfuegbarkeit .auswahl { position: absolute; z-index: 2; margin-top: 0.3rem;
            padding: 0.6rem 0.8rem; border: 1px solid var(--rand); border-radius: 6px;
            background: var(--hintergrund); box-shadow: 0 2px 8px rgba(28,29,27,0.18); min-width: 14rem; }
        .verfuegbarkeit .auswahl label { display: block; font-weight: 400; margin: 0.15rem 0; }
    </style>

    <h1>Artikel</h1>

    <p class="gedaempft">
        Der Artikelkatalog ist die gemeinsame Klammer für Einkaufsliste, Rezeptzutat
        und Vorrat. Ohne Angabe einer Warengruppe entscheiden die Stichwortregeln.
        Artikel gelten als überall erhältlich — einzutragen ist nur die Ausnahme.
        Was als <strong>Grundvorrat</strong> gekennzeichnet ist, schlägt der Assistent
        „Neue Woche" mit seiner Sollmenge vor; Rezepte setzen es als vorhanden voraus.
    </p>

    {{-- Filter und Sortierung: bei dreistelliger Artikelzahl der eigentliche
         Zugang zur Tabelle. --}}
    <form method="GET" action="{{ route('stammdaten.produkte.index') }}" class="filter">
        <div style="flex:1 1 12rem">
            <label for="suche">Suche</label>
            <input id="suche" name="suche" type="search" value="{{ $suche }}" placeholder="Name" style="width:100%">
        </div>

        <div>
            <label for="gruppe">Warengruppe</label>
            <select id="gruppe" name="gruppe">
                <option value="">— alle —</option>
                @foreach ($gruppen as $eintrag)
                    <option value="{{ $eintrag->id }}" @selected($gruppe === $eintrag->id)>{{ $eintrag->label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="sortierung">Sortierung</label>
            <select id="sortierung" name="sortierung">
                <option value="name" @selected($sortierung === 'name')>Name</option>
                <option value="gruppe" @selected($sortierung === 'gruppe')>Warengruppe</option>
                <option value="neu" @selected($sortierung === 'neu')>zuletzt angelegt</option>
            </select>
        </div>

        <label class="kaestchen">
            <input type="checkbox" name="vorrat" value="1" @checked($nurVorrat)> nur Grundvorrat
        </label>
        <label class="kaestchen">
            <input type="checkbox" name="eingeschraenkt" value="1" @checked($nurEingeschraenkt)> nur eingeschränkt erhältlich
        </label>

        <button type="submit" class="knopf-schlicht">Anwenden</button>
        @if ($suche !== '' || $gruppe || $nurVorrat || $nurEingeschraenkt || $sortierung !== 'name')
            <a href="{{ route('stammdaten.produkte.index') }}" class="gedaempft" style="font-size:0.85rem">zurücksetzen</a>
        @endif
    </form>

    <p class="gedaempft" style="margin:0.6rem 0 0">
        {{ $produkte->total() }} von {{ $gesamt }} Artikeln
        @if ($produkte->hasPages()) · Seite {{ $produkte->currentPage() }} von {{ $produkte->lastPage() }} @endif
        · Enter im Feld speichert die Zeile
    </p>

    <div class="tabellenrahmen">
        <table class="katalog">
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="mittel">Warengruppe</th>
                    <th class="schmal">Einheit</th>
                    <th class="schmal">Grundvorrat</th>
                    <th class="schmal">Sollmenge</th>
                    <th class="mittel">Erhältlich</th>
                    <th class="knopfspalte"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($produkte as $produkt)
                    @php $f = 'artikel'.$produkt->id; @endphp
                    <tr>
                        <td><input form="{{ $f }}" name="name" type="text" value="{{ $produkt->name }}" required></td>
                        <td>
                            <select form="{{ $f }}" name="product_group_id">
                                <option value="">— ohne —</option>
                                @foreach ($gruppen as $eintrag)
                                    <option value="{{ $eintrag->id }}" @selected($produkt->product_group_id === $eintrag->id)>
                                        {{ $eintrag->label }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td><input form="{{ $f }}" name="default_unit" type="text" value="{{ $produkt->default_unit }}"></td>
                        <td style="text-align:center">
                            <input form="{{ $f }}" type="checkbox" name="is_pantry_staple" value="1"
                                   class="vorrat-an" @checked($produkt->is_pantry_staple)
                                   aria-label="{{ $produkt->name }} ist Grundvorrat">
                        </td>
                        <td>
                            {{-- Wie viel davon im Haus sein soll. Der Assistent
                                 „Neue Woche" und die Bestandsaufnahme rechnen damit. --}}
                            <input form="{{ $f }}" name="pantry_target_amount" type="number"
                                   step="0.001" min="0" value="{{ $produkt->pantry_target_amount }}"
                                   aria-label="Sollmenge {{ $produkt->name }}">
                        </td>
                        <td>
                            <details class="verfuegbarkeit">
                                <summary>{{ $produkt->verfuegbarkeitshinweis() ?? 'überall' }}</summary>
                                <div class="auswahl">
                                    <label>
                                        <input form="{{ $f }}" type="radio" name="verfuegbarkeit" value="ueberall"
                                               @checked($produkt->istUeberallErhaeltlich())> überall
                                    </label>
                                    <label>
                                        <input form="{{ $f }}" type="radio" name="verfuegbarkeit" value="auswahl"
                                               @checked(! $produkt->istUeberallErhaeltlich())> nur in diesen Märkten
                                    </label>
                                    @foreach ($maerkte as $markt)
                                        <label style="margin-left:1.2rem">
                                            <input form="{{ $f }}" type="checkbox" name="maerkte[]" value="{{ $markt->id }}"
                                                   @checked($produkt->markets->contains($markt))> {{ $markt->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        </td>
                        <td class="knopfspalte">
                            <button form="{{ $f }}" type="submit" class="knopf-schlicht">Speichern</button>
                            <button form="loeschen{{ $produkt->id }}" type="submit" class="knopf-schlicht">Löschen</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="gedaempft">
                        {{ $suche !== '' || $gruppe || $nurVorrat || $nurEingeschraenkt
                            ? 'Kein Artikel passt zu diesen Angaben.'
                            : 'Noch keine Artikel im Katalog.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{--
        Die Formulare stehen außerhalb der Tabelle und werden über das
        form-Attribut angesprochen. Ein <form> zwischen <tr> und <td> wäre
        ungültiges HTML -- und nur so lassen sich die Felder in echte
        Tabellenspalten legen, statt sie in eine Zelle zu quetschen.
    --}}
    @foreach ($produkte as $produkt)
        <form id="artikel{{ $produkt->id }}" method="POST"
              action="{{ route('stammdaten.produkte.update', $produkt) }}" hidden>
            @csrf @method('PUT')
        </form>
        <form id="loeschen{{ $produkt->id }}" method="POST"
              action="{{ route('stammdaten.produkte.destroy', $produkt) }}" hidden
              onsubmit="return confirm('Artikel „{{ $produkt->name }}“ löschen?')">
            @csrf @method('DELETE')
        </form>
    @endforeach

    {{ $produkte->links() }}

    <h2>Neuer Artikel</h2>
    <form method="POST" action="{{ route('stammdaten.produkte.store') }}">
        @csrf

        <div style="display:flex;gap:0.8rem;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:1 1 16rem">
                <label for="name">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required
                       placeholder="Kirschtomaten" style="margin:0">
            </div>
            <div>
                <label for="product_group_id">Warengruppe</label>
                <select id="product_group_id" name="product_group_id" style="margin:0;width:auto">
                    <option value="">— automatisch bestimmen —</option>
                    @foreach ($gruppen as $eintrag)
                        <option value="{{ $eintrag->id }}">{{ $eintrag->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="default_unit">Einheit</label>
                <input id="default_unit" name="default_unit" type="text" value="{{ old('default_unit') }}"
                       placeholder="g" style="margin:0;width:6rem">
            </div>
            <label style="font-weight:400;margin:0 0 0.4rem">
                <input type="checkbox" name="is_pantry_staple" value="1" style="width:auto"> Grundvorrat
            </label>
            <div>
                <label for="pantry_target_amount">Sollmenge</label>
                <input id="pantry_target_amount" name="pantry_target_amount" type="number"
                       step="0.001" min="0" value="{{ old('pantry_target_amount') }}"
                       style="margin:0;width:6rem">
            </div>
            <button type="submit" style="margin:0">Anlegen</button>
        </div>

        <p class="gedaempft" style="margin-top:0.5rem">
            Erhältlichkeit lässt sich nach dem Anlegen in der Tabelle einschränken.
        </p>
    </form>

    <h2>Dubletten zusammenführen</h2>
    <p class="gedaempft">
        Der aufzulösende Artikel wird gelöscht, alle Verweise wandern auf den Artikel,
        den du behältst. Fehlende Angaben und die gepflegte Erhältlichkeit werden übernommen.
    </p>
    <form method="POST" action="{{ route('stammdaten.produkte.merge') }}"
          style="display:flex;gap:0.8rem;align-items:flex-end;flex-wrap:wrap">
        @csrf

        <div>
            <label for="behalten">Behalten</label>
            <select id="behalten" name="behalten" required style="margin:0;width:auto">
                @foreach ($produkte as $produkt)
                    <option value="{{ $produkt->id }}">{{ $produkt->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="aufloesen">Auflösen</label>
            <select id="aufloesen" name="aufloesen" required style="margin:0;width:auto">
                @foreach ($produkte as $produkt)
                    <option value="{{ $produkt->id }}">{{ $produkt->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" style="margin:0">Zusammenführen</button>
    </form>

    @if ($darfVerwalten)
        {{-- Ganz unten und schmucklos: Wer hier hinklickt, sucht es. --}}
        <h2>Stammdaten zurücksetzen</h2>
        <p class="gedaempft">
            Artikel, Warengruppen und Märkte auf einmal löschen — für den Fall, dass der
            Startdatensatz nicht passt und Einzelnes wegzuklicken länger dauert als neu
            anzufangen. Die nächste Seite zeigt zuerst, was dabei verschwindet.
        </p>
        <p><a href="{{ route('stammdaten.zuruecksetzen') }}" class="knopf knopf-schlicht">Stammdaten zurücksetzen …</a></p>
    @endif
@endsection
