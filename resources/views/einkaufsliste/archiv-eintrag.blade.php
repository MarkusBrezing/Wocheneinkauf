@extends('layouts.app')
@section('title', $liste->anzeigename())

@section('inhalt')
    <h1>{{ $liste->anzeigename() }}</h1>

    <p class="gedaempft">
        Archiviert am {{ $liste->archived_at?->format('d.m.Y') }} um {{ $liste->archived_at?->format('H:i') }}
        @if ($liste->market) · {{ $liste->market->name }} @endif
    </p>

    @forelse ($abschnitte as $abschnitt)
        <h2>{{ $abschnitt->name }}</h2>
        <ul>
            @foreach ($abschnitt->items as $item)
                <li @class(['gedaempft' => $item->is_checked])
                    style="{{ $item->is_checked ? 'text-decoration:line-through' : '' }}">
                    @if ($item->mengeAnzeige() !== ''){{ $item->mengeAnzeige() }} @endif{{ $item->raw_name }}
                    @if ($item->note)<span class="gedaempft">— {{ $item->note }}</span>@endif
                    @if (! $item->is_checked)<span class="gedaempft" style="font-size:0.8rem">· hat gefehlt</span>@endif
                </li>
            @endforeach
        </ul>
    @empty
        <p class="gedaempft">Dieser Einkauf hat keine Einträge.</p>
    @endforelse

    <h2>Weiterverwenden</h2>
    <p class="gedaempft">
        Die Artikel werden der aktuellen Liste hinzugefügt, nicht an ihre Stelle
        gesetzt. Was dort schon offen steht, wird zusammengeführt.
    </p>
    <form method="POST" action="{{ route('einkaufsliste.archiv.laden', $liste) }}">
        @csrf
        <button type="submit">In die aktuelle Liste laden</button>
        <a href="{{ route('einkaufsliste.archiv.index') }}" class="knopf knopf-schlicht">Zurück zum Archiv</a>
    </form>
@endsection
