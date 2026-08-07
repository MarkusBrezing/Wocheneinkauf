@extends('layouts.app')

@section('title', 'Wochenplan · Historie')

@section('inhalt')
    <h1>Was wann zuletzt auf dem Tisch stand</h1>

    <p class="gedaempft">
        Sortiert nach dem längsten Abstand -- oben steht, was am ehesten wieder
        an der Reihe wäre. Genau in dieser Reihenfolge bewertet auch der
        Vorschlagsgenerator. Wer ein Rezept dauerhaft aus den Vorschlägen nehmen
        will, tut das hier; von Hand einsetzen lässt es sich weiterhin.
    </p>

    <p><a href="{{ route('wochenplan.index') }}">‹ Zurück zum Wochenplan</a></p>

    <div class="tabellenrahmen">
        <table>
            <thead>
                <tr>
                    <th>Rezept</th>
                    <th>Zuletzt</th>
                    <th style="text-align:right">Wie oft</th>
                    <th>Vorschläge</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rezepte as $zeile)
                    <tr @if ($loop->even) style="background:var(--flaeche)" @endif>
                        <td>
                            <a href="{{ route('rezepte.show', $zeile['rezept']) }}">{{ $zeile['rezept']->name }}</a>
                            @if ($zeile['rezept']->is_favorite)
                                <span title="Favorit">★</span>
                            @endif
                        </td>
                        <td>
                            @if ($zeile['zuletzt'])
                                {{ $zeile['zuletzt']->format('d.m.Y') }}
                                <span class="gedaempft">
                                    ({{ $zeile['tage'] == 0 ? 'heute' : 'vor '.$zeile['tage'].' Tagen' }})
                                </span>
                            @else
                                <span class="gedaempft">noch nie geplant</span>
                            @endif
                        </td>
                        <td style="text-align:right">{{ $zeile['anzahl'] }}</td>
                        <td>
                            <form method="POST" action="{{ route('wochenplan.rezept.ausschliessen', $zeile['rezept']) }}">
                                @csrf
                                <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">
                                    {{ $zeile['rezept']->exclude_from_suggestions ? 'Wieder vorschlagen' : 'Nie vorschlagen' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="gedaempft">Noch keine Rezepte angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
