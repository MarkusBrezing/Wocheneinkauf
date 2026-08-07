@extends('layouts.app')
@section('title', 'Neue Woche')

@section('inhalt')
    <h1>Neue Woche</h1>

    <p class="gedaempft">
        Der Assistent schließt den laufenden Einkauf ab und schreibt den
        Grundvorrat auf die neue Liste. Die Rezeptauswahl, die derselbe Use Case
        vorsieht, kommt mit dem Rezeptmodul.
    </p>

    <form method="POST" action="{{ route('einkaufsliste.neue-woche.start') }}">
        @csrf

        <h2>Laufender Einkauf</h2>
        @if ($offen + $erledigt === 0)
            <p class="gedaempft">Die aktuelle Liste ist leer — es gibt nichts zu archivieren.</p>
        @else
            <p class="gedaempft">
                Auf der aktuellen Liste stehen {{ $offen }} offene und {{ $erledigt }} erledigte Artikel.
            </p>
            <label style="font-weight:400">
                <input type="checkbox" name="archivieren" value="1" checked style="width:auto">
                Aktuelle Liste archivieren
            </label>
            <label style="font-weight:400">
                <input type="checkbox" name="offene_uebernehmen" value="1" checked style="width:auto">
                Die {{ $offen }} fehlenden Artikel auf die neue Liste übernehmen
            </label>
        @endif

        <label for="name">Name der neuen Liste <span class="gedaempft">(optional)</span></label>
        <input id="name" name="name" type="text" value="{{ old('name') }}"
               placeholder="Woche ab {{ now()->format('d.m.Y') }}">

        @if ($vorlagen->isNotEmpty())
            <h2>Vorlagen</h2>
            <p class="gedaempft">
                Die Standardvorlage (★) ist vorausgewählt — sie kommt sonst
                automatisch auf jede neue Liste.
            </p>
            @foreach ($vorlagen as $vorlage)
                <label style="font-weight:400">
                    <input type="checkbox" name="vorlagen[]" value="{{ $vorlage->id }}"
                           @checked($vorlage->is_default) style="width:auto">
                    {{ $vorlage->name }}@if ($vorlage->is_default) ★ @endif
                    <span class="gedaempft">{{ $vorlage->items_count }} Artikel</span>
                </label>
            @endforeach
        @endif

        <h2>Grundvorrat</h2>
        <p class="gedaempft">
            Alle ausgewählten Artikel kommen mit ihrer Sollmenge auf die Liste.
            Wenn du stattdessen erst nachsehen willst, was noch da ist, führt die
            <a href="{{ route('einkaufsliste.inventur') }}">Bestandsaufnahme</a>
            nur die Fehlmengen auf.
        </p>
        @forelse ($grundvorrat as $artikel)
            <label style="font-weight:400">
                <input type="checkbox" name="grundvorrat[]" value="{{ $artikel->id }}" checked style="width:auto">
                @if ($artikel->sollmengeAnzeige() !== '')
                    <strong>{{ $artikel->sollmengeAnzeige() }}</strong>
                @endif
                {{ $artikel->name }}
                <span class="gedaempft">{{ $artikel->productGroup?->label }}</span>
            </label>
        @empty
            <p class="gedaempft">
                Es ist kein Grundvorrat hinterlegt. Im
                <a href="{{ route('stammdaten.produkte.index') }}">Artikelkatalog</a> lässt sich jeder
                Artikel als Grundvorrat kennzeichnen.
            </p>
        @endforelse

        <button type="submit">Neue Woche starten</button>
        <a href="{{ route('einkaufsliste.index') }}" class="knopf knopf-schlicht">Abbrechen</a>
    </form>
@endsection
