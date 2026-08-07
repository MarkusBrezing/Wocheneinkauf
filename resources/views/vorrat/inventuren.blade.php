@extends('layouts.app')
@section('title', 'Inventur')

@section('inhalt')
    <h1>Inventur</h1>

    <p class="gedaempft">
        Ein Inventurlauf friert Soll und Ist ein, sammelt die Zählungen — auch
        von mehreren Personen — und schreibt die Bestände erst beim Abschluss
        fort. Ein Abbruch lässt alles, wie es war.
    </p>

    @if ($laufend)
        <div class="hinweis">
            <strong>{{ $laufend->anzeigename() }}</strong> läuft seit
            {{ $laufend->started_at->diffForHumans() }} —
            {{ $laufend->lines_count - $laufend->offene_count }} von {{ $laufend->lines_count }} gezählt.
            <p style="margin:0.6rem 0 0">
                <a href="{{ route('vorrat.inventur.show', $laufend) }}" class="knopf" style="margin:0">Weiterzählen</a>
            </p>
        </div>
    @else
        <h2>Neue Inventur</h2>
        <form method="POST" action="{{ route('vorrat.inventur.store') }}">
            @csrf

            <label for="title">Bezeichnung <span class="gedaempft">(optional)</span></label>
            <input id="title" name="title" type="text" placeholder="Vorratscheck August">

            <label>Umfang</label>
            <label style="font-weight:400">
                <input type="radio" name="scope" value="full" checked style="width:auto">
                Gesamter Vorrat ({{ $positionen }} Positionen)
            </label>
            <label style="font-weight:400">
                <input type="radio" name="scope" value="location" style="width:auto"> Nur ein Ort:
            </label>
            <select name="scope_value" style="margin-left:1.2rem;width:auto">
                @foreach ($orte as $ort)
                    <option value="{{ $ort }}">{{ $ort }}</option>
                @endforeach
            </select>

            <button type="submit">Inventur starten</button>
        </form>
    @endif

    <h2>Frühere Läufe</h2>
    <div class="tabellenrahmen">
        <table>
            <thead>
                <tr><th>Lauf</th><th>Umfang</th><th style="width:6rem">Positionen</th><th style="width:8rem">Ergebnis</th></tr>
            </thead>
            <tbody>
                @forelse ($historie as $eintrag)
                    <tr>
                        <td>
                            <a href="{{ route('vorrat.inventur.show', $eintrag) }}">{{ $eintrag->anzeigename() }}</a>
                            <span class="gedaempft" style="font-size:0.8rem">
                                · {{ $eintrag->completed_at?->format('d.m.Y H:i') }}
                            </span>
                        </td>
                        <td class="gedaempft">{{ $eintrag->umfangLabel() }}</td>
                        <td>{{ $eintrag->lines_count }}</td>
                        <td>{{ $eintrag->status === 'completed' ? 'abgeschlossen' : 'abgebrochen' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="gedaempft">Noch keine Inventur gelaufen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $historie->links() }}
@endsection
