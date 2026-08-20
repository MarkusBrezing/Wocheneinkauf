{{--
    Abhaken ohne Seitenneuaufbau.

    Ohne dieses Skript wird jedes Häkchen als Formular abgeschickt: Anfrage,
    Umleitung, ganze Seite neu, Sprung zum Anker. Beim Einkaufen tippt man das
    hundertmal, und im Laden mit schwachem Empfang wartet man jedes Mal.

    Das Formular bleibt vollständig erhalten und funktioniert ohne JavaScript
    genauso wie vorher -- hier wird nur das Absenden abgefangen.

    Die Anzeige wird sofort umgestellt, noch bevor die Antwort da ist. Kommt
    ein Fehler zurück, wird sie zurückgedreht: Ein Haken, der stehen bleibt,
    obwohl er nicht gespeichert wurde, wäre schlimmer als ein langsamer.
--}}
<script>
    (function () {
        const marke = document.querySelector('meta[name="csrf-token"]');

        /*
         * In der Einkaufsansicht steht nur, was noch fehlt -- Abgehaktes
         * verschwindet. Vor dem Umbau erledigte das der Seitenneuaufbau; ohne
         * ihn muss die Zeile von Hand weg, sonst bleibt sie durchgestrichen
         * stehen und die Liste wird beim Einkaufen immer unübersichtlicher.
         *
         * Auf der Pflegeseite am PC bleibt alles stehen: Dort wird die Liste
         * zusammengestellt, nicht abgearbeitet.
         */
        const ausblenden = @json($ausblenden ?? false);
        const zeigtErledigte = @json($zeigtErledigte ?? false);

        if (!marke || !window.fetch) {
            return; // Ohne CSRF-Marke oder fetch bleibt es beim Formular.
        }

        function umschalten(formular) {
            const zeile = formular.closest('[data-haken-zeile]') || formular;
            const knopf = formular.querySelector('button');
            const kaestchen = formular.querySelector('[data-kaestchen]') || knopf;

            if (formular.dataset.laeuft === '1') {
                return; // Doppeltes Tippen nicht doppelt zaehlen.
            }
            formular.dataset.laeuft = '1';

            const vorher = zeile.classList.contains('ist-abgehakt');
            anzeigen(zeile, kaestchen, !vorher);

            fetch(formular.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': marke.content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(formular),
            })
                .then(function (antwort) {
                    if (!antwort.ok) {
                        throw new Error(antwort.status);
                    }
                    return antwort.json();
                })
                .then(function (daten) {
                    anzeigen(zeile, kaestchen, daten.abgehakt);
                    zaehlerSetzen(daten);

                    if (ausblenden && daten.abgehakt) {
                        entfernen(zeile);
                    }
                })
                .catch(function () {
                    // Zurueckdrehen und den Anwender nicht im Unklaren lassen.
                    anzeigen(zeile, kaestchen, vorher);
                    formular.classList.add('haken-fehler');
                    setTimeout(function () {
                        formular.classList.remove('haken-fehler');
                    }, 1500);
                })
                .finally(function () {
                    formular.dataset.laeuft = '';
                });
        }

        function anzeigen(zeile, kaestchen, abgehakt) {
            zeile.classList.toggle('ist-abgehakt', abgehakt);

            if (kaestchen) {
                kaestchen.textContent = abgehakt ? '☑' : '☐';
            }
        }

        /*
         * Kurz stehen lassen, dann ausblenden.
         *
         * Sofortiges Verschwinden liest sich wie ein Fehlgriff -- man sieht
         * nicht mehr, was man getroffen hat. Eine halbe Sekunde reicht, um
         * den Haken zu sehen, und ist kurz genug, um nicht zu stören.
         */
        function entfernen(zeile) {
            zeile.classList.add('geht-weg');

            setTimeout(function () {
                const bereich = zeile.dataset.bereich;

                zeile.remove();

                // Ein Regalbereich ohne Artikel ist eine Überschrift ohne
                // Inhalt. Die Zeilen sind Geschwister der Überschrift, keine
                // Kinder -- deshalb wird über die Kennung gezählt.
                if (bereich) {
                    const rest = document.querySelectorAll('[data-bereich="' + bereich + '"]');
                    const ueberschrift = document.getElementById(bereich);

                    if (rest.length === 0 && ueberschrift) {
                        ueberschrift.remove();
                    }
                }

                // War das der letzte Artikel, soll der Schlusssatz erscheinen
                // ("Alles abgehakt"). Den baut der Server -- einmal je
                // Einkauf ist ein Neuaufbau dafür vertretbar.
                if (document.querySelectorAll('[data-haken]').length === 0) {
                    window.location.reload();
                }
            }, 500);
        }

        function zaehlerSetzen(daten) {
            const zaehler = document.querySelector('[data-zaehler]');
            const balken = document.querySelector('[data-balken]');

            if (zaehler) {
                zaehler.textContent = daten.erledigt + ' von ' + daten.gesamt;
            }

            if (balken) {
                const anteil = daten.gesamt > 0 ? Math.round(daten.erledigt / daten.gesamt * 100) : 0;
                balken.style.width = anteil + '%';
                const rahmen = balken.parentElement;
                if (rahmen) {
                    rahmen.setAttribute('aria-valuenow', daten.erledigt);
                    rahmen.setAttribute('aria-valuemax', daten.gesamt);
                }
            }

            schalterSetzen(daten);
        }

        /*
         * Der Schalter "N Abgehaktes zeigen" trägt eine Zahl, die sich mit
         * jedem Haken ändert -- und er erscheint überhaupt erst mit dem ersten.
         * Beides muss hier nachgezogen werden, sonst bliebe er entweder
         * unsichtbar oder stünde auf einem alten Stand.
         *
         * In der Ansicht MIT Abgehaktem heißt er "Abgehaktes ausblenden",
         * trägt keine Zahl und bleibt immer sichtbar: Er ist dort der Rückweg.
         */
        function schalterSetzen(daten) {
            const schalter = document.querySelector('[data-erledigt-schalter]');
            const link = document.querySelector('[data-erledigt-link]');

            if (!schalter || !link) {
                return; // Die Pflegeseite am PC hat diesen Schalter nicht.
            }

            if (zeigtErledigte) {
                schalter.hidden = false;

                return;
            }

            schalter.hidden = daten.erledigt === 0;
            link.textContent = daten.erledigt + ' Abgehaktes zeigen';
        }

        document.addEventListener('submit', function (ereignis) {
            const formular = ereignis.target;

            if (formular.matches('[data-haken]')) {
                ereignis.preventDefault();
                umschalten(formular);
            }
        });

        /*
         * Die ganze Zeile als Ziel -- auf dem PC ist das Kaestchen sonst
         * etwa 20 mal 22 Pixel gross, und die Zeilen sollen kompakt bleiben.
         * Ausgenommen ist alles, was selbst schon anklickbar ist: der
         * Bearbeiten-Griff, Links, Eingabefelder.
         */
        document.addEventListener('click', function (ereignis) {
            const zeile = ereignis.target.closest('[data-haken-zeile]');

            if (!zeile || ereignis.target.closest('a, button, input, select, textarea, summary, details, label')) {
                return;
            }

            // Wer Text markiert, will ihn kopieren, nicht abhaken. Ohne diese
            // Abfrage setzt schon ein Doppelklick auf einen Artikelnamen den
            // Haken -- und gleich wieder zurueck.
            const markierung = window.getSelection();

            if (markierung && markierung.toString().length > 0) {
                return;
            }

            const formular = zeile.querySelector('[data-haken]');

            if (formular) {
                ereignis.preventDefault();
                umschalten(formular);
            }
        });
    })();
</script>
