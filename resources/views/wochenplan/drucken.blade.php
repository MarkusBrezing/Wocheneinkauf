<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wochenplan KW {{ $plan->kalenderwoche() }}</title>
    <style>
        /* Bewusst eigenständig und ohne Navigation: Der Ausdruck soll an den
           Kühlschrank, nicht ins Browserfenster. */
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            margin: 1.5rem auto; max-width: 46rem; color: #1C1D1B; }
        h1 { font-size: 1.3rem; margin-bottom: 0.2rem; }
        .zeitraum { color: #6A6B66; margin-top: 0; }
        table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.45rem 0.6rem; border-bottom: 1px solid #DCD6CB;
            vertical-align: top; }
        th { background: #F2EFE9; }
        .tag { font-weight: 600; white-space: nowrap; }
        .leer { color: #6A6B66; }
        .drucken { margin-top: 1.5rem; }
        @media print { .drucken { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <h1>Wochenplan · KW {{ $plan->kalenderwoche() }}</h1>
    <p class="zeitraum">
        {{ $plan->week_start->format('d.m.Y') }} bis {{ $plan->week_start->copy()->addDays(6)->format('d.m.Y') }}
    </p>

    <table>
        <tbody>
            @foreach ($plan->tage() as $tag)
                @php($tagesSlots = $slots->get($tag->toDateString(), collect()))
                <tr>
                    <td class="tag">
                        {{ $tag->locale('de')->isoFormat('dddd') }}<br>
                        <span class="leer">{{ $tag->format('d.m.') }}</span>
                    </td>
                    <td>
                        @foreach ($tagesSlots as $slot)
                            <div>
                                @if ($tagesSlots->count() > 1)
                                    <strong>{{ $slot->mahlzeitLabel() }}:</strong>
                                @endif
                                @if ($slot->istBelegt())
                                    {{ $slot->anzeige() }}@if ($slot->servings) ({{ $slot->servings }} Portionen)@endif
                                @else
                                    <span class="leer">—</span>
                                @endif
                            </div>
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="drucken">
        <button onclick="window.print()">Drucken</button>
        <a href="{{ route('wochenplan.index', ['woche' => $plan->week_start->toDateString()]) }}">Zurück</a>
    </p>
</body>
</html>
