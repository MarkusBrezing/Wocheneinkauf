@extends('layouts.app')
@section('title', 'Inventurbericht')

@section('inhalt')
    <h1>{{ $inventur->anzeigename() }}</h1>

    <p class="gedaempft">
        {{ $inventur->status === 'completed' ? 'Abgeschlossen' : 'Abgebrochen' }}
        am {{ $inventur->completed_at?->format('d.m.Y \u\m H:i') }} ·
        {{ $inventur->umfangLabel() }} · {{ $gezaehlt }} von {{ $gesamt }} gezählt
        @if ($inventur->starter) · gestartet von {{ $inventur->starter->name }} @endif
    </p>

    @if ($inventur->status === 'cancelled')
        <p class="hinweis">Dieser Lauf wurde abgebrochen — die Bestände sind unverändert geblieben.</p>
    @endif

    @foreach ($zeilen as $ort => $gruppe)
        <h2>{{ $ort }}</h2>

        <div class="tabellenrahmen">
            <table>
                <thead>
                    <tr>
                        <th>Artikel</th><th style="width:6rem">Soll</th><th style="width:6rem">erwartet</th>
                        <th style="width:6rem">gezählt</th><th style="width:7rem">Abweichung</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gruppe as $zeile)
                        <tr>
                            <td>{{ $zeile->pantryItem->product?->name ?? '—' }}</td>
                            <td class="gedaempft">{{ \App\Modules\ShoppingList\Models\ShoppingListItem::formatiereMenge($zeile->target_qty) }}</td>
                            <td class="gedaempft">{{ \App\Modules\ShoppingList\Models\ShoppingListItem::formatiereMenge($zeile->expected_qty) }}</td>
                            <td>
                                @if ($zeile->istGezaehlt())
                                    <strong>{{ \App\Modules\ShoppingList\Models\ShoppingListItem::formatiereMenge($zeile->counted_qty) }}</strong>
                                @else
                                    <span class="gedaempft">nicht gezählt</span>
                                @endif
                            </td>
                            <td>
                                @if ($zeile->istGezaehlt() && (float) $zeile->difference !== 0.0)
                                    <span style="color: var(--akzent); font-weight:600">
                                        {{ (float) $zeile->difference > 0 ? '+' : '' }}{{ \App\Modules\ShoppingList\Models\ShoppingListItem::formatiereMenge(abs((float) $zeile->difference)) }}
                                        {{ (float) $zeile->difference > 0 ? '' : '−' }}
                                    </span>
                                @elseif ($zeile->istGezaehlt())
                                    <span class="gedaempft">stimmt</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @if ($inventur->status === 'completed')
        <h2>Fehlmengen auf die Einkaufsliste</h2>
        <p class="gedaempft">
            Grundlage ist die gezählte Menge: Was zum Soll fehlt, kommt mit der
            Differenz auf die Liste.
        </p>
        <form method="POST" action="{{ route('vorrat.inventur.auf-die-liste', $inventur) }}">
            @csrf
            <button type="submit">Fehlmengen übernehmen</button>
        </form>
    @endif

    <p style="margin-top:1.5rem">
        <a href="{{ route('vorrat.inventur.index') }}" class="knopf knopf-schlicht">Zurück zur Übersicht</a>
        <a href="{{ route('vorrat.index') }}" class="knopf knopf-schlicht">Zum Vorrat</a>
    </p>
@endsection
