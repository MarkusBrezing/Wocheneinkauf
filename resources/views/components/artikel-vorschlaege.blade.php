{{--
    Vorschläge aus dem Artikelkatalog für jedes Eingabefeld mit
    list="artikel-vorschlaege".

    Zwei Stufen, damit auch ohne JavaScript etwas passiert:

    1. Die datalist ist serverseitig gefüllt. Wer "Mil" tippt, bekommt
       "Vollmilch" vorgeschlagen — das kann der Browser allein.
    2. Mit JavaScript ersetzt eine eigene Auswahlliste die datalist. Die
       datalist allein war der Grund, warum Artikel "nicht zu finden" waren:
       Sie öffnet sich nicht beim Hineinklicken, zeigt keine Warengruppe,
       ist auf dem Telefon kaum zu treffen -- und der Browser filtert die
       Vorschläge ein zweites Mal nach eigenen Regeln, sodass "creme" die
       "Crème fraîche" wieder verlor, die das Skript gerade gefunden hatte.

    Die eigene Liste
      - sortiert nach Passung statt nach Alphabet: exakt, dann Wortanfang,
        dann irgendwo im Wort, zuletzt mit einem Tippfehler,
      - findet ohne Akzente, mit "ae" statt "ä" und in Ein- wie Mehrzahl,
      - zeigt je Treffer die Warengruppe und ob er schon offen auf der Liste
        steht,
      - stellt Artikel der gewählten Warengruppe nach vorn, blendet die
        übrigen aber nicht aus,
      - endet mit "„…“ neu aufschreiben", damit sichtbar ist, wann ein
        Eintrag ohne Katalogbezug entsteht.

    Ein gewählter Vorschlag setzt die Warengruppe (data-gruppe="#auswahl"),
    trägt die Einheit des Artikels als Platzhalter ins Mengenfeld
    (data-menge="#feld") und springt dorthin. Optional nimmt ein Element
    (data-hinweis="#element") eine Zeile auf, woher der Eintrag kommt.

    Der Katalog steht genau einmal im Quelltext, nicht einmal je Feld.
--}}
@props(['artikel', 'gruppen' => [], 'vorhanden' => []])

@php
    // Die Ansicht darf sowohl eine Liste von Namen als auch Artikelmodelle
    // liefern -- gebraucht wird beides als {name, gruppe, einheit}.
    $eintraege = collect($artikel)->map(fn ($a) => is_string($a)
        ? ['name' => $a, 'gruppe' => null, 'einheit' => null]
        : ['name' => $a->name, 'gruppe' => $a->product_group_id, 'einheit' => $a->default_unit]
    )->values();

    $katalog = [
        'artikel' => $eintraege,
        'gruppen' => collect($gruppen)->map(fn ($g) => ['id' => $g->id, 'label' => $g->label])->values(),
        'vorhanden' => collect($vorhanden)->values(),
    ];
@endphp

<datalist id="artikel-vorschlaege">
    @foreach ($eintraege as $eintrag)
        <option value="{{ $eintrag['name'] }}"></option>
    @endforeach
</datalist>

