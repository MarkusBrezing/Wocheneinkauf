@extends('layouts.app')
@section('title', 'Bestand des Grundvorrats')

@section('inhalt')
    {{-- Der Bogen soll sich auch ausdrucken lassen: dann wird die Bestandsspalte
         zur Schreiblinie und alles Bedienbare verschwindet. --}}
    <style>
        @media print {
            nav, .nicht-drucken, .hinweis, button { display: none; }
            body { padding: 0; max-width: none; }
            input[type=number] { border: none; border-bottom: 1px solid var(--rand); width: 6rem; }
            input[type=checkbox] { appearance: none; width: 1rem; height: 1rem; border: 1px solid var(--rand); }
        }
    </style>

    <h1>Bestand des Grundvorrats</h1>

    <p class="gedaempft nicht-drucken">
        Trage ein, was noch da ist. Was zur Sollmenge fehlt, kommt auf die
        Einkaufsliste. <strong>Ein leeres Feld heißt „nicht gezählt"</strong> —
        nur eine <strong>0</strong> heißt „nichts mehr da". So lässt sich die
        Zählung unterbrechen, ohne dass ungeprüfte Artikel auf der Liste landen.
    </p>

    <form method="POST" action="{{ route('einkaufsliste.inventur.store') }}">
        @csrf

        <div class="tabellenrahmen">
            <table>
                <thead>
                    <tr>
                        <th>Artikel</th>
                        <th style="width:6rem">Soll</th>
                        <th style="width:8rem">Bestand</th>
                        <th style="width:5rem">fehlt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vorrat as $artikel)
                        @php $soll = $artikel->sollmengeAnzeige(); @endphp
                        <tr>
                            <td>
                                {{ $artikel->name }}
                                <span class="gedaempft" style="font-size:0.8rem">{{ $artikel->productGroup?->label }}</span>
                            </td>
                            <td class="gedaempft">{{ $soll !== '' ? $soll : '—' }}</td>
                            <td>
                                @if ($artikel->pantry_target_amount !== null)
                                    <input type="number" name="bestand[{{ $artikel->id }}]" step="0.001" min="0"
                                           style="margin:0" aria-label="Bestand {{ $artikel->name }}"
                                           placeholder="{{ $artikel->default_unit }}">
                                @else
                                    <span class="gedaempft">ohne Sollmenge</span>
                                @endif
                            </td>
                            <td>
                                <label style="margin:0;font-weight:400">
                                    <input type="checkbox" name="fehlt[{{ $artikel->id }}]" value="1" style="width:auto"
                                           aria-label="{{ $artikel->name }} fehlt ganz">
                                </label>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="gedaempft">
                            Es ist kein Grundvorrat hinterlegt. Im
                            <a href="{{ route('stammdaten.produkte.index') }}">Artikelkatalog</a> lässt sich
                            jeder Artikel als Grundvorrat kennzeichnen und mit einer Sollmenge versehen.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($vorrat->isNotEmpty())
            <button type="submit">Fehlmengen auf die Liste</button>
            <a href="{{ route('einkaufsliste.index') }}" class="knopf knopf-schlicht nicht-drucken">Zurück zur Liste</a>
        @endif
    </form>
@endsection
