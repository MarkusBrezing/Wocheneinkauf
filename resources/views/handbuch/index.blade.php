@extends('layouts.handbuch')

@section('titel', 'Übersicht')

@section('handbuch')
    <style>
        .karten { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr));
            margin-top: 1.5rem; }
        .karte { display: block; padding: 1rem 1.1rem; border: 1px solid var(--rand);
            border-radius: 8px; background: var(--flaeche); text-decoration: none; color: var(--text); }
        .karte:hover { border-color: var(--primaer); color: var(--text); }
        .karte .nr { font-size: 0.75rem; color: var(--gedaempft); letter-spacing: 0.08em; }
        .karte h2 { font-size: 1.05rem; margin: 0.2rem 0 0.4rem; color: var(--primaer); }
        .karte p { margin: 0; font-size: 0.9rem; color: var(--gedaempft); }
        .karte .geraet { display: inline-block; margin-top: 0.6rem; font-size: 0.72rem;
            padding: 0.05rem 0.45rem; border-radius: 999px; border: 1px solid var(--rand);
            background: var(--hintergrund); color: var(--gedaempft); }
        .einstieg { padding: 1rem 1.2rem; border-left: 4px solid var(--primaer);
            background: var(--primaer-hell); border-radius: 0 6px 6px 0; }
    </style>

    <h1 style="margin-top:0">Anwenderhandbuch</h1>

    <p class="einstieg">
        <strong>Ganz eilig?</strong> Drei Kapitel reichen für den Alltag:
        <a href="{{ route('handbuch.show', 'erste-schritte') }}">Erste Schritte</a>,
        <a href="{{ route('handbuch.show', 'einkaufsliste') }}">Die Einkaufsliste</a> und
        <a href="{{ route('handbuch.show', 'einkaufen') }}">Einkaufen mit dem Handy</a>.
        Der Rest wartet, bis Sie ihn brauchen.
    </p>

    <div class="karten">
        @foreach ($kapitel as $k)
            <a class="karte" href="{{ route('handbuch.show', $k['slug']) }}">
                <span class="nr">KAPITEL {{ $k['nummer'] }}</span>
                <h2>{{ $k['titel'] }}</h2>
                <p>{{ $k['teaser'] }}</p>
                @if ($k['geraet'])
                    <span class="geraet">{{ $k['geraet'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <p class="gedaempft" style="margin-top:2rem">
        Zum Ausdrucken oder Durchsuchen gibt es das Handbuch auch
        <a href="{{ route('handbuch.komplett') }}">am Stück auf einer Seite</a>.
    </p>
@endsection
