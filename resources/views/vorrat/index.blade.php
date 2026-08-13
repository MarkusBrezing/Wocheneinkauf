@extends('layouts.app')
@section('title', 'Vorrat')

@section('inhalt')
    <style>
        body { max-width: 72rem; }
        .vorrat { font-size: 0.9rem; }
        .vorrat th, .vorrat td { padding: 0.35rem 0.5rem; vertical-align: middle; }
        .vorrat thead th { background: var(--flaeche); border-bottom: 2px solid var(--primaer); white-space: nowrap; }
        .vorrat tbody tr:nth-child(even) { background: var(--flaeche); }
        .vorrat input[type=number], .vorrat input[type=text], .vorrat input[type=date], .vorrat select {
            margin: 0; padding: 0.25rem 0.4rem; background: var(--hintergrund); }
        .vorrat .schmal { width: 5.5rem; }
        .vorrat button { margin: 0; padding: 0.2rem 0.55rem; font-size: 0.85rem; }
        .knapp { color: var(--akzent); font-weight: 600; }
        .kachel { display: inline-block; padding: 0.5rem 0.9rem; border-radius: 6px;
                  background: var(--flaeche); margin-right: 0.5rem; font-size: 0.9rem; }
    </style>

    <h1>Vorrat</h1>

    <p class="gedaempft">
        Was im Haus ist, mit Soll und Meldebestand. Der Bestand wird nie direkt
        gesetzt, sondern gebucht — so bleibt nachvollziehbar, warum aus drei
        Dosen zwei wurden.
    </p>

    <p>
        <span class="kachel">{{ $positionen->flatten()->count() }} Positionen</span>
        @if ($knapp > 0)
            <span class="kachel" style="background: var(--akzent-hell); color: var(--akzent)">
                {{ $knapp }} unter Meldebestand
            </span>
        @endif
        @if ($laeuftAb > 0)
            <span class="kachel">{{ $laeuftAb }} laufen bald ab</span>
        @endif
        <a href="{{ route('einkaufsliste.inventur') }}" class="knopf knopf-schlicht" style="margin:0">Bestandsaufnahme</a>
    </p>

    <form method="GET" action="{{ route('vorrat.index') }}"
          style="display:flex;gap:0.6rem;align-items:flex-end;flex-wrap:wrap">
        <div>
            <label for="ort">Ort</label>
            <select id="ort" name="ort" style="margin:0;width:auto">
                <option value="">— alle —</option>
                @foreach ($orte as $eintrag)
                    <option value="{{ $eintrag }}" @selected($ort === $eintrag)>{{ $eintrag }}</option>
                @endforeach
            </select>
        </div>
        <label style="margin:0;font-weight:400;align-self:center">
            <input type="checkbox" name="knapp" value="1" @checked($nurKnapp) style="width:auto"> nur knappe
        </label>
        <button type="submit" class="knopf-schlicht" style="margin:0">Anwenden</button>
    </form>

    @forelse ($positionen as $ortName => $gruppe)
        <h2>{{ $ortName }}</h2>

        <div class="tabellenrahmen">
            <table class="vorrat">
                <thead>
                    <tr>
                        <th>Artikel</th><th class="schmal">Bestand</th><th class="schmal">Soll</th>
                        <th class="schmal">Melde&shy;bestand</th><th class="schmal">MHD</th>
                        <th style="width:13rem">Buchen</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gruppe as $position)
                        <tr @class(['knapp-zeile' => $position->istKnapp()])>
                            <td>
                                {{ $position->product?->name ?? '—' }}
                                @if ($position->istKnapp())
                                    <span class="knapp">· knapp</span>
                                @endif
                                @if ($position->laeuftAb())
                                    <span class="knapp">· läuft ab</span>
                                @endif
                            </td>
                            <td><strong>{{ $position->bestandAnzeige() ?: '0' }}</strong></td>
                            <td>
                                <input form="p{{ $position->id }}" name="target_qty" type="number"
                                       step="0.001" min="0" value="{{ $position->target_qty }}" required>
                            </td>
                            <td>
                                <input form="p{{ $position->id }}" name="min_qty" type="number"
                                       step="0.001" min="0" value="{{ $position->min_qty }}">
                            </td>
                            <td>
                                <input form="p{{ $position->id }}" name="best_before" type="date"
                                       value="{{ $position->best_before?->format('Y-m-d') }}">
                            </td>
                            <td>
                                {{-- Bestandsbuchung: Menge und Grund, nicht der neue Wert. --}}
                                <form method="POST" action="{{ route('vorrat.buchen', $position) }}"
                                      style="display:flex;gap:0.3rem">
                                    @csrf
                                    <input name="delta" type="number" step="0.001" required
                                           style="width:5rem" placeholder="±1" aria-label="Menge">
                                    <select name="reason" style="width:auto" aria-label="Grund">
                                        <option value="purchase">Einkauf</option>
                                        <option value="consumption">Verbrauch</option>
                                        <option value="correction">Korrektur</option>
                                        <option value="expired">abgelaufen</option>
                                    </select>
                                    <button type="submit" class="knopf-schlicht">Buchen</button>
                                </form>
                            </td>
                            <td style="white-space:nowrap">
                                <input form="p{{ $position->id }}" type="hidden" name="location" value="{{ $position->location }}">
                                <input form="p{{ $position->id }}" type="hidden" name="unit" value="{{ $position->unit }}">
                                <input form="p{{ $position->id }}" type="hidden" name="is_active" value="1">
                                <button form="p{{ $position->id }}" type="submit" class="knopf-schlicht">Speichern</button>
                                <a href="{{ route('vorrat.verlauf', $position) }}" class="gedaempft"
                                   style="font-size:0.85rem">Verlauf</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @foreach ($gruppe as $position)
            <form id="p{{ $position->id }}" method="POST" action="{{ route('vorrat.update', $position) }}" hidden>
                @csrf @method('PUT')
            </form>
        @endforeach
    @empty
        <p class="gedaempft">Noch keine Vorratsposition angelegt.</p>
    @endforelse

    <h2>Fehlmengen auf die Einkaufsliste</h2>
    <p class="gedaempft">
        Alles, was unter dem Soll liegt, kommt mit der Differenz auf die Liste —
        zusammengeführt mit dem, was schon draufsteht.
    </p>
    <form method="POST" action="{{ route('vorrat.auf-die-liste') }}">
        @csrf
        <button type="submit">Fehlmengen übernehmen</button>
    </form>

    <h2>Position aufnehmen</h2>
    <form method="POST" action="{{ route('vorrat.store') }}">
        @csrf

        <div style="display:flex;gap:0.8rem;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:1 1 14rem">
                <label for="product_id">Artikel</label>
                <select id="product_id" name="product_id" required style="margin:0">
                    @foreach ($artikel as $eintrag)
                        <option value="{{ $eintrag->id }}">{{ $eintrag->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="location">Ort</label>
                <input id="location" name="location" type="text" value="Vorrat" style="margin:0;width:9rem">
            </div>
            <div>
                <label for="unit">Einheit</label>
                <input id="unit" name="unit" type="text" style="margin:0;width:6rem" placeholder="Pck">
            </div>
            <div>
                <label for="target_qty">Soll</label>
                <input id="target_qty" name="target_qty" type="number" step="0.001" min="0" value="1"
                       required style="margin:0;width:6rem">
            </div>
            <div>
                <label for="min_qty">Meldebestand</label>
                <input id="min_qty" name="min_qty" type="number" step="0.001" min="0" value="0"
                       style="margin:0;width:6rem">
            </div>
            <div>
                <label for="current_qty">Ist jetzt</label>
                <input id="current_qty" name="current_qty" type="number" step="0.001" min="0"
                       style="margin:0;width:6rem">
            </div>
            <button type="submit" style="margin:0">Aufnehmen</button>
        </div>
    </form>
@endsection
