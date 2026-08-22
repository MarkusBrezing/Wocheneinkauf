{{--
    Nachträge bemerken, während man im Laden steht.

    Der typische Fall: Einer steht vor dem Regal, jemand zu Hause fällt noch
    etwas ein und schreibt es auf die Liste. Ohne dieses Skript merkt der
    Einkaufende davon nichts -- die Seite wurde beim Betreten des Ladens
    geladen und zeigt bis zum Verlassen denselben Stand.

    Bewusst KEIN stilles Neuladen: Wer gerade den Daumen über einer Zeile hat,
    tippt sonst auf einen Artikel, der eine Sekunde vorher noch woanders stand.
    Stattdessen ein Hinweis, der wartet, bis er angetippt wird.
--}}
<div class="nachschub" id="nachschub" hidden>
    <button type="button" id="nachschub-knopf"></button>
</div>

<script>
    (function () {
        const marke = document.querySelector('meta[name="csrf-token"]');
        const streifen = document.getElementById('nachschub');
        const knopf = document.getElementById('nachschub-knopf');

        if (!marke || !window.fetch || !streifen) {
            return;
        }

        /*
         * Der Stand, den diese Seite zeigt. Beides wandert mit: Die Version
         * erkennt jede Änderung, die Gesamtzahl unterscheidet "jemand hat
         * etwas nachgetragen" von "jemand hat zu Hause etwas abgehakt".
         */
        let version = @json($liste->version);
        let gesamt = @json($gesamt);

        /*
         * 25 Sekunden. Häufiger kostet Akku und Datenvolumen an einer Stelle,
         * an der die Verbindung ohnehin schlecht ist; seltener merkt man den
         * Nachtrag erst an der Kasse.
         */
        const ABSTAND = 25000;

        // Das eigene Abhaken zählt die Version hoch. Ohne diese Meldung hielte
        // der Abgleich jeden eigenen Tipper für eine fremde Änderung.
        window.addEventListener('einkauf:eigene-aenderung', function (ereignis) {
            if (ereignis.detail) {
                version = ereignis.detail.version ?? version;
                gesamt = ereignis.detail.gesamt ?? gesamt;
            }
        });

        function zaehlerSetzen(daten) {
            const zaehler = document.querySelector('[data-zaehler]');
            const balken = document.querySelector('[data-balken]');

            if (zaehler) {
                zaehler.textContent = daten.erledigt + ' von ' + daten.gesamt;
            }

            if (balken) {
                balken.style.width = (daten.gesamt > 0
                    ? Math.round(daten.erledigt / daten.gesamt * 100)
                    : 0) + '%';
            }
        }

        function melden(neue) {
            knopf.textContent = neue > 0
                ? (neue === 1 ? '1 Artikel kam dazu · anzeigen' : neue + ' Artikel kamen dazu · anzeigen')
                : 'Die Liste wurde geändert · neu laden';
            streifen.hidden = false;
        }

        knopf.addEventListener('click', function () {
            window.location.reload();
        });

        function nachsehen() {
            // Im Flugmodus oder mit der Seite in der Tasche gar nicht erst
            // fragen -- das spart Akku und erzeugt keine Fehlerlawine.
            if (document.visibilityState !== 'visible' || navigator.onLine === false) {
                return;
            }

            fetch(@json(route('einkaufsliste.sync.puls')), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (antwort) {
                    if (!antwort.ok) {
                        throw new Error(antwort.status);
                    }
                    return antwort.json();
                })
                .then(function (daten) {
                    // Der Zähler stimmt auch dann wieder, wenn jemand zu Hause
                    // etwas abgehakt hat.
                    zaehlerSetzen(daten);

                    if (daten.version === version) {
                        return;
                    }

                    melden(daten.gesamt - gesamt);
                })
                .catch(function () {
                    // Im Laden bricht die Verbindung ständig ab. Ein Hinweis
                    // darauf alle 25 Sekunden wäre schlimmer als die Stille.
                });
        }

        setInterval(nachsehen, ABSTAND);

        // Wer das Telefon aus der Tasche holt, will den Stand von jetzt sehen
        // und nicht erst in 25 Sekunden.
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                nachsehen();
            }
        });
    })();
</script>
