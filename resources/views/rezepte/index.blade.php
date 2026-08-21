@extends('layouts.app')
@section('title', 'Rezepte')

@section('inhalt')
    @php
        /*
         * Wer sucht, will Treffer sehen -- keine zugeklappten Überschriften.
         * Zugeklappt ist der Zustand fürs Stöbern; sobald ein Filter greift,
         * stehen die Gruppen offen. Ohne das wirkt jede Suche wie eine, die
         * nichts gefunden hat.
         */
        $filterAktiv = $suche !== '' || $gewaehlteTags !== [] || $nurFavoriten;
    @endphp

    <style>
        /* Dasselbe Muster wie die Artikelliste nach Warengruppen -- wer das
           dort kennt, muss hier nichts Neues lernen. */
        .kuechenblock { margin-top: 1.1rem; }
        .kuechenblock > summary { cursor: pointer; display: flex; align-items: baseline;
            gap: 0.6rem; padding: 0.25rem 0 0.2rem; color: var(--primaer);
            font-size: 1.15rem; font-weight: 600; border-bottom: 1px solid var(--rand); }
        .kuechenblock > summary:hover { border-bottom-color: var(--primaer); }
        .kuechenblock .stand { margin-left: auto; font-size: 0.85rem; font-weight: 400;
            color: var(--gedaempft); }
        .kuechenblock:not([open]) > summary { color: var(--gedaempft);
            border-bottom-style: dashed; }
        /* Was keine Küche hat, ist keine Küche, sondern eine Arbeitsliste --
           Terrakotta trennt das sichtbar vom Rest. */
        .block-ohne > summary { color: var(--akzent); border-bottom-color: var(--akzent); }
    </style>

    {{-- Die beiden Wege, wie ein Rezept hereinkommt, gehören nach oben und
         sichtbar. Als graue Fußnote unter dem Filter lasen sie sich wie eine
         Randbemerkung -- dabei sind sie der Grund, warum jemand diese Seite
         zum ersten Mal öffnet. --}}
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <h1 style="margin-bottom:0">Rezepte</h1>
        <p style="margin:0;display:flex;gap:0.5rem;flex-wrap:wrap">
            {{-- „Rezept KI" trägt den Akzent, „Neues Rezept" tritt dafür
                 zurück. Zwei gefüllte Knöpfe nebeneinander heben sich
                 gegenseitig auf -- hervorgehoben ist nur, was allein
                 hervorsticht. Terrakotta ist laut Palette die Farbe für
                 genau das. --}}
            <a href="{{ route('rezepte.import') }}" class="knopf knopf-akzent" style="margin:0">Rezept KI</a>
            <a href="{{ route('rezepte.create') }}" class="knopf knopf-schlicht" style="margin:0">Neues Rezept</a>
        </p>
    </div>

    <form method="GET" action="{{ route('rezepte.index') }}">
        <div style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap">
            <input name="suche" type="search" value="{{ $suche }}" style="margin:0;flex:1 1 12rem"
                   placeholder="Name, Anmerkung oder Zutat">
            {{-- Ein Häkchen ist eine Entscheidung, kein Entwurf: Es wirkt
                 sofort, statt auf einen zweiten Klick auf „Suchen" zu warten.
                 Ohne JavaScript bleibt genau dieser Knopf der Weg. --}}
            <label style="margin:0;font-weight:400">
                <input type="checkbox" name="favoriten" value="1" @checked($nurFavoriten)
                       style="width:auto" onchange="this.form.submit()"> nur Favoriten
            </label>
            <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.35rem 0.7rem">Suchen</button>
            @if ($suche !== '' || $gewaehlteTags !== [] || $nurFavoriten)
                <a href="{{ route('rezepte.index') }}" class="gedaempft" style="font-size:0.85rem">zurücksetzen</a>
            @endif
        </div>

        @foreach ($tags as $typ => $gruppe)
            <p style="margin:0.6rem 0 0">
                <span class="gedaempft" style="font-size:0.85rem">{{ \App\Modules\Recipes\Models\Tag::TYPEN[$typ] ?? $typ }}:</span>
                @foreach ($gruppe as $tag)
                    <label style="margin:0 0.6rem 0 0;font-weight:400;display:inline">
                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                               @checked(in_array($tag->id, $gewaehlteTags, true))
                               style="width:auto" onchange="this.form.submit()">
                        {{ $tag->name }}
                    </label>
                @endforeach
            </p>
        @endforeach
    </form>

    <p class="gedaempft" style="margin-top:1rem">
        {{ $rezepte->count() }} {{ $rezepte->count() === 1 ? 'Rezept' : 'Rezepte' }}
        @if ($kuechen->count() > 1)
            in {{ $kuechen->count() }} Küchen
        @endif
    </p>

    @if ($rezepte->isEmpty())
        <p class="gedaempft">
            {{ $suche !== '' || $gewaehlteTags !== [] || $nurFavoriten ? 'Nichts gefunden.' : 'Noch kein Rezept angelegt.' }}
        </p>
    @endif

    {{-- Favoriten stehen doppelt: hier zum Zugreifen, unten in ihrer Küche
         zum Vergleichen. Die Gruppe fehlt, wenn es keine gibt -- eine leere
         Überschrift ist kein Angebot, sondern ein Vorwurf. --}}
    @if ($favoriten->isNotEmpty() && ! $nurFavoriten)
        <details class="kuechenblock">
            <summary>
                <span>★ Favoriten</span>
                <span class="stand">{{ $favoriten->count() }}</span>
            </summary>
            @include('rezepte.partials.liste', ['eintraege' => $favoriten])
        </details>
    @endif

    @foreach ($kuechen as $name => $gruppe)
        {{-- Ohne Filter zugeklappt: Die Seite beginnt als Überblick über die
             Küchen, nicht als Wand aus Rezeptzeilen. Mit Filter offen -- sonst
             verstecken sich die Treffer hinter den Überschriften. --}}
        <details class="kuechenblock @if ($name === \App\Http\Controllers\RecipeController::OHNE_KUECHE) block-ohne @endif"
                 @if ($filterAktiv) open @endif>
            <summary>
                <span>{{ $name }}</span>
                <span class="stand">{{ $gruppe->count() }}</span>
            </summary>
            @include('rezepte.partials.liste', ['eintraege' => $gruppe])
        </details>
    @endforeach
@endsection
