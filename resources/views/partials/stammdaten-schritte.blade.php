@php
    // Quelle ist dieselbe Liste, die auch das Menü baut -- die Reihenfolge
    // steht also genau einmal im Code.
    $schritte = \App\Core\Support\Navigation::gruppen()['stammdaten']['items'];
    $aktiv = \App\Core\Support\Navigation::aktiv(request()->route()?->getName());
@endphp

{{--
    Stammdaten hängen aneinander: Warengruppen gliedern alles, Märkte ordnen
    ihre Regalbereiche den Warengruppen zu, Artikel brauchen beides. Wer die
    Reihenfolge nicht kennt, legt Artikel an, bevor es Warengruppen gibt --
    und wundert sich über den Abschnitt „Ohne Warengruppe".
--}}
<nav class="pflegeschritte" aria-label="Reihenfolge der Stammdatenpflege">
    <ol>
        @foreach ($schritte as $i => $schritt)
            @php($istAktiv = $aktiv && $aktiv['item']['route'] === $schritt['route'])
            <li>
                <a href="{{ route($schritt['route']) }}" class="@if ($istAktiv) aktiv @endif"
                   @if ($istAktiv) aria-current="page" @endif>
                    <span class="nr">{{ $i + 1 }}</span>{{ $schritt['label'] }}
                </a>
            </li>
        @endforeach
    </ol>
    <p class="gedaempft">
        Warengruppen gliedern alles · Märkte ordnen sie ihren Regalbereichen zu ·
        Artikel brauchen beides
    </p>
</nav>
