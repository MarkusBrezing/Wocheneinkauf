@extends('layouts.app')
@section('title', 'Märkte')

@section('inhalt')
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
            <form method="POST" action="{{ route('stammdaten.maerkte.update', $markt) }}"
                  style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap">
                @csrf
                @method('PUT')
                <input name="name" type="text" value="{{ $markt->name }}" required style="margin:0;width:auto">
                <input name="sort" type="number" value="{{ $markt->sort }}" required style="margin:0;width:5rem" min="0" max="65535">
                <label style="margin:0;font-weight:400">
                    <input type="checkbox" name="is_active" value="1" @checked($markt->is_active) style="width:auto"> aktiv
                </label>
                <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.35rem 0.7rem">Speichern</button>
            </form>

            <p>
                @unless ($markt->is_default)
                    <form method="POST" action="{{ route('stammdaten.maerkte.standard', $markt) }}" class="inline">
                        @csrf
                        <button type="submit" class="knopf-schlicht" style="padding:0.2rem 0.6rem">Als Standard setzen</button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('stammdaten.maerkte.destroy', $markt) }}" class="inline"
                      onsubmit="return confirm('Markt „{{ $markt->name }}“ mit allen Regalbereichen löschen?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="knopf-schlicht" style="padding:0.2rem 0.6rem">Markt löschen</button>
                </form>
            </p>
        @endif

        <div class="tabellenrahmen">
            <table>
                <thead>
                    <tr><th style="width:5rem">Reihenfolge</th><th>Regalbereich</th><th>Warengruppen</th>@if ($darfVerwalten)<th></th>@endif</tr>
                </thead>
                <tbody>
                    @forelse ($markt->sections as $bereich)
                        <tr>
                            @if ($darfVerwalten)
                                <td colspan="2">
                                    <form method="POST" action="{{ route('stammdaten.bereiche.update', $bereich) }}"
                                          style="display:flex;gap:0.4rem;align-items:center">
                                        @csrf
                                        @method('PUT')
                                        <input name="sort" type="number" value="{{ $bereich->sort }}" required
                                               style="margin:0;width:5rem" min="0" max="65535">
                                        <input name="name" type="text" value="{{ $bereich->name }}" required style="margin:0">
                                        <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.35rem 0.7rem">Speichern</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('stammdaten.bereiche.gruppen', $bereich) }}">
                                        @csrf
                                        @method('PUT')
                                        <select name="gruppen[]" multiple size="4" style="margin:0">
                                            @foreach ($gruppen as $gruppe)
                                                <option value="{{ $gruppe->id }}"
                                                    @selected($bereich->productGroups->contains($gruppe))>{{ $gruppe->label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="knopf-schlicht" style="margin-top:0.3rem;padding:0.2rem 0.6rem">Zuordnung speichern</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('stammdaten.bereiche.destroy', $bereich) }}" class="inline"
                                          onsubmit="return confirm('Regalbereich „{{ $bereich->name }}“ löschen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">Löschen</button>
                                    </form>
                                </td>
                            @else
                                <td>{{ $bereich->sort }}</td>
                                <td>{{ $bereich->name }}</td>
                                <td class="gedaempft">{{ $bereich->productGroups->pluck('label')->implode(', ') ?: '—' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="4" class="gedaempft">Noch keine Regalbereiche.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

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
