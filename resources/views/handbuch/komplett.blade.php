@extends('layouts.handbuch')

@section('titel', 'Alles auf einer Seite')

@section('handbuch')
    <h1 style="margin-top:0">Anwenderhandbuch</h1>
    <p class="gedaempft">
        Alle {{ count($abschnitte) }} Kapitel am Stück — zum Ausdrucken oder zum Durchsuchen mit Strg + F.
    </p>

    <nav aria-label="Inhalt" style="margin:1.5rem 0">
        <ol>
            @foreach ($abschnitte as $abschnitt)
                <li><a href="#k{{ $abschnitt['kapitel']['nummer'] }}">{{ $abschnitt['kapitel']['titel'] }}</a></li>
            @endforeach
        </ol>
    </nav>

    @foreach ($abschnitte as $abschnitt)
        {{-- Seitenumbruch vor jedem Kapitel: Der Ausdruck soll nicht mitten
             im Satz die Seite wechseln. --}}
        <section id="k{{ $abschnitt['kapitel']['nummer'] }}" class="text"
                 style="page-break-before: always; border-top: 1px solid var(--rand); padding-top: 1.5rem; margin-top: 2.5rem">
            {!! $abschnitt['html'] !!}
        </section>
    @endforeach
@endsection
