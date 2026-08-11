@extends('layouts.app')
@section('title', 'Warengruppen')
@section('koerper', 'weit')

@section('inhalt')
    @include('partials.stammdaten-schritte')

    <style>
        .warengruppen .kuerzel { width: 12rem; }
        .warengruppen .anzahl { width: 5rem; text-align: right;
            font-variant-numeric: tabular-nums; }
        .warengruppen code { background: none; padding: 0; color: var(--gedaempft); }
    </style>

    <h1>Warengruppen</h1>

    <p class="gedaempft">
        Die Warengruppen bestimmen, wie die Einkaufsliste sortiert wird. Das Kürzel
        ist unveränderlich, weil die Stichwortregeln sich darauf beziehen.
        Die <strong>Sortierung</strong> gilt, solange kein Markt gewählt ist — sonst
        entscheidet dessen Laufreihenfolge.
    </p>

    <div class="tabellenrahmen">
        <table class="stammtabelle warengruppen">
            <thead>
                <tr>
                    <th class="kuerzel">Kürzel</th>
                    <th>Bezeichnung</th>
                    <th class="zahl">Sortierung</th>
                    <th class="anzahl">Artikel</th>
                    @if ($darfVerwalten)
                        <th class="knopfspalte"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($gruppen as $gruppe)
                    @php($f = 'wg'.$gruppe->id)
                    <tr>
                        <td class="kuerzel"><code>{{ $gruppe->code }}</code></td>

                        @if ($darfVerwalten)
                            {{-- Jedes Feld liegt in seiner eigenen Spalte und gehört
                                 über das form-Attribut trotzdem zum selben Formular. --}}
                            <td><input form="{{ $f }}" name="label" type="text" value="{{ $gruppe->label }}" required></td>
                            <td class="zahl">
                                <input form="{{ $f }}" name="sort" type="number" value="{{ $gruppe->sort }}"
                                       required min="0" max="65535">
                            </td>
                        @else
                            <td>{{ $gruppe->label }}</td>
                            <td class="zahl">{{ $gruppe->sort }}</td>
                        @endif

                        <td class="anzahl gedaempft">{{ $gruppe->products_count }}</td>

                        @if ($darfVerwalten)
                            <td class="knopfspalte">
                                <button form="{{ $f }}" type="submit" class="knopf-schlicht">Speichern</button>
                                <button form="{{ $f }}del" type="submit" class="knopf-schlicht">Löschen</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $darfVerwalten ? 5 : 4 }}" class="gedaempft">
                            Noch keine Warengruppen. Ohne sie landet alles auf der Einkaufsliste
                            im Abschnitt „Ohne Warengruppe".
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($darfVerwalten)
        {{--
            Die Formulare stehen außerhalb der Tabelle und werden über das
            form-Attribut angesprochen. Ein <form> zwischen <tr> und <td> wäre
            ungültiges HTML -- und nur so lassen sich die Felder in echte
            Tabellenspalten legen, statt sie in eine Zelle zu quetschen.
        --}}
        @foreach ($gruppen as $gruppe)
            <form id="wg{{ $gruppe->id }}" method="POST"
                  action="{{ route('stammdaten.warengruppen.update', $gruppe) }}" hidden>
                @csrf @method('PUT')
            </form>
            <form id="wg{{ $gruppe->id }}del" method="POST"
                  action="{{ route('stammdaten.warengruppen.destroy', $gruppe) }}" hidden
                  onsubmit="return confirm('Warengruppe „{{ $gruppe->label }}“ löschen?')">
                @csrf @method('DELETE')
                <input type="hidden" name="trotzdem" value="{{ $errors->has('gruppe') ? 1 : 0 }}">
            </form>
        @endforeach

        <h2>Neue Warengruppe</h2>
        <form method="POST" action="{{ route('stammdaten.warengruppen.store') }}"
              style="display:flex;gap:0.6rem;align-items:flex-end;flex-wrap:wrap">
            @csrf

            <div style="flex:0 0 12rem">
                <label for="code">Kürzel</label>
                <input id="code" name="code" type="text" value="{{ old('code') }}" required
                       placeholder="TIEFKUEHL" pattern="[A-Z0-9_]+" style="margin:0">
            </div>
            <div style="flex:1 1 16rem">
                <label for="label">Bezeichnung</label>
                <input id="label" name="label" type="text" value="{{ old('label') }}" required
                       placeholder="Tiefkühl" style="margin:0">
            </div>
            <div style="flex:0 0 7rem">
                <label for="sort">Sortierung</label>
                <input id="sort" name="sort" type="number" value="{{ old('sort', 0) }}"
                       min="0" max="65535" style="margin:0">
            </div>
            <button type="submit" style="margin:0">Anlegen</button>
        </form>

        <p class="gedaempft" style="margin-top:2.5rem">
            Alle Stammdaten auf einmal loswerden — Artikel, Warengruppen und Märkte:
            <a href="{{ route('stammdaten.zuruecksetzen') }}">Stammdaten zurücksetzen</a>.
        </p>
    @else
        <p class="gedaempft">Warengruppen darf nur ändern, wer die Familie verwaltet.</p>
    @endif
@endsection
