@extends('layouts.app')
@section('title', 'Fehlmengen übernehmen')
@section('koerper', 'weit')

@section('inhalt')
    <style>
        .fehlmengen .zahl { text-align: right; white-space: nowrap;
            font-variant-numeric: tabular-nums; width: 6rem; }
        .fehlmengen .fehlt { font-weight: 600; color: var(--akzent); }
        .fehlmengen .kaestchen { width: 2.5rem; text-align: center; }
    </style>

    <p><a href="{{ route('vorrat.index') }}">‹ Zurück zum Bestand</a></p>

    <h1>Fehlmengen auf die Einkaufsliste</h1>

    @if ($fehlmengen->isEmpty())
        <div class="hinweis">
            <strong>Es fehlt nichts.</strong> Jede Vorratsposition liegt auf oder über ihrem Soll.
        </div>
        <p><a href="{{ route('vorrat.index') }}" class="knopf knopf-schlicht">Zurück zum Bestand</a></p>
    @else
        <p>
            {{ $fehlmengen->count() }}
            {{ $fehlmengen->count() === 1 ? 'Position liegt' : 'Positionen liegen' }}
            unter dem Soll. Gerechnet wird <strong>Soll minus Bestand</strong> — was darüber liegt,
            bleibt im Schrank und nicht auf dem Zettel.
        </p>

        <form method="POST" action="{{ route('vorrat.auf-die-liste.store') }}">
            @csrf

            <div class="tabellenrahmen">
                <table class="stammtabelle fehlmengen">
                    <thead>
                        <tr>
                            <th class="kaestchen"></th>
                            <th>Artikel</th>
                            <th>Ort</th>
                            <th class="zahl">Bestand</th>
                            <th class="zahl">Soll</th>
                            <th class="zahl">Fehlt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fehlmengen as $zeile)
                            <tr>
                                {{-- Alles vorausgewählt: Der Normalfall ist „alles
                                     übernehmen". Wer eine Zeile nicht will, hakt sie
                                     ab -- das ist seltener als der Normalfall und
                                     darf deshalb der aufwendigere Weg sein. --}}
                                <td class="kaestchen">
                                    <input type="checkbox" name="positionen[]" value="{{ $zeile['id'] }}" checked
                                           aria-label="{{ $zeile['name'] }} übernehmen">
                                </td>
                                <td>{{ $zeile['name'] }}</td>
                                <td class="gedaempft">{{ $zeile['ort'] }}</td>
                                <td class="zahl gedaempft">{{ $zeile['bestand'] }} {{ $zeile['einheit'] }}</td>
                                <td class="zahl gedaempft">{{ $zeile['soll'] }} {{ $zeile['einheit'] }}</td>
                                <td class="zahl fehlt">{{ $zeile['fehlt'] }} {{ $zeile['einheit'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="hinweis" style="margin-top:1rem">
                Was schon offen auf der Einkaufsliste steht, wird <strong>zusammengeführt</strong>
                statt verdoppelt — bei gleicher Einheit addieren sich die Mengen.
            </div>

            <div style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;margin-top:1rem">
                <button type="submit">Auf die Einkaufsliste setzen</button>
                <a href="{{ route('vorrat.index') }}" class="knopf knopf-schlicht">Abbrechen</a>
            </div>
        </form>
    @endif
@endsection
