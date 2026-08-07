@extends('layouts.app')
@section('title', $rezept->name)

@section('inhalt')
    @php $faktor = $rezept->servings > 0 ? $portionen / $rezept->servings : 1.0; @endphp

    <h1>{{ $rezept->name }}</h1>

    <p class="gedaempft">
        {{ $rezept->servings }} Portionen · {{ $rezept->aufwandLabel() }}
        @if ($rezept->prep_minutes) · {{ $rezept->prep_minutes }} min Vorbereitung @endif
        @if ($rezept->cook_minutes) · {{ $rezept->cook_minutes }} min Garzeit @endif
        @if ($rezept->plant_points) · {{ $rezept->plant_points }} Pflanzenpunkte @endif
        · <a href="{{ route('rezepte.edit', $rezept) }}">Bearbeiten</a>
        · <a href="{{ route('rezepte.index') }}">Alle Rezepte</a>
    </p>

    @if ($rezept->tags->isNotEmpty())
        <p class="gedaempft">{{ $rezept->tags->pluck('name')->join(' · ') }}</p>
    @endif

    {{-- D-04: Auf die Einkaufsliste, mit Portionsskalierung und Auswahl --}}
    <h2>Auf die Einkaufsliste</h2>
    <form method="GET" action="{{ route('rezepte.show', $rezept) }}"
          style="display:flex;gap:0.4rem;align-items:flex-end;flex-wrap:wrap">
        <div>
            <label for="portionen">Portionen</label>
            <input id="portionen" name="portionen" type="number" min="1" max="99" value="{{ $portionen }}"
                   style="margin:0;width:6rem">
        </div>
        <button type="submit" class="knopf-schlicht" style="margin:0">Mengen umrechnen</button>
    </form>

    <form method="POST" action="{{ route('rezepte.auf-die-liste', $rezept) }}" style="margin-top:0.8rem">
        @csrf
        <input type="hidden" name="portionen" value="{{ $portionen }}">

        <p class="gedaempft">
            Vorratszutaten sind abgewählt — was im Schrank steht, muss nicht mit.
        </p>

        <div class="tabellenrahmen">
            <table>
                <tbody>
                    @forelse ($rezept->ingredients as $zutat)
                        <tr>
                            <td style="width:2rem">
                                <input type="checkbox" name="zutaten[]" value="{{ $zutat->id }}"
                                       @checked(! $zutat->is_pantry) style="width:auto"
                                       aria-label="{{ $zutat->raw_name }} auf die Liste">
                            </td>
                            <td>
                                @if ($zutat->mengeAnzeige($faktor) !== '')
                                    <strong>{{ $zutat->mengeAnzeige($faktor) }}</strong>
                                @endif
                                {{ $zutat->raw_name }}
                                @if ($zutat->is_optional)<span class="gedaempft">(optional)</span>@endif
                                @if ($zutat->is_pantry)<span class="gedaempft" style="font-size:0.8rem">· Vorrat</span>@endif
                                @if ($zutat->buy_label)
                                    <span class="gedaempft" style="font-size:0.8rem">· kaufen als „{{ $zutat->buy_label }}"</span>
                                @endif
                                @if ($zutat->product)
                                    <span class="gedaempft" style="font-size:0.8rem">· im Katalog</span>
                                @endif

                                <details style="margin-top:0.2rem">
                                    <summary class="gedaempft" style="cursor:pointer;font-size:0.85rem">bearbeiten</summary>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="gedaempft">Noch keine Zutaten erfasst.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rezept->ingredients->isNotEmpty())
            <button type="submit">Ausgewählte auf die Liste</button>
        @endif
    </form>

    {{-- Zutaten pflegen (D-03, D-08). Bewusst außerhalb des Übernahmeformulars:
         verschachtelte Formulare sind in HTML nicht erlaubt. --}}
    <h2>Zutaten pflegen</h2>

    @foreach ($rezept->ingredients as $zutat)
        <form method="POST" action="{{ route('rezepte.zutaten.update', $zutat) }}"
              style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;margin-bottom:0.4rem">
            @csrf
            @method('PUT')
            <input name="text" type="text" value="{{ trim($zutat->mengeAnzeige().' '.$zutat->raw_name) }}" required
                   style="margin:0;width:auto" aria-label="Menge und Zutat">
            <input name="buy_label" type="text" value="{{ $zutat->buy_label }}" placeholder="kaufen als"
                   style="margin:0;width:auto" aria-label="Einkaufsname">
            <input name="merge_key" type="text" value="{{ $zutat->merge_key }}" placeholder="Sammelbegriff"
                   style="margin:0;width:9rem" aria-label="Sammelbegriff">
            <label style="margin:0;font-weight:400">
                <input type="checkbox" name="is_pantry" value="1" @checked($zutat->is_pantry) style="width:auto"> Vorrat
            </label>
            <label style="margin:0;font-weight:400">
                <input type="checkbox" name="is_optional" value="1" @checked($zutat->is_optional) style="width:auto"> optional
            </label>
            <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.3rem 0.6rem">Speichern</button>
        </form>
        <form method="POST" action="{{ route('rezepte.zutaten.destroy', $zutat) }}" style="margin:-0.2rem 0 0.8rem"
              onsubmit="return confirm('„{{ $zutat->raw_name }}“ entfernen?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.15rem 0.5rem;font-size:0.85rem">Entfernen</button>
        </form>
    @endforeach

    <form method="POST" action="{{ route('rezepte.zutaten.store', $rezept) }}"
          style="display:flex;gap:0.4rem;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div style="flex:1 1 12rem">
            <label for="text">Zutat hinzufügen</label>
            <input id="text" name="text" type="text" required placeholder="200 g Feta" style="margin:0">
        </div>
        <label style="margin:0;font-weight:400">
            <input type="checkbox" name="is_pantry" value="1" style="width:auto"> Vorrat
        </label>
        <label style="margin:0;font-weight:400">
            <input type="checkbox" name="is_optional" value="1" style="width:auto"> optional
        </label>
        <button type="submit" style="margin:0">Hinzufügen</button>
    </form>

    @if ($rezept->instructions)
        <h2>Zubereitung</h2>
        <div style="white-space:pre-wrap">{{ $rezept->instructions }}</div>
    @endif

    @if ($rezept->notes)
        <h2>Anmerkungen</h2>
        <p style="white-space:pre-wrap">{{ $rezept->notes }}</p>
    @endif

    @if ($rezept->nutrition)
        <h2>Nährwerte</h2>
        <p class="gedaempft">
            @foreach ($rezept->nutrition as $bezeichnung => $wert)
                {{ ucfirst($bezeichnung) }}: {{ $wert }}@if (! $loop->last) · @endif
            @endforeach
        </p>
    @endif

    <h2>Schlagworte</h2>
    <form method="POST" action="{{ route('rezepte.schlagworte', $rezept) }}">
        @csrf
        @method('PUT')

        @foreach ($tags as $typ => $gruppe)
            <p style="margin:0.4rem 0">
                <span class="gedaempft" style="font-size:0.85rem">{{ \App\Modules\Recipes\Models\Tag::TYPEN[$typ] ?? $typ }}:</span>
                @foreach ($gruppe as $tag)
                    <label style="margin:0 0.6rem 0 0;font-weight:400;display:inline">
                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                               @checked($rezept->tags->contains($tag)) style="width:auto"> {{ $tag->name }}
                    </label>
                @endforeach
            </p>
        @endforeach

        <div style="display:flex;gap:0.4rem;align-items:flex-end;flex-wrap:wrap">
            <div>
                <label for="neuer_tag">Neues Schlagwort</label>
                <input id="neuer_tag" name="neuer_tag" type="text" style="margin:0" placeholder="vegetarisch">
            </div>
            <div>
                <label for="neuer_tag_typ">Art</label>
                <select id="neuer_tag_typ" name="neuer_tag_typ" style="margin:0;width:auto">
                    @foreach (\App\Modules\Recipes\Models\Tag::TYPEN as $wert => $label)
                        <option value="{{ $wert }}" @selected($wert === 'other')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="knopf-schlicht" style="margin:0">Speichern</button>
        </div>
    </form>

    <h2>Rezept löschen</h2>
    <form method="POST" action="{{ route('rezepte.destroy', $rezept) }}"
          onsubmit="return confirm('Rezept „{{ $rezept->name }}“ löschen?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="knopf-schlicht">Löschen</button>
    </form>
@endsection
