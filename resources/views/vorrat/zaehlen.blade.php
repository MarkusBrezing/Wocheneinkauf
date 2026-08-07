@extends('layouts.app')
@section('title', 'Zählen')

@section('inhalt')
    <style>
        .zaehlzeile { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;
                      padding: 0.5rem 0; border-bottom: 1px solid var(--rand); }
        .zaehlzeile input[type=number] { margin: 0; width: 6.5rem; min-height: 44px; }
        .zaehlzeile button { margin: 0; min-height: 44px; }
        .zaehlzeile .artikel { flex: 1 1 12rem; font-size: 1.05rem; }
        .gezaehlt { background: var(--primaer-hell); }
        .erwartet { color: var(--gedaempft); font-size: 0.85rem; }
        .ortkopf { position: sticky; top: 0; background: var(--flaeche); color: var(--primaer);
                   padding: 0.3rem 0.5rem; margin: 1.2rem -0.5rem 0.3rem; font-size: 0.95rem; }
    </style>

    <h1>{{ $inventur->anzeigename() }}</h1>

    <p class="gedaempft">
        {{ $gezaehlt }} von {{ $gesamt }} gezählt · {{ $inventur->umfangLabel() }}
        @if ($offen > 0) · {{ $offen }} offen @endif
    </p>

    <p class="gedaempft">
        Ein leeres Feld heißt „noch nicht gezählt" — mehrere Personen können
        gleichzeitig zählen und sehen beim Laden, was die anderen schon
        eingetragen haben.
    </p>

    @foreach ($zeilen as $ort => $gruppe)
        <h2 class="ortkopf" id="o{{ $loop->index }}">{{ $ort }}</h2>

        @foreach ($gruppe as $zeile)
            <form method="POST" action="{{ route('vorrat.inventur.zaehlen', $zeile) }}"
                  class="zaehlzeile @if ($zeile->istGezaehlt()) gezaehlt @endif">
                @csrf
                @method('PUT')
                <input type="hidden" name="anker" value="o{{ $loop->parent->index }}">

                <span class="artikel">
                    {{ $zeile->pantryItem->product?->name ?? '—' }}
                    <span class="erwartet">
                        · erwartet {{ \App\Modules\ShoppingList\Models\ShoppingListItem::formatiereMenge($zeile->expected_qty) }}
                        {{ $zeile->pantryItem->unit }}
                        @if ($zeile->counter) · gezählt von {{ $zeile->counter->name }} @endif
                    </span>
                </span>

                <input name="counted_qty" type="number" step="0.001" min="0"
                       value="{{ $zeile->counted_qty }}"
                       placeholder="{{ $zeile->pantryItem->unit ?: 'Stück' }}"
                       aria-label="Gezählt {{ $zeile->pantryItem->product?->name }}">
                <button type="submit" class="knopf-schlicht">Übernehmen</button>
            </form>
        @endforeach
    @endforeach

    <h2>Abschließen</h2>
    <p class="gedaempft">
        Beim Abschluss werden die gezählten Mengen zum neuen Bestand — jede über
        eine Buchung mit Grund „Inventur". Was niemand gezählt hat, bleibt
        unverändert.
    </p>

    <form method="POST" action="{{ route('vorrat.inventur.abschliessen', $inventur) }}"
          onsubmit="return confirm('Inventur abschließen? {{ $offen }} Positionen sind ungezählt und bleiben unverändert.')">
        @csrf
        <button type="submit">Inventur abschließen</button>
    </form>

    <form method="POST" action="{{ route('vorrat.inventur.abbrechen', $inventur) }}"
          style="margin-top:0.5rem"
          onsubmit="return confirm('Inventur abbrechen? Die Bestände bleiben unverändert.')">
        @csrf
        <button type="submit" class="knopf-schlicht">Abbrechen</button>
    </form>
@endsection
