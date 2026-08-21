{{--
    Die Rezeptzeilen einer Gruppe.

    Ausgelagert, weil sie zweimal gebraucht werden: einmal unter „Favoriten"
    und einmal in der Küche des Rezepts. Zwei Kopien wären zwei Stellen, an
    denen die nächste Änderung nur zur Hälfte ankommt.
--}}
<div class="tabellenrahmen">
    <table>
        <tbody>
            @foreach ($eintraege as $rezept)
                <tr>
                    <td style="width:2.4rem">
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
            @endforeach
        </tbody>
    </table>
</div>
