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

    <h2>Nächster Schritt</h2>
    <p class="gedaempft">
        Die Einkaufsliste kommt in Schritt 4 der Roadmap. Bis dahin zeigt diese
        Seite nur, dass Anmeldung und Mandantenkontext stehen.
    </p>
@endsection
