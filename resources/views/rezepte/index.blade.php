@extends('layouts.app')
@section('title', 'Rezepte')

@section('inhalt')
    <h1>Rezepte</h1>

    <form method="GET" action="{{ route('rezepte.index') }}">
        <div style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap">
            <input name="suche" type="search" value="{{ $suche }}" style="margin:0;flex:1 1 12rem"
                   placeholder="Name, Anmerkung oder Zutat">
            <label style="margin:0;font-weight:400">
                <input type="checkbox" name="favoriten" value="1" @checked($nurFavoriten) style="width:auto"> nur Favoriten
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
                               @checked(in_array($tag->id, $gewaehlteTags, true)) style="width:auto">
                        {{ $tag->name }}
                    </label>
                @endforeach
            </p>
        @endforeach
    </form>

    <p class="gedaempft" style="margin-top:1rem">
        {{ $rezepte->total() }} {{ $rezepte->total() === 1 ? 'Rezept' : 'Rezepte' }}
        · <a href="{{ route('rezepte.create') }}">Neues Rezept</a>
    </p>

    <div class="tabellenrahmen">
        <table>
            <tbody>
                @forelse ($rezepte as $rezept)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('rezepte.favorit', $rezept) }}" class="inline">
                                @csrf
                                <button type="submit" class="knopf-schlicht"
                                        style="margin:0;padding:0.1rem 0.4rem;font-size:1.1rem"
                                        title="{{ $rezept->is_favorite ? 'Kein Favorit mehr' : 'Als Favorit merken' }}"
                                        aria-label="Favorit">{{ $rezept->is_favorite ? '★' : '☆' }}</button>
                            </form>
                        </td>
                        <td>
                            <a href="{{ route('rezepte.show', $rezept) }}"><strong>{{ $rezept->name }}</strong></a>
                            <span class="gedaempft" style="font-size:0.85rem">
                                {{ $rezept->servings }} Portionen · {{ $rezept->aufwandLabel() }}
                                @if ($rezept->gesamtzeit()) · {{ $rezept->gesamtzeit() }} min @endif
                                · {{ $rezept->ingredients_count }} Zutaten
                            </span>
                            @if ($rezept->tags->isNotEmpty())
                                <div class="gedaempft" style="font-size:0.8rem">
                                    {{ $rezept->tags->pluck('name')->join(' · ') }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="gedaempft">
                        {{ $suche !== '' || $gewaehlteTags !== [] ? 'Nichts gefunden.' : 'Noch kein Rezept angelegt.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rezepte->links() }}
@endsection
