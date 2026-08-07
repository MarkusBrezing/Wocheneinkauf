@php
    // Der aktive Eintrag kommt aus dem Routennamen, nicht aus einer Variablen,
    // die jede View setzen müsste -- Unterseiten ohne eigenen Menüeintrag
    // markieren so von selbst den richtigen Bereich.
    $routenname = request()->route()?->getName();
    $gruppen = \App\Core\Support\Navigation::gruppen();
    $aktiv = \App\Core\Support\Navigation::aktiv($routenname);
    $aktiveGruppe = $aktiv['gruppe'] ?? null;
    $geraete = \App\Core\Support\Navigation::GERAETE;
@endphp

<nav class="nav-bereiche" aria-label="Bereiche">
    <strong><a href="{{ route('dashboard') }}">Wocheneinkauf</a></strong>

    {{-- Erste Ebene: fünf Bereiche. Ein Klick führt auf die erste Seite des
         Bereichs und blendet damit die zweite Ebene ein -- ohne Klappmenü,
         das auf einem Tastenfeld oder unter dem Finger nur im Weg wäre. --}}
    @foreach ($gruppen as $schluessel => $gruppe)
        <a href="{{ route($gruppe['items'][0]['route']) }}"
           class="bereich @if ($schluessel === $aktiveGruppe) aktiv @endif"
           @if ($schluessel === $aktiveGruppe) aria-current="true" @endif>{{ $gruppe['label'] }}</a>
    @endforeach

    <span class="gedaempft wer">
        {{ auth()->user()->name }}@if (auth()->user()->currentFamily) · {{ auth()->user()->currentFamily->name }}@endif
    </span>

    {{--
        Beim Abmelden räumt der Service Worker seinen Speicher. Sonst bliebe
        die zuletzt geöffnete Einkaufsliste auf dem Gerät liegen -- auf einem
        geteilten Tablet wäre das die Liste einer fremden Familie.
    --}}
    <form method="POST" action="{{ route('logout') }}" class="inline"
          onsubmit="if (navigator.serviceWorker && navigator.serviceWorker.controller) { navigator.serviceWorker.controller.postMessage({ type: 'abmelden' }); }">
        @csrf
        <button type="submit" class="knopf-schlicht abmelden">Abmelden</button>
    </form>
</nav>

@if ($aktiveGruppe)
    {{-- Zweite Ebene: die Seiten des Bereichs, jede mit ihrem Gerätezeichen.
         Das steht hier und nicht erst auf der Seite, damit man vor dem Klick
         weiß, was einen erwartet. --}}
    <nav class="nav-seiten" aria-label="Seiten im Bereich {{ $gruppen[$aktiveGruppe]['label'] }}">
        @foreach ($gruppen[$aktiveGruppe]['items'] as $item)
            @php($istAktiv = $aktiv && $aktiv['item']['route'] === $item['route'])
            <a href="{{ route($item['route']) }}"
               class="@if ($istAktiv) aktiv @endif"
               @if ($istAktiv) aria-current="page" @endif>
                {{ $item['label'] }}
                <span class="marke-geraet marke-{{ $item['geraet'] }}">
                    <span aria-hidden="true">{{ $geraete[$item['geraet']]['zeichen'] }}</span>
                    {{ $geraete[$item['geraet']]['kurz'] }}
                </span>
            </a>
        @endforeach
    </nav>
@endif
