@extends('layouts.einkauf')
@section('title', 'Einkaufen')

@section('inhalt')
    @php
        $fortschritt = $gesamt > 0 ? round($erledigt / $gesamt * 100) : 0;
    @endphp

    <header class="kopf">
        <div class="kopf-zeile">
            <h1>{{ $liste->anzeigename() }}</h1>
            <span class="zaehler">{{ $erledigt }} von {{ $gesamt }}</span>
        </div>
        <div class="balken" role="progressbar" aria-valuenow="{{ $erledigt }}"
             aria-valuemin="0" aria-valuemax="{{ $gesamt }}"
             aria-label="Abgehakte Artikel">
            <div style="width: {{ $fortschritt }}%"></div>
        </div>
    </header>

    @forelse ($abschnitte as $index => $abschnitt)
        {{-- Der Regalbereich klebt beim Scrollen unter dem Kopf: So weiß man
             jederzeit, vor welchem Regal man gerade steht. --}}
        <h2 class="bereich @if ($abschnitt->name === \App\Modules\ShoppingList\Services\ListSorter::ANDERSWO) bereich-anderswo @endif"
            id="b{{ $index }}">
            {{ $abschnitt->name }}
        </h2>

        @foreach ($abschnitt->items as $item)
            {{-- Ein Formular je Zeile, die ganze Zeile ist der Knopf. Der
                 Anker führt nach dem Abhaken an dieselbe Stelle zurück --
                 ohne ihn stünde man nach jedem Tippen wieder ganz oben. --}}
            <form method="POST" action="{{ route('einkaufsliste.eintraege.haken', $item) }}" class="zeile">
                @csrf
                <input type="hidden" name="anker" value="b{{ $index }}">
                <button type="submit" @class(['abgehakt' => $item->is_checked])>
                    <span class="kaestchen" aria-hidden="true">{{ $item->is_checked ? '☑' : '☐' }}</span>
                    <span>
                        <span class="name">
                            @if ($item->mengeAnzeige() !== '')<b>{{ $item->mengeAnzeige() }}</b> @endif{{ $item->raw_name }}
                        </span>
                        @if ($item->note || $item->verfuegbarkeitshinweis())
                            <span class="zusatz">
                                {{ $item->note }}@if ($item->note && $item->verfuegbarkeitshinweis()) · @endif{{ $item->verfuegbarkeitshinweis() }}
                            </span>
                        @endif
                    </span>
                </button>
            </form>
        @endforeach
    @empty
        <p class="leer">
            @if ($gesamt === 0)
                Auf der Liste steht nichts.
            @else
                Alles abgehakt — {{ $erledigt }} {{ $erledigt === 1 ? 'Artikel' : 'Artikel' }} im Wagen.
            @endif
        </p>
    @endforelse

    @if ($erledigt > 0)
        <p style="padding: 0.9rem; margin: 0; text-align: center;">
            <a href="{{ route('einkaufsliste.einkaufen', $mitErledigten ? [] : ['erledigt' => 1]) }}"
               style="color: var(--gedaempft); font-size: 0.9rem;">
                {{ $mitErledigten ? 'Abgehaktes ausblenden' : $erledigt.' Abgehaktes zeigen' }}
            </a>
        </p>
    @endif

    <footer class="fuss">
        <form method="POST" action="{{ route('einkaufsliste.archivieren') }}"
              onsubmit="return confirm('Einkauf beenden?{{ $offen > 0 ? ' Die '.$offen.' fehlenden Artikel kommen auf die neue Liste.' : '' }}')">
            @csrf
            {{-- Was nicht abgehakt ist, hat gefehlt und wandert mit. --}}
            <input type="hidden" name="offene_uebernehmen" value="1">
            <button type="submit">Einkauf beenden</button>
        </form>

        <a href="{{ route('einkaufsliste.index') }}" class="schlicht"
           aria-label="Zur vollständigen Liste">&#8942;</a>
    </footer>
@endsection