@if ($eintraege->isNotEmpty())
    <style>
        .av-liste { position: absolute; z-index: 40; max-height: min(22rem, 60vh); overflow-y: auto;
            padding: 0.2rem 0; border: 1px solid var(--rand); border-radius: 6px;
            background: var(--hintergrund); box-shadow: 0 6px 18px rgba(28, 29, 27, 0.16); }
        /* Wird es eng, rutscht die rechte Seite unter den Namen, statt ihn
           Buchstabe für Buchstabe umzubrechen. */
        .av-option { display: flex; flex-wrap: wrap; align-items: center; gap: 0.15rem 0.75rem;
            padding: 0.5rem 0.75rem; cursor: pointer; }
        .av-option:hover { background: var(--flaeche); }
        .av-option.aktiv { background: var(--primaer-hell); }
        .av-name { flex: 1 1 9rem; min-width: 0; overflow-wrap: break-word; }
        .av-name mark { background: none; color: inherit; font-weight: 600;
            text-decoration: underline; text-decoration-color: var(--primaer); }
        .av-rechts { margin-left: auto; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 0.2rem 0.5rem;
            align-items: center; font-size: 0.82rem; color: var(--gedaempft); text-align: right; }
        .av-rechts span { white-space: nowrap; }
        .av-drauf { padding: 0 0.45rem; border-radius: 3px; border: 1px solid var(--primaer);
            background: var(--primaer-hell); color: var(--primaer); }
        .av-neu .av-name { color: var(--primaer); }
        .av-trenner { padding: 0.45rem 0.75rem 0.15rem; font-size: 0.8rem; color: var(--gedaempft); }
        .av-trenner:not(:first-child) { margin-top: 0.2rem; border-top: 1px solid var(--rand); }
        /* Mit dem Daumen braucht eine Zeile mehr Höhe als mit der Maus. */
        @media (pointer: coarse) { .av-option { padding: 0.75rem; } }
    </style>
    <script type="application/json" id="artikel-katalog">@json($katalog)</script>
    <script>
        (function () {
            const quelle = document.getElementById('artikel-katalog');

            if (!quelle) {
                return;
            }

            const daten = JSON.parse(quelle.textContent);

            // Vergleichsform: ohne Akzente, "ä" wie "ae", "ß" wie "ss". Wer
            // "creme" oder "kaese" tippt, sucht die Sache, nicht deren
            // Schreibweise.
            const schlicht = (text) => text.toLowerCase().replace(/ß/g, 'ss')
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/ae/g, 'a').replace(/oe/g, 'o').replace(/ue/g, 'u')
                .replace(/\s+/g, ' ').trim();

            // Grob die Mehrzahl abschneiden: "Tomate" findet "Tomaten" und
            // umgekehrt. Kurze Wörter bleiben, "Reis" ist kein "Rei".
            const stamm = (wort) => wort.length > 4 ? wort.replace(/(en|n|e|s)$/, '') : wort;

            const artikel = daten.artikel.map((a) => {
                const s = schlicht(a.name);

                return { ...a, s, woerter: s.split(/[\s-]+/) };
            });
            const gruppen = new Map(daten.gruppen.map((g) => [g.id, g.label]));
            const vorhanden = new Map(daten.vorhanden.map((v) => [schlicht(v.name), v.menge]));
            const alphabetisch = (x, y) => x.name.localeCompare(y.name, 'de');

            // Dasselbe Verständnis von "Menge vorn" wie ItemTextParser:
            // Zahl, Bereich oder Bruch, optional gefolgt von einer Einheit.
            const menge = /^\s*((?:\d+(?:[.,]\d+)?(?:\s*[-–]\s*\d+(?:[.,]\d+)?)?|\d+\s*\/\s*\d+)\s*[a-zA-ZäöüÄÖÜß]*\.?\s+)(.*)$/;

            /** Kleinster Abstand von q zu irgendeinem Stück von t (Tippfehler). */
            function abstand(q, t) {
                let vorher = new Array(t.length + 1).fill(0);

                for (let i = 1; i <= q.length; i++) {
                    const zeile = [i];

                    for (let j = 1; j <= t.length; j++) {
                        zeile[j] = Math.min(vorher[j] + 1, zeile[j - 1] + 1,
                            vorher[j - 1] + (q[i - 1] === t[j - 1] ? 0 : 1));
                    }

                    vorher = zeile;
                }

                return Math.min(...vorher);
            }

            const TIPPFEHLER = 25;

            /** Wie gut passt ein Artikel? 0 heißt: gar nicht. */
            function passung(q, a) {
                const sq = stamm(q);

                if (a.s === q || a.s === sq) return 100;
                if (a.s.startsWith(q)) return 85;
                if (a.woerter.some((w) => w.startsWith(q))) return 70;
                if (sq !== q && (a.s.startsWith(sq) || a.woerter.some((w) => w.startsWith(sq)))) return 65;
                if (a.s.includes(q)) return 50;
                if (sq.length >= 4 && a.s.includes(sq)) return 45;
                if (q.length >= 4 && abstand(q, a.s) <= (q.length >= 7 ? 2 : 1)) return TIPPFEHLER;

                return 0;
            }

            const ziel = (feld, schluessel) => feld.dataset[schluessel]
                ? document.querySelector(feld.dataset[schluessel])
                : null;

            /** Die Warengruppe, die jemand selbst gewählt hat -- oder null. */
            function gruppeVon(feld) {
                const auswahl = ziel(feld, 'gruppe');

                return auswahl && auswahl.value !== '' && !auswahl.dataset.avAuto
                    ? Number(auswahl.value)
                    : null;
            }

            function teile(feld) {
                const treffer = menge.exec(feld.value);

                return {
                    praefix: treffer ? treffer[1] : '',
                    suche: (treffer ? treffer[2] : feld.value).trim(),
                };
            }

            // Eine Auswahlliste für alle Felder der Seite.
            const liste = document.createElement('div');
            liste.id = 'artikel-auswahl';
            liste.className = 'av-liste';
            liste.setAttribute('role', 'listbox');
            liste.hidden = true;
            document.body.appendChild(liste);

            let feldAktuell = null;
            let eintraege = [];
            let aktiv = -1;

            function platzieren(feld) {
                const rahmen = feld.getBoundingClientRect();
                const sichtbar = document.documentElement.clientWidth;
                const breite = Math.min(Math.max(rahmen.width, 288), sichtbar - 16);
                const links = Math.max(8, Math.min(rahmen.left, sichtbar - breite - 8));

                liste.style.width = breite + 'px';
                liste.style.left = (links + window.scrollX) + 'px';
                liste.style.top = (rahmen.bottom + window.scrollY + 4) + 'px';
            }

            function trenner(text) {
                const element = document.createElement('div');
                element.className = 'av-trenner';
                element.setAttribute('role', 'presentation');
                element.textContent = text;

                return element;
            }

            /** Den Namen mit hervorgehobenem Suchstück -- über Textknoten, Namen sind Nutzereingaben. */
            function name(text, suche) {
                const element = document.createElement('span');
                element.className = 'av-name';
                const stelle = suche ? text.toLowerCase().indexOf(suche.toLowerCase()) : -1;

                if (stelle < 0) {
                    element.textContent = text;

                    return element;
                }

                const mark = document.createElement('mark');
                mark.textContent = text.slice(stelle, stelle + suche.length);
                element.append(text.slice(0, stelle), mark, text.slice(stelle + suche.length));

                return element;
            }

            function option(eintrag, suche) {
                const index = eintraege.push(eintrag) - 1;
                const element = document.createElement('div');
                element.className = 'av-option' + (eintrag.neu !== undefined ? ' av-neu' : '');
                element.id = 'av-option-' + index;
                element.dataset.index = index;
                element.setAttribute('role', 'option');

                const rechts = document.createElement('span');
                rechts.className = 'av-rechts';

                if (eintrag.artikel) {
                    const a = eintrag.artikel;
                    element.append(name(a.name, suche));

                    if (vorhanden.has(a.s)) {
                        const drauf = document.createElement('span');
                        drauf.className = 'av-drauf';
                        const schon = vorhanden.get(a.s);
                        drauf.textContent = 'steht drauf' + (schon ? ' · ' + schon : '');
                        rechts.append(drauf);
                    }

                    const gruppe = document.createElement('span');
                    gruppe.textContent = gruppen.get(a.gruppe) ?? 'ohne Warengruppe';
                    rechts.append(gruppe);
                } else {
                    element.append(name('+ „' + eintrag.neu + '“ neu aufschreiben', ''));
                    const gruppe = document.createElement('span');
                    gruppe.textContent = 'Warengruppe automatisch';
                    rechts.append(gruppe);
                }

                element.append(rechts);

                return element;
            }

            function zeigen(feld) {
                const { suche } = teile(feld);
                const q = schlicht(suche);
                const gruppe = gruppeVon(feld);
                const kinder = [];
                eintraege = [];

                if (q === '' && gruppe === null) {
                    kinder.push(trenner('Tippen, um im Katalog zu suchen. „sahne“ findet auch Schlagsahne, „kaese“ den Frischkäse.'));
                } else {
                    let eigene;
                    let andere = [];

                    if (q === '') {
                        // Nur eine Warengruppe gewählt: deren Artikel, alle.
                        eigene = artikel.filter((a) => a.gruppe === gruppe).sort(alphabetisch);
                    } else {
                        const bewertet = artikel
                            .map((a) => ({ a, p: passung(q, a) }))
                            .filter((t) => t.p > 0);
                        // Tippfehler-Treffer nur, wenn sonst nichts passt:
                        // "milch" soll keinen "Mischbrot" vorschlagen,
                        // "jogurt" aber den "Naturjoghurt" finden.
                        const genau = bewertet.some((t) => t.p > TIPPFEHLER);
                        const treffer = bewertet
                            .filter((t) => !genau || t.p > TIPPFEHLER)
                            .sort((x, y) => y.p - x.p || x.a.name.length - y.a.name.length || alphabetisch(x.a, y.a))
                            .map((t) => t.a);

                        // Die gewählte Gruppe stellt nach vorn, versteckt aber
                        // nichts. Wer die Sahne bei den Milchprodukten sucht,
                        // findet sie auch, wenn sie woanders einsortiert ist.
                        eigene = gruppe === null ? treffer : treffer.filter((a) => a.gruppe === gruppe);
                        andere = gruppe === null ? [] : treffer.filter((a) => a.gruppe !== gruppe);
                    }

                    if (gruppe !== null) {
                        kinder.push(trenner((gruppen.get(gruppe) ?? 'Gewählte Warengruppe') + (eigene.length ? '' : ' · kein Treffer')));
                    }

                    eigene.slice(0, 30).forEach((a) => kinder.push(option({ artikel: a }, suche)));

                    if (andere.length) {
                        kinder.push(trenner('Aus anderen Warengruppen'));
                        andere.slice(0, 15).forEach((a) => kinder.push(option({ artikel: a }, suche)));
                    }

                    if (q !== '' && !artikel.some((a) => a.s === q)) {
                        if (kinder.length) {
                            kinder.push(trenner(''));
                        }

                        kinder.push(option({ neu: suche }, suche));
                    }
                }

                aktiv = Math.min(aktiv, eintraege.length - 1);
                liste.replaceChildren(...kinder);
                markieren(feld);

                feldAktuell = feld;
                liste.hidden = false;
                feld.setAttribute('aria-expanded', 'true');
                platzieren(feld);
            }

            function markieren(feld) {
                liste.querySelectorAll('.av-option').forEach((element) => {
                    const an = Number(element.dataset.index) === aktiv;
                    element.classList.toggle('aktiv', an);
                    element.setAttribute('aria-selected', an ? 'true' : 'false');

                    if (an) {
                        element.scrollIntoView({ block: 'nearest' });
                    }
                });

                if (aktiv >= 0) {
                    feld.setAttribute('aria-activedescendant', 'av-option-' + aktiv);
                } else {
                    feld.removeAttribute('aria-activedescendant');
                }
            }

            function schliessen() {
                liste.hidden = true;
                aktiv = -1;

                if (feldAktuell) {
                    feldAktuell.setAttribute('aria-expanded', 'false');
                    feldAktuell.removeAttribute('aria-activedescendant');
                }
            }

            function hinweisen(feld, text) {
                const element = ziel(feld, 'hinweis');

                if (element) {
                    element.textContent = text;
                }
            }

            /** Was ein gewählter Vorschlag an Gruppe und Platzhalter gesetzt hat, wieder lösen. */
            function loesen(feld) {
                const auswahl = ziel(feld, 'gruppe');
                const mengenfeld = ziel(feld, 'menge');

                if (auswahl && auswahl.dataset.avAuto) {
                    auswahl.value = auswahl.dataset.avVorher ?? '';
                    delete auswahl.dataset.avAuto;
                    delete auswahl.dataset.avVorher;
                }

                if (mengenfeld && mengenfeld.dataset.avPlatzhalter !== undefined) {
                    mengenfeld.placeholder = mengenfeld.dataset.avPlatzhalter;
                }

                hinweisen(feld, '');
            }

            function waehlen(feld, eintrag) {
                const { praefix } = teile(feld);
                const auswahl = ziel(feld, 'gruppe');
                const mengenfeld = ziel(feld, 'menge');
                // Eine vorangestellte Menge wandert ins Mengenfeld, wenn das
                // noch leer ist -- sonst bleibt sie, wo sie getippt wurde.
                const verschieben = praefix !== '' && mengenfeld && mengenfeld.value.trim() === '';
                const vorne = verschieben ? '' : praefix;

                if (eintrag.artikel) {
                    const a = eintrag.artikel;
                    feld.value = vorne + a.name;

                    if (auswahl && a.gruppe !== null && auswahl.querySelector('option[value="' + a.gruppe + '"]')) {
                        if (!auswahl.dataset.avAuto) {
                            auswahl.dataset.avVorher = auswahl.value;
                        }

                        auswahl.value = String(a.gruppe);
                        auswahl.dataset.avAuto = '1';
                    }

                    if (mengenfeld) {
                        mengenfeld.dataset.avPlatzhalter ??= mengenfeld.placeholder;
                        mengenfeld.placeholder = a.einheit || mengenfeld.dataset.avPlatzhalter;
                    }

                    hinweisen(feld, 'Aus dem Katalog · ' + (gruppen.get(a.gruppe) ?? 'ohne Warengruppe')
                        + (a.einheit ? ' · Einheit ' + a.einheit : ''));
                } else {
                    feld.value = vorne + eintrag.neu;
                    const gruppe = gruppeVon(feld);

                    hinweisen(feld, 'Neu: kommt ohne Katalogbezug auf die Liste'
                        + (gruppe !== null ? ', Warengruppe ' + (gruppen.get(gruppe) ?? '') : ', die Warengruppe wird automatisch erkannt') + '.');
                }

                if (verschieben) {
                    mengenfeld.value = praefix.trim();
                }

                schliessen();
                (mengenfeld || feld).focus();
            }

            liste.addEventListener('mousedown', (ereignis) => ereignis.preventDefault());
            liste.addEventListener('click', (ereignis) => {
                const element = ereignis.target.closest('.av-option');

                if (element && feldAktuell) {
                    waehlen(feldAktuell, eintraege[Number(element.dataset.index)]);
                }
            });

            document.addEventListener('pointerdown', (ereignis) => {
                if (!liste.hidden && !liste.contains(ereignis.target) && ereignis.target !== feldAktuell) {
                    schliessen();
                }
            });

            window.addEventListener('resize', () => {
                if (!liste.hidden && feldAktuell) {
                    platzieren(feldAktuell);
                }
            });

            document.querySelectorAll('input[list="artikel-vorschlaege"]').forEach((feld) => {
                // Ab hier übernimmt die eigene Liste. Bliebe die datalist
                // verbunden, stünden zwei Listen übereinander.
                feld.removeAttribute('list');
                feld.setAttribute('role', 'combobox');
                feld.setAttribute('aria-autocomplete', 'list');
                feld.setAttribute('aria-controls', liste.id);
                feld.setAttribute('aria-expanded', 'false');

                feld.addEventListener('input', () => {
                    loesen(feld);
                    aktiv = -1;
                    zeigen(feld);
                });
                feld.addEventListener('focus', () => zeigen(feld));
                feld.addEventListener('blur', () => setTimeout(() => {
                    if (document.activeElement !== feld && feldAktuell === feld) {
                        schliessen();
                    }
                }, 150));

                feld.addEventListener('keydown', (ereignis) => {
                    const offen = !liste.hidden && feldAktuell === feld;

                    if (ereignis.key === 'ArrowDown') {
                        ereignis.preventDefault();

                        if (!offen) {
                            zeigen(feld);
                        }

                        aktiv = Math.min(aktiv + 1, eintraege.length - 1);
                        markieren(feld);
                    } else if (ereignis.key === 'ArrowUp' && offen) {
                        ereignis.preventDefault();
                        aktiv = Math.max(aktiv - 1, -1);
                        markieren(feld);
                    } else if (ereignis.key === 'Escape' && offen) {
                        ereignis.preventDefault();
                        schliessen();
                    } else if (ereignis.key === 'Enter' && offen && (aktiv >= 0 || eintraege.length === 1)) {
                        // Enter übernimmt den markierten -- oder den einzigen --
                        // Vorschlag und springt zur Menge. Erst Enter dort
                        // schickt ab. Ohne Auswahl schickt Enter wie gewohnt ab.
                        ereignis.preventDefault();
                        waehlen(feld, eintraege[Math.max(aktiv, 0)]);
                    } else if (ereignis.key === 'Tab' || ereignis.key === 'Enter') {
                        schliessen();
                    }
                });

                const auswahl = ziel(feld, 'gruppe');

                if (auswahl) {
                    auswahl.addEventListener('change', () => {
                        // Jetzt ist es die eigene Wahl, nicht mehr die eines Vorschlags.
                        delete auswahl.dataset.avAuto;
                        delete auswahl.dataset.avVorher;

                        if (document.activeElement === feld) {
                            zeigen(feld);
                        }
                    });
                }
            });
        })();
    </script>
@endif
