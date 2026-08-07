{{--
    Vorschläge aus dem Artikelkatalog für jedes Eingabefeld mit
    list="artikel-vorschlaege".

    Zwei Stufen, damit auch ohne JavaScript etwas passiert:

    1. Die datalist ist serverseitig gefüllt. Wer "Mil" tippt, bekommt
       "Vollmilch" vorgeschlagen — das kann der Browser allein.
    2. Das Skript darunter kann zweierlei mehr: Es schränkt die Vorschläge auf
       die **gewählte Warengruppe** ein (Feld mit data-gruppe="#auswahl"), und
       es trennt eine vorangestellte Menge ab. Letzteres bleibt, obwohl die
       Maske ein eigenes Mengenfeld hat: Wer aus Gewohnheit "2 l Mil…" in das
       Namensfeld tippt, soll trotzdem einen Treffer bekommen.

    Der Katalog steht genau einmal im Quelltext, nicht einmal je Warengruppe.
--}}
@props(['artikel'])

@php
    // Die Ansicht darf sowohl eine Liste von Namen als auch Artikelmodelle
    // liefern -- gebraucht wird beides als {name, gruppe}.
    $eintraege = collect($artikel)->map(fn ($a) => is_string($a)
        ? ['name' => $a, 'gruppe' => null]
        : ['name' => $a->name, 'gruppe' => $a->product_group_id]
    )->values();
@endphp

<datalist id="artikel-vorschlaege">
    @foreach ($eintraege as $eintrag)
        <option value="{{ $eintrag['name'] }}"></option>
    @endforeach
</datalist>

@if ($eintraege->isNotEmpty())
    <script type="application/json" id="artikel-katalog">@json($eintraege)</script>
    <script>
        (function () {
            const quelle = document.getElementById('artikel-katalog');
            const liste = document.getElementById('artikel-vorschlaege');

            if (!quelle || !liste) {
                return;
            }

            const artikel = JSON.parse(quelle.textContent);

            // Dasselbe Verständnis von "Menge vorn" wie ItemTextParser:
            // Zahl, Bereich oder Bruch, optional gefolgt von einer Einheit.
            const menge = /^\s*((?:\d+(?:[.,]\d+)?(?:\s*[-–]\s*\d+(?:[.,]\d+)?)?|\d+\s*\/\s*\d+)\s*[a-zA-ZäöüÄÖÜß]*\.?\s+)(.*)$/;

            // Ohne Akzente vergleichen: Wer "creme" tippt, sucht die
            // "Crème fraîche" und nicht deren Schreibweise.
            const schlicht = (text) => text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

            /** Die gewählte Warengruppe des Feldes -- oder null. */
            function gruppeVon(feld) {
                const auswahl = feld.dataset.gruppe ? document.querySelector(feld.dataset.gruppe) : null;

                return auswahl && auswahl.value ? Number(auswahl.value) : null;
            }

            function aktualisieren(feld) {
                const treffer = menge.exec(feld.value);
                const praefix = treffer ? treffer[1] : '';
                const gesucht = (treffer ? treffer[2] : feld.value).trim().toLowerCase();
                const gruppe = gruppeVon(feld);

                // Ist eine Warengruppe gewählt, kommen nur deren Artikel in
                // Frage. Das ist der eigentliche Gewinn der Auswahl: Sie
                // sortiert nicht nur ein, sie hilft beim Finden.
                const grundmenge = gruppe === null
                    ? artikel
                    : artikel.filter((a) => a.gruppe === gruppe);

                // Unter zwei Zeichen wird nicht gefiltert, sondern der Anfang
                // gezeigt. Bei gewählter Warengruppe ist das bereits eine
                // brauchbare Auswahl -- ohne sie nur die ersten zwölf Namen.
                const passend = gesucht.length < 2
                    ? grundmenge.slice(0, 12)
                    : grundmenge.filter((a) => schlicht(a.name).includes(schlicht(gesucht))).slice(0, 12);

                liste.replaceChildren(...passend.map((a) => {
                    // Bewusst über die DOM-Schnittstelle statt innerHTML:
                    // Artikelnamen sind Nutzereingaben.
                    const option = document.createElement('option');
                    option.value = praefix + a.name;

                    return option;
                }));
            }

            document.querySelectorAll('input[list="artikel-vorschlaege"]').forEach((feld) => {
                feld.addEventListener('input', () => aktualisieren(feld));
                // Beim Hineinklicken gleich die Artikel der gewählten
                // Warengruppe zeigen, ohne dass etwas getippt sein muss.
                feld.addEventListener('focus', () => aktualisieren(feld));

                const auswahl = feld.dataset.gruppe ? document.querySelector(feld.dataset.gruppe) : null;

                if (auswahl) {
                    auswahl.addEventListener('change', () => aktualisieren(feld));
                }
            });
        })();
    </script>
@endif
