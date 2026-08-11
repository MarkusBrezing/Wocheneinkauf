@extends('layouts.app')
@section('title', 'Warengruppen')

@section('inhalt')
    @include('partials.stammdaten-schritte')

    <h1>Warengruppen</h1>

    <p class="gedaempft">
        Die Warengruppen bestimmen, wie die Einkaufsliste sortiert wird. Das Kürzel
        ist unveränderlich, weil die Stichwortregeln sich darauf beziehen.
    </p>

    <div class="tabellenrahmen">
        <table>
            <thead>
                <tr><th>Kürzel</th><th>Bezeichnung</th><th>Sortierung</th><th>Artikel</th>@if ($darfVerwalten)<th></th>@endif</tr>
            </thead>
            <tbody>
                @forelse ($gruppen as $gruppe)
                    <tr>
                        <td><code>{{ $gruppe->code }}</code></td>
                        @if ($darfVerwalten)
                            <td colspan="2">
                                <form method="POST" action="{{ route('stammdaten.warengruppen.update', $gruppe) }}"
                                      style="display:flex;gap:0.4rem;align-items:center">
                                    @csrf
                                    @method('PUT')
                                    <input name="label" type="text" value="{{ $gruppe->label }}" required style="margin:0">
                                    <input name="sort" type="number" value="{{ $gruppe->sort }}" required
                                           style="margin:0;width:6rem" min="0" max="65535">
                                    <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.35rem 0.7rem">Speichern</button>
                                </form>
                            </td>
                        @else
                            <td>{{ $gruppe->label }}</td>
                            <td>{{ $gruppe->sort }}</td>
                        @endif
                        <td class="gedaempft">{{ $gruppe->products_count }}</td>
                        @if ($darfVerwalten)
                            <td>
                                <form method="POST" action="{{ route('stammdaten.warengruppen.destroy', $gruppe) }}" class="inline"
                                      onsubmit="return confirm('Warengruppe „{{ $gruppe->label }}“ löschen?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="trotzdem" value="{{ $errors->has('gruppe') ? 1 : 0 }}">
                                    <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">Löschen</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="gedaempft">Noch keine Warengruppen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($darfVerwalten)
        <h2>Neue Warengruppe</h2>
        <form method="POST" action="{{ route('stammdaten.warengruppen.store') }}">
            @csrf

            <label for="code">Kürzel</label>
            <input id="code" name="code" type="text" value="{{ old('code') }}" required
                   placeholder="TIEFKUEHL" pattern="[A-Z0-9_]+">

            <label for="label">Bezeichnung</label>
            <input id="label" name="label" type="text" value="{{ old('label') }}" required placeholder="Tiefkühl">

            <label for="sort">Sortierung</label>
            <input id="sort" name="sort" type="number" value="{{ old('sort', 0) }}" min="0" max="65535">

            <button type="submit">Anlegen</button>
        </form>
    @else
        <p class="gedaempft">Warengruppen darf nur ändern, wer die Familie verwaltet.</p>
    @endif

    @if ($darfVerwalten)
        <p class="gedaempft" style="margin-top:2.5rem">
            Alle Stammdaten auf einmal loswerden — Artikel, Warengruppen und Märkte:
            <a href="{{ route('stammdaten.zuruecksetzen') }}">Stammdaten zurücksetzen</a>.
        </p>
    @endif
@endsection
