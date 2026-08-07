@extends('layouts.app')
@section('title', 'Liste drucken')

@section('inhalt')
    {{-- C-15: Auf Papier gehoert die Bedienoberflaeche nicht mit aufs Blatt. --}}
    <style>
        @media print {
            nav, .nicht-drucken, .hinweis { display: none; }
            body { padding: 0; max-width: none; }
            a { text-decoration: none; color: inherit; }
        }
    </style>

    <h1>{{ $liste->anzeigename() }}</h1>

    <p class="gedaempft">
        Stand {{ now()->format('d.m.Y H:i') }}
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
                    @if ($item->verfuegbarkeitshinweis())<span class="gedaempft">({{ $item->verfuegbarkeitshinweis() }})</span>@endif
                </li>
            @endforeach
        </ul>
    @empty
        <p class="gedaempft">Die Liste ist leer.</p>
    @endforelse

    <div class="nicht-drucken">
        <h2>Als Text teilen</h2>
        <p class="gedaempft">Zum Kopieren in eine Nachricht:</p>
        <textarea rows="{{ min(24, substr_count($alsText, "\n") + 2) }}" readonly
                  style="width:100%;padding:0.5rem;border:1px solid var(--rand);border-radius:6px;
                         background:var(--hintergrund);color:inherit;font:inherit">{{ $alsText }}</textarea>

        <p style="margin-top:1rem">
            <a href="{{ route('einkaufsliste.index') }}" class="knopf knopf-schlicht">Zurück zur Liste</a>
        </p>
    </div>
@endsection
