@extends('layouts.app')
@section('title', 'Übersicht')

@section('inhalt')
    <h1>Übersicht</h1>

    @if ($aktuelleFamilie)
        <p>
            Du arbeitest gerade in <strong>{{ $aktuelleFamilie->name }}</strong>
            als <em>{{ auth()->user()->roleIn($aktuelleFamilie) }}</em>.
        </p>
    @else
        <p class="fehler">Für dein Konto ist keine Familie gesetzt.</p>
    @endif

    @if ($familien->count() > 1)
        <h2>Familie wechseln</h2>
        <form method="POST" action="{{ route('family.switch') }}">
            @csrf
            <label for="family">Familie</label>
            <select id="family" name="family">
                @foreach ($familien as $familie)
                    <option value="{{ $familie->public_id }}"
                        @selected($aktuelleFamilie && $familie->is($aktuelleFamilie))>
                        {{ $familie->name }} ({{ $familie->pivot->role }})
                    </option>
                @endforeach
            </select>
            <button type="submit">Wechseln</button>
        </form>
    @endif

    <h2>Einkaufen</h2>
    <p>
        <a href="{{ route('einkaufsliste.index') }}" class="knopf">Zur Einkaufsliste</a>
        <a href="{{ route('einkaufsliste.einkaufen') }}" class="knopf knopf-akzent">Einkaufen (Handy)</a>
        <a href="{{ route('einkaufsliste.neue-woche') }}" class="knopf knopf-schlicht">Neue Woche starten</a>
    </p>

    <h2>Planen</h2>
    <p>
        <a href="{{ route('wochenplan.index') }}" class="knopf knopf-schlicht">Wochenplan</a>
        <a href="{{ route('rezepte.index') }}" class="knopf knopf-schlicht">Rezepte</a>
        <a href="{{ route('vorrat.index') }}" class="knopf knopf-schlicht">Vorrat</a>
        <a href="{{ route('vorrat.inventur.index') }}" class="knopf knopf-schlicht">Inventur</a>
    </p>

    <h2>Hilfe</h2>
    <p class="gedaempft">
        Das <a href="{{ route('handbuch.index') }}">Anwenderhandbuch</a> erklärt jede Seite —
        für den Anfang reichen die ersten drei Kapitel.
    </p>
@endsection
