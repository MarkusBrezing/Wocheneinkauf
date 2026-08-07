@extends('layouts.app')
@section('title', 'Verlauf')

@section('inhalt')
    <h1>{{ $position->product?->name ?? 'Vorratsposition' }}</h1>

    <p class="gedaempft">
        {{ $position->location }} · Bestand {{ $position->bestandAnzeige() ?: '0' }}
        von {{ $position->sollAnzeige() }}
        @if ($position->counted_at) · zuletzt gezählt {{ $position->counted_at->format('d.m.Y') }} @endif
    </p>

    <div class="tabellenrahmen">
        <table>
            <thead>
                <tr><th style="width:9rem">Wann</th><th style="width:6rem">Menge</th><th>Grund</th><th>Wer</th></tr>
            </thead>
            <tbody>
                @forelse ($buchungen as $buchung)
                    <tr>
                        <td class="gedaempft">{{ $buchung->created_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            <strong>{{ (float) $buchung->qty_delta > 0 ? '+' : '−' }}{{ \App\Modules\ShoppingList\Models\ShoppingListItem::formatiereMenge(abs((float) $buchung->qty_delta)) }}</strong>
                        </td>
                        <td>{{ $buchung->grundLabel() }}</td>
                        <td class="gedaempft">{{ $buchung->creator?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="gedaempft">Noch keine Buchung.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p style="margin-top:1.5rem">
        <a href="{{ route('vorrat.index') }}" class="knopf knopf-schlicht">Zurück zum Vorrat</a>
    </p>
@endsection
