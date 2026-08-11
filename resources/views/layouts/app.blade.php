<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#457A00">
    <title>@yield('title', 'Wocheneinkauf')</title>

    <link rel="manifest" href="{{ route('manifest') }}">
    <link rel="icon" href="/symbole/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/symbole/apple-touch-icon.png">

    {{-- Bewusst systemeigene Schriften statt Google Fonts: keine Anfrage an
         Dritte, damit die Datenschutzerklaerung schlank bleibt. --}}
    <style>
        /*
         * Farbpalette: Moosgrün auf Creme, dazu Beige für Flächen und
         * Terrakotta als warmer Gegenpol.
         *
         * Die Rollen sind getrennt gehalten, damit eine Seite nicht raten
         * muss: --primaer trägt jede Handlung (Knöpfe, Links), --akzent nur
         * das, was auffallen soll, --flaeche jede abgesetzte Box. Wer eine
         * Farbe direkt in eine View schreibt, umgeht diese Ordnung -- deshalb
         * steht hier alles, was gebraucht wird.
         */
        :root {
            color-scheme: light;
            --hintergrund: #FDFBF7;   /* Creme / Elfenbein */
            --flaeche: #F2EFE9;       /* sanftes Beige: Karten, Zeilen, Kopf */
            --text: #1C1D1B;          /* fast Schwarz */
            --primaer: #457A00;       /* Moosgrün */
            --primaer-hell: #EDF3E2;  /* Moosgrün, stark aufgehellt */
            --akzent: #C86D51;        /* Terrakotta */
            --akzent-hell: #F7E9E3;
            --rand: #DCD6CB;          /* Beige, eine Stufe dunkler */
            --gedaempft: #6A6B66;
            --fehler: #A33A21;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --hintergrund: #1C1D1B;
                --flaeche: #262824;
                --text: #FDFBF7;
                --primaer: #8CBF3F;   /* Moosgrün, für dunklen Grund aufgehellt */
                --primaer-hell: #2C3524;
                --akzent: #E0906F;
                --akzent-hell: #3A2A24;
                --rand: #3C3E39;
                --gedaempft: #A8A79F;
                --fehler: #F0A08A;
            }
        }

        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5; margin: 0; padding: 1.5rem;
            max-width: 46rem; margin-inline: auto;
            background: var(--hintergrund); color: var(--text);
        }
        h1 { font-size: 1.5rem; } h2 { font-size: 1.15rem; margin-top: 2rem; }
        a { color: var(--primaer); }
        a:hover { color: var(--akzent); }
        label { display: block; margin-top: 0.85rem; font-weight: 600; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 0.5rem; margin-top: 0.25rem;
            border: 1px solid var(--rand); border-radius: 6px; font: inherit;
            background: var(--hintergrund); color: inherit; }
        input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible {
            outline: 2px solid var(--primaer); outline-offset: 1px;
        }
        button, .knopf { margin-top: 1rem; padding: 0.55rem 1.1rem; font: inherit;
            border: 1px solid var(--primaer); border-radius: 6px; cursor: pointer;
            background: var(--primaer); color: #FDFBF7; text-decoration: none;
            display: inline-block; }
        button:hover, .knopf:hover { filter: brightness(1.08); color: #FDFBF7; }
        .knopf-schlicht { background: var(--hintergrund); color: var(--text); border-color: var(--rand); }
        .knopf-schlicht:hover { background: var(--flaeche); color: var(--text); filter: none; }
        /* Terrakotta bleibt dem vorbehalten, was heraussticht: der einen
           Handlung, um die es auf einer Seite geht. */
        .knopf-akzent { background: var(--akzent); border-color: var(--akzent); }
        .fehler { color: var(--fehler); font-size: 0.9rem; margin-top: 0.5rem; }
        .hinweis { padding: 0.7rem 0.9rem; border: 1px solid var(--primaer);
            border-left-width: 4px; border-radius: 6px; margin-bottom: 1rem;
            background: var(--primaer-hell); }

        /*
         * Bestätigungen als Einblendung oben rechts. Das Ausblenden nach zehn
         * Sekunden macht die Animation selbst -- ohne JavaScript verschwindet
         * die Meldung also auch dann, wenn Skripte blockiert sind. Der
         * Schließen-Knopf ist die Abkürzung für alle anderen.
         */
        .meldung {
            position: fixed; top: 1rem; right: 1rem; z-index: 50;
            max-width: min(26rem, calc(100vw - 2rem));
            display: flex; gap: 0.75rem; align-items: flex-start;
            padding: 0.7rem 0.9rem; border-radius: 6px;
            border: 1px solid var(--primaer); border-left-width: 4px;
            background: var(--primaer-hell); color: var(--text);
            box-shadow: 0 4px 14px rgba(28, 29, 27, 0.18);
            animation: meldung-ein 0.2s ease-out, meldung-aus 0.6s ease-in 10s forwards;
        }
        .meldung-zu {
            margin: -0.2rem -0.3rem 0 auto; padding: 0 0.35rem;
            background: transparent; border: none; color: var(--gedaempft);
            font-size: 1.2rem; line-height: 1.2; cursor: pointer;
        }
        .meldung-zu:hover { background: transparent; color: var(--akzent); filter: none; }
        @keyframes meldung-ein { from { opacity: 0; transform: translateY(-0.6rem); } }
        @keyframes meldung-aus { to { opacity: 0; visibility: hidden; transform: translateY(-0.6rem); } }

        /* Wer Bewegung abbestellt hat, bekommt die Meldung ohne Fahrt -- sie
           verschwindet trotzdem zur selben Zeit. */
        @media (prefers-reduced-motion: reduce) {
            .meldung { animation: meldung-aus 0.1s linear 10s forwards; }
        }

        @media print { .meldung { display: none; } }
        .gedaempft { color: var(--gedaempft); font-size: 0.9rem; }

        /*
         * Navigation in zwei Ebenen: oben die Bereiche, darunter deren Seiten.
         * Die zweite Ebene erscheint nur, wenn ein Bereich offen ist -- eine
         * dauerhaft sichtbare Liste aller sechzehn Seiten war der Zustand
         * vorher, und genau der sollte weg.
         */
        nav a { color: var(--text); text-decoration: none; }
        nav a:hover { color: var(--primaer); text-decoration: underline; }
        nav strong a { color: var(--primaer); }

        .nav-bereiche { display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: baseline;
            border-bottom: 2px solid var(--primaer); padding-bottom: 0; }
        .nav-bereiche strong { margin-right: 0.6rem; padding-bottom: 0.5rem; }
        .nav-bereiche .bereich { padding: 0.35rem 0.8rem; border-radius: 6px 6px 0 0;
            border: 1px solid transparent; border-bottom: none; }
        .nav-bereiche .bereich:hover { background: var(--flaeche); text-decoration: none; }
        /* Der offene Bereich sitzt auf der Linie und trägt sie weiter -- so
           gehört die zweite Ebene sichtbar zu ihm. */
        .nav-bereiche .bereich.aktiv { background: var(--primaer); color: #FDFBF7; font-weight: 600; }
        .nav-bereiche .bereich.aktiv:hover { color: #FDFBF7; }
        .nav-bereiche .wer { margin-left: auto; padding-bottom: 0.5rem; }
        .nav-bereiche .abmelden { margin: 0 0 0.35rem; padding: 0.2rem 0.6rem; }

        .nav-seiten { display: flex; gap: 1.1rem; flex-wrap: wrap; align-items: center;
            padding: 0.6rem 0.2rem; border-bottom: 1px solid var(--rand);
            margin-bottom: 1.5rem; font-size: 0.95rem; }
        .nav-seiten a { display: inline-flex; align-items: center; gap: 0.35rem; }
        .nav-seiten a.aktiv { color: var(--primaer); font-weight: 600; }

        /*
         * Gerätekennzeichnung. Die Farbe trägt sie nicht allein: Jede Marke hat
         * zusätzlich ein Zeichen und ihren Namen ausgeschrieben -- wer Farben
         * schlecht unterscheidet, liest sonst nichts.
         */
        .marke-geraet { font-size: 0.7rem; letter-spacing: 0.03em; white-space: nowrap;
            padding: 0.05rem 0.4rem; border-radius: 999px;
            border: 1px solid var(--rand); background: var(--hintergrund); color: var(--gedaempft); }
        .marke-pc { border-color: var(--gedaempft); }
        .marke-tablet { border-color: var(--primaer); color: var(--primaer); }
        .marke-handy { border-color: var(--akzent); color: var(--akzent); background: var(--akzent-hell); }

        .geraetestreifen { display: flex; gap: 0.6rem; align-items: baseline; flex-wrap: wrap;
            margin: 0 0 1.25rem; padding: 0.4rem 0.7rem; border-radius: 6px;
            border-left: 4px solid var(--rand); background: var(--flaeche); font-size: 0.85rem; }
        .geraetestreifen .art { font-weight: 600; white-space: nowrap; }
        .streifen-pc { border-left-color: var(--gedaempft); }
        .streifen-tablet { border-left-color: var(--primaer); }
        .streifen-handy { border-left-color: var(--akzent); background: var(--akzent-hell); }

        /*
         * Der Satz dazu erscheint nur dort, wo er gebraucht wird: auf einem
         * Bildschirm, für den die Seite nicht gebaut ist. Am PC ist der
         * Hinweis „für den PC" nur Lärm.
         */
        .geraetestreifen .warum { color: var(--gedaempft); display: none; }
        @media (max-width: 48rem) { .streifen-pc .warum { display: inline; } }
        @media (max-width: 34rem) { .streifen-tablet .warum { display: inline; } }
        @media (min-width: 60rem) { .streifen-handy .warum { display: inline; } }

        /*
         * Die Kette der Stammdatenpflege. Sie steht auf allen drei Seiten und
         * beantwortet die Frage, in welcher Reihenfolge man vorgeht -- bevor
         * jemand Artikel anlegt, für die es noch keine Warengruppe gibt.
         */
        .pflegeschritte { margin: 0 0 1.5rem; }
        .pflegeschritte ol { display: flex; gap: 0.4rem; flex-wrap: wrap;
            list-style: none; margin: 0; padding: 0; align-items: center; }
        .pflegeschritte li + li::before { content: '→'; color: var(--gedaempft);
            margin-right: 0.4rem; }
        .pflegeschritte li { display: flex; align-items: center; }
        .pflegeschritte a { display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.25rem 0.7rem; border: 1px solid var(--rand); border-radius: 999px;
            background: var(--hintergrund); text-decoration: none; font-size: 0.9rem; }
        .pflegeschritte a:hover { border-color: var(--primaer); text-decoration: none; }
        .pflegeschritte a.aktiv { background: var(--primaer); color: #FDFBF7;
            border-color: var(--primaer); font-weight: 600; }
        .pflegeschritte .nr { display: inline-grid; place-items: center;
            width: 1.25rem; height: 1.25rem; border-radius: 50%;
            background: var(--flaeche); color: var(--gedaempft); font-size: 0.75rem; }
        .pflegeschritte a.aktiv .nr { background: #FDFBF7; color: var(--primaer); }
        .pflegeschritte p { margin: 0.4rem 0 0; font-size: 0.82rem; }

        @media print { .nav-bereiche, .nav-seiten, .geraetestreifen, .pflegeschritte { display: none; } }
        table { border-collapse: collapse; width: 100%; margin-top: 0.75rem; }
        th, td { text-align: left; padding: 0.45rem 0.6rem; border-bottom: 1px solid var(--rand); }
        thead th { background: var(--flaeche); }
        .tabellenrahmen { overflow-x: auto; }
        form.inline { display: inline; }
        code { word-break: break-all; background: var(--flaeche); padding: 0 0.2rem; border-radius: 3px; }
        .fusszeile { margin-top: 3rem; padding-top: 1rem; border-top: 1px solid var(--rand);
            display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.85rem; }
        .fusszeile a { color: var(--gedaempft); text-decoration: none; }
        .fusszeile a:hover { color: var(--primaer); text-decoration: underline; }
        @media print { .fusszeile { display: none; } }
    </style>
</head>
<body>
    @auth
        @include('partials.navigation')

        {{--
            Für welches Gerät die Seite gebaut ist. Die Angabe steht oben und
            nicht im Kleingedruckten: Sie beantwortet die Frage, warum eine
            Tabelle zwölf Spalten hat oder eine Schaltfläche daumengroß ist --
            bevor sie jemand für einen Fehler hält.
        --}}
        @php($geraet = \App\Core\Support\Navigation::geraet(request()->route()?->getName()))
        @if ($geraet)
            @php($angaben = \App\Core\Support\Navigation::GERAETE[$geraet])
            <p class="geraetestreifen streifen-{{ $geraet }}">
                <span class="art"><span aria-hidden="true">{{ $angaben['zeichen'] }}</span> {{ $angaben['label'] }}</span>
                <span class="warum">{{ $angaben['hinweis'] }}</span>
            </p>
        @endif
    @endauth

    {{--
        Bestätigungen erscheinen als Einblendung und gehen nach zehn Sekunden
        von selbst wieder. Beim Pflegen einer Tabelle wird im Minutentakt
        gespeichert -- eine Meldung, die stehen bleibt und den Seiteninhalt
        nach unten schiebt, wäre dabei mehr Störung als Hilfe.

        Fehler verschwinden ausdrücklich *nicht*: Wer eine Eingabe korrigieren
        muss, darf nicht erst nachschlagen müssen, was beanstandet wurde.
    --}}
    @if (session('status'))
        <div class="meldung" role="status" aria-live="polite">
            {{ session('status') }}
            <button type="button" class="meldung-zu" aria-label="Meldung schließen"
                    onclick="this.parentElement.remove()">×</button>
        </div>
    @endif

    @if ($errors->any())
        <ul class="fehler">
            @foreach ($errors->all() as $fehler)
                <li>{{ $fehler }}</li>
            @endforeach
        </ul>
    @endif

    @yield('inhalt')

    <footer class="fusszeile">
        <a href="{{ route('handbuch.index') }}">Handbuch</a>
        <a href="{{ route('impressum') }}">Impressum</a>
        <a href="{{ route('datenschutz') }}">Datenschutz</a>
        <a href="{{ route('nutzungsbedingungen') }}">Nutzungsbedingungen</a>
        <a href="/health">Systemstatus</a>
    </footer>
</body>
</html>
