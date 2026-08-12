@extends('layouts.app')
@section('title', 'Grundsortiment laden')
@section('koerper', 'weit')

@section('inhalt')
    @include('partials.stammdaten-schritte')

    <style>
        .bilanz { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
            margin: 1.25rem 0; }
        .bilanz section { border: 1px solid var(--rand); border-radius: 8px; padding: 0.9rem 1rem;
            background: var(--flaeche); }
        .bilanz h2 { margin: 0 0 0.5rem; font-size: 0.95rem; }
        .bilanz table { margin: 0; }
        .bilanz td { border: none; padding: 0.15rem 0; }
        .bilanz td.zahl { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .bilanz .gross { font-size: 2rem; font-weight: 600; color: var(--primaer);
            line-height: 1.1; display: block; }
        .unberuehrt { columns: 2; font-size: 0.9rem; color: var(--gedaempft); }
        @media (max-width: 34rem) { .unberuehrt { columns: 1; } }
    </style>

    <p><a href="{{ route('stammdaten.produkte.index') }}">‹ Zurück zum Artikelkatalog</a></p>

    <h1>Grundsortiment laden</h1>

    <p>
        Der Satz umfasst <strong>{{ $vorschau['gesamt'] }} Artikel</strong>, wie sie in einem
        deutschen Vierpersonenhaushalt vorkommen. Bei allem, was vorrätig gehalten wird, ist
        zugleich eine Sollmenge für den Grundvorrat hinterlegt.
    </p>

    <div class="bilanz">
        <section>
            <h2>Wird angelegt</h2>
            <span class="gross">{{ $vorschau['neu'] }}</span>
            <span class="gedaempft">neue Artikel</span>
        </section>

        <section>
            <h2>Bleibt unangetastet</h2>
            <span class="gross">{{ count($vorschau['vorhanden']) }}</span>
            <span class="gedaempft">Artikel gibt es schon</span>
        </section>

        <section>
            <h2>Verteilung auf Warengruppen</h2>
            <table>
                @forelse ($vorschau['gruppen'] as $gruppe => $anzahl)
                    <tr><td>{{ $gruppe }}</td><td class="zahl">{{ $anzahl }}</td></tr>
                @empty
                    <tr><td class="gedaempft">Es käme nichts dazu.</td></tr>
                @endforelse
            </table>
        </section>
    </div>

    {{-- Die eine Frage, die eine Sicherheitsabfrage beantworten muss: Was
         passiert mit dem, was schon da ist? --}}
    <div class="hinweis">
        <strong>Es geht nichts verloren.</strong> Der Vorgang legt nur an. Ein Artikel, den es
        unter demselben Namen schon gibt, bleibt unverändert — mitsamt seiner Einheit, seiner
        Warengruppe und seiner Sollmenge. Auch ein zweiter Lauf legt deshalb nichts doppelt an.
        <br>
        <strong>Nicht enthalten:</strong> Süßigkeiten, Knabberzeug und Fertiggerichte; aus dem
        Tiefkühlfach nur Rohware. Ebenso fehlen Honig, Marmelade und Ketchup — Grenzfälle, die
        der Satz Ihnen überlässt.
    </div>

    @if ($vorschau['vorhanden'] !== [])
        <h2>Diese {{ count($vorschau['vorhanden']) }} gibt es schon</h2>
        <p class="gedaempft">Sie werden übersprungen, nicht überschrieben.</p>
        <div class="unberuehrt">
            @foreach ($vorschau['vorhanden'] as $name)
                {{ $name }}@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif

    <h2>Laden?</h2>

    <form method="POST" action="{{ route('stammdaten.produkte.grundsortiment.store') }}"
          style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap">
        @csrf
        <button type="submit" @disabled($vorschau['neu'] === 0)>
            {{ $vorschau['neu'] }} Artikel anlegen
        </button>
        <a href="{{ route('stammdaten.produkte.index') }}" class="knopf knopf-schlicht">Abbrechen</a>

        @if ($vorschau['neu'] === 0)
            <span class="gedaempft">Es ist bereits alles vorhanden — es gibt nichts zu tun.</span>
        @endif
    </form>
@endsection
