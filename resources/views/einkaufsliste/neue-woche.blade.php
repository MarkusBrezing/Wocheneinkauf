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

        @if ($grundvorrat->isNotEmpty())
            {{-- Drei Möglichkeiten statt 145 Häkchen. Vorher gab es nur die
                 Kästchen, alle vorausgewählt — „gar nichts" hätte bedeutet,
                 jedes einzeln abzuwählen, und das tut niemand.

                 Maßgeblich ist diese Wahl, nicht die Kästchen darunter: Ohne
                 JavaScript bleiben sie sichtbar und angehakt, und wer „Keinen"
                 wählt, bekommt trotzdem keinen. --}}
            <p class="gedaempft">
                Was vom Grundvorrat auf die neue Liste soll. Wenn du erst
                nachsehen willst, was noch da ist, führt die
                <a href="{{ route('einkaufsliste.inventur') }}">Bestandsaufnahme</a>
                nur die Fehlmengen auf.
            </p>

            <div class="vorrat-modus">
                <label style="font-weight:400">
                    <input type="radio" name="grundvorrat_modus" value="alle" checked style="width:auto">
                    <strong>Alle</strong> {{ $grundvorrat->count() }} Artikel mit ihrer Sollmenge
                </label>
                <label style="font-weight:400">
                    <input type="radio" name="grundvorrat_modus" value="auswahl" style="width:auto">
                    <strong>Eine Auswahl</strong> — unten ankreuzen
                </label>
                <label style="font-weight:400">
                    <input type="radio" name="grundvorrat_modus" value="keine" style="width:auto">
                    <strong>Keinen</strong> — die Liste beginnt ohne Grundvorrat
                </label>
            </div>

            <fieldset id="grundvorrat-auswahl" style="border:1px solid var(--rand);border-radius:8px;padding:0.8rem 1rem;margin:0.8rem 0">
                <legend class="gedaempft" style="font-size:0.9rem;padding:0 0.4rem">
                    Auswahl
                    <a href="#" data-alle="1">alle</a> ·
                    <a href="#" data-alle="0">keine</a>
                </legend>

                @foreach ($grundvorrat as $artikel)
                    <label style="font-weight:400">
                        <input type="checkbox" name="grundvorrat[]" value="{{ $artikel->id }}" checked style="width:auto">
                        @if ($artikel->sollmengeAnzeige() !== '')
                            <strong>{{ $artikel->sollmengeAnzeige() }}</strong>
                        @endif
                        {{ $artikel->name }}
                        <span class="gedaempft">{{ $artikel->productGroup?->label }}</span>
                    </label>
                @endforeach
            </fieldset>
        @else
            <p class="gedaempft">
                Es ist kein Grundvorrat hinterlegt. Im
                <a href="{{ route('stammdaten.produkte.index') }}">Artikelkatalog</a> lässt sich jeder
                Artikel als Grundvorrat kennzeichnen.
            </p>
        @endif

        <button type="submit">Neue Woche starten</button>
        <a href="{{ route('einkaufsliste.index') }}" class="knopf knopf-schlicht">Abbrechen</a>
    </form>

    <style>
        .vorrat-modus { border: 1px solid var(--rand); border-radius: 8px;
            padding: 0.7rem 1rem; margin: 0.6rem 0; background: var(--flaeche); }
        .vorrat-modus label { margin: 0.15rem 0; }
        /* Die Auswahlliste tritt zurück, solange sie nicht gilt -- sichtbar
           bleibt sie trotzdem, damit man sieht, worum es geht. */
        #grundvorrat-auswahl[disabled] { opacity: 0.45; }
        #grundvorrat-auswahl legend a { font-size: 0.85rem; }
    </style>

    <script>
        (function () {
            const kasten = document.getElementById('grundvorrat-auswahl');

            if (!kasten) {
                return;
            }

            const modi = document.querySelectorAll('input[name="grundvorrat_modus"]');

            /*
             * Die Liste ist nur im Modus "Auswahl" scharf. disabled statt
             * hidden: So sieht man weiterhin, worum es geht, und der Browser
             * schickt die Häkchen gar nicht erst mit, wenn sie nicht gelten.
             */
            function nachfuehren() {
                const gewaehlt = document.querySelector('input[name="grundvorrat_modus"]:checked');

                kasten.disabled = !gewaehlt || gewaehlt.value !== 'auswahl';
            }

            modi.forEach(function (feld) {
                feld.addEventListener('change', nachfuehren);
            });

            // „alle" und „keine" innerhalb der Auswahl -- bei 145 Artikeln ist
            // das der Unterschied zwischen benutzbar und nicht.
            kasten.querySelectorAll('[data-alle]').forEach(function (link) {
                link.addEventListener('click', function (ereignis) {
                    ereignis.preventDefault();

                    const an = link.dataset.alle === '1';

                    kasten.querySelectorAll('input[type=checkbox]').forEach(function (kaestchen) {
                        kaestchen.checked = an;
                    });
                });
            });

            nachfuehren();
        })();
    </script>
@endsection
