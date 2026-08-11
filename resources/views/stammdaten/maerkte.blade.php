@extends('layouts.app')
@section('title', 'Märkte')
@section('koerper', 'weit')

@section('inhalt')
    @include('partials.stammdaten-schritte')

    <style>
        /* Angaben links, Handlungen rechts -- dieselbe Ordnung wie in den
           Tabellenzeilen darunter. */
        .marktkopf { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;
            padding: 0.5rem 0.7rem; border: 1px solid var(--rand); border-radius: 6px;
            background: var(--flaeche); margin-bottom: 0.75rem; }
        .marktkopf input[type=text] { width: 16rem; margin: 0; }
        .marktkopf input[type=number] { margin: 0; }
        .marktkopf label { margin: 0; font-weight: 400; display: flex; align-items: center; gap: 0.3rem; }
        .marktkopf input[type=checkbox] { width: auto; margin: 0; accent-color: var(--primaer); }
        .marktkopf .handlungen { margin-left: auto; display: flex; gap: 0.3rem; flex-wrap: wrap; }
        .marktkopf button { margin: 0; padding: 0.25rem 0.6rem; font-size: 0.85rem; }

        /* Die Mehrfachauswahl darf nicht die halbe Zeile fressen. */
        .bereiche select[multiple] { min-width: 14rem; }
    </style>

    <h1>Märkte</h1>

    <p class="gedaempft">
        Die Regalbereiche in Laufreihenfolge bestimmen, in welcher Reihenfolge die
        Einkaufsliste im jeweiligen Markt erscheint. Jeder Bereich bündelt eine
        oder mehrere Warengruppen.
    </p>

    @forelse ($maerkte as $markt)
        <h2>
            {{ $markt->name }}
            @if ($markt->is_default)<span class="gedaempft">· Standard</span>@endif
            @unless ($markt->is_active)<span class="gedaempft">· deaktiviert</span>@endunless
        </h2>

        @if ($darfVerwalten)
            {{-- Die Angaben des Marktes links, seine Handlungen rechts -- wie in
                 den Tabellenzeilen darunter. --}}
            <form method="POST" action="{{ route('stammdaten.maerkte.update', $markt) }}" class="marktkopf">
                @csrf
                @method('PUT')
                <input name="name" type="text" value="{{ $markt->name }}" required aria-label="Name des Marktes">
                <input name="sort" type="number" value="{{ $markt->sort }}" required min="0" max="65535"
                       aria-label="Sortierung" style="width:5rem">
                <label>
                    <input type="checkbox" name="is_active" value="1" @checked($markt->is_active)> aktiv
                </label>

                <span class="handlungen">
                    <button type="submit" class="knopf-schlicht">Speichern</button>
                    @unless ($markt->is_default)
                        <button type="submit" class="knopf-schlicht" form="standard{{ $markt->id }}">Als Standard</button>
                    @endunless
                    <button type="submit" class="knopf-schlicht" form="marktweg{{ $markt->id }}">Markt löschen</button>
                </span>
            </form>

            @unless ($markt->is_default)
                <form id="standard{{ $markt->id }}" method="POST"
                      action="{{ route('stammdaten.maerkte.standard', $markt) }}" hidden>@csrf</form>
            @endunless
            <form id="marktweg{{ $markt->id }}" method="POST" hidden
                  action="{{ route('stammdaten.maerkte.destroy', $markt) }}"
                  onsubmit="return confirm('Markt „{{ $markt->name }}“ mit allen Regalbereichen löschen?')">
                @csrf @method('DELETE')
            </form>
        @endif

        <div class="tabellenrahmen">
            <table class="stammtabelle bereiche">
                <thead>
                    <tr>
                        <th class="zahl">Reihenfolge</th>
                        <th>Regalbereich</th>
                        <th>Warengruppen, die hier liegen</th>
                        @if ($darfVerwalten)
                            <th class="knopfspalte"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($markt->sections as $bereich)
                        @php($f = 'bereich'.$bereich->id)
                        <tr>
                            @if ($darfVerwalten)
                                <td class="zahl">
                                    <input form="{{ $f }}" name="sort" type="number" value="{{ $bereich->sort }}"
                                           required min="0" max="65535" aria-label="Reihenfolge">
                                </td>
                                <td>
                                    <input form="{{ $f }}" name="name" type="text" value="{{ $bereich->name }}"
                                           required aria-label="Name des Regalbereichs">
                                </td>
                                <td>
                                    {{-- Die Zuordnung gehört zum selben Formular wie Name
                                         und Reihenfolge -- ein Speichern für die ganze
                                         Zeile statt zwei nebeneinander. --}}
                                    <select form="{{ $f }}" name="gruppen[]" multiple size="4"
                                            aria-label="Warengruppen in diesem Bereich">
                                        @foreach ($gruppen as $gruppe)
                                            <option value="{{ $gruppe->id }}"
                                                @selected($bereich->productGroups->contains($gruppe))>{{ $gruppe->label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="knopfspalte">
                                    <button form="{{ $f }}" type="submit" class="knopf-schlicht">Speichern</button>
                                    <button form="{{ $f }}weg" type="submit" class="knopf-schlicht">Löschen</button>
                                </td>
                            @else
                                <td class="zahl">{{ $bereich->sort }}</td>
                                <td>{{ $bereich->name }}</td>
                                <td class="gedaempft">{{ $bereich->productGroups->pluck('label')->implode(', ') ?: '—' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $darfVerwalten ? 4 : 3 }}" class="gedaempft">
                                Noch keine Regalbereiche. Ohne sie sortiert die Liste in diesem
                                Markt nach Warengruppen.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($darfVerwalten)
            @foreach ($markt->sections as $bereich)
                <form id="bereich{{ $bereich->id }}" method="POST"
                      action="{{ route('stammdaten.bereiche.update', $bereich) }}" hidden>
                    @csrf @method('PUT')
                </form>
                <form id="bereich{{ $bereich->id }}weg" method="POST" hidden
                      action="{{ route('stammdaten.bereiche.destroy', $bereich) }}"
                      onsubmit="return confirm('Regalbereich „{{ $bereich->name }}“ löschen?')">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        @endif

        @if ($darfVerwalten)
            <form method="POST" action="{{ route('stammdaten.bereiche.store', $markt) }}"
                  style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;margin-top:0.6rem">
                @csrf
                <input name="name" type="text" placeholder="Neuer Regalbereich" required style="margin:0;width:auto">
                <input name="sort" type="number" placeholder="Pos." value="0" style="margin:0;width:5rem" min="0" max="65535">
                <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.35rem 0.7rem">Hinzufügen</button>
            </form>
        @endif
    @empty
        <p class="gedaempft">Noch keine Märkte angelegt.</p>
    @endforelse

    @if ($darfVerwalten)
        <h2>Neuer Markt</h2>
        <form method="POST" action="{{ route('stammdaten.maerkte.store') }}">
            @csrf

            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required placeholder="Storchenhof">

            <label for="sort">Sortierung</label>
            <input id="sort" name="sort" type="number" value="{{ old('sort', 0) }}" min="0" max="65535">

            <button type="submit">Markt anlegen</button>
        </form>
    @endif

    @if ($darfVerwalten)
        <p class="gedaempft" style="margin-top:2.5rem">
            Alle Stammdaten auf einmal loswerden — Artikel, Warengruppen und Märkte:
            <a href="{{ route('stammdaten.zuruecksetzen') }}">Stammdaten zurücksetzen</a>.
        </p>
    @endif
@endsection
