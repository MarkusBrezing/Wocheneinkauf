<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover: damit die Fußleiste bis unter die Gestenleiste
         reicht und nicht darüber schwebt. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#457A00">
    {{-- Das Abhaken laeuft ueber fetch statt ueber ein Formular; ohne diese
         Marke faende das Skript keinen CSRF-Wert und liesse es beim
         Formular. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Einkaufen')</title>

    {{-- Zum Startbildschirm hinzufügen: Von hier aus startet die App auch,
         wenn sie als Symbol abgelegt wurde -- deshalb steht das Manifest an
         genau dieser Seite. --}}
    <link rel="manifest" href="{{ route('manifest') }}">
    <link rel="icon" href="/symbole/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/symbole/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Einkauf">

    {{--
        Ein eigenes, schmales Layout für die Einkaufsansicht.

        Es teilt die Farbpalette mit dem Rest der Anwendung, lässt aber alles
        weg, was im Laden nichts nützt: Navigation, Seitenbreite für Tabellen,
        Artikelvorschläge. Was nicht geladen wird, kann im Funkloch auch nicht
        fehlen.
    --}}
    <style>
        :root {
            color-scheme: light;
            --hintergrund: #FDFBF7;
            --flaeche: #F2EFE9;
            --text: #1C1D1B;
            --primaer: #457A00;
            --primaer-hell: #EDF3E2;
            --akzent: #C86D51;
            --akzent-hell: #F7E9E3;
            --rand: #DCD6CB;
            --gedaempft: #6A6B66;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --hintergrund: #1C1D1B;
                --flaeche: #262824;
                --text: #FDFBF7;
                --primaer: #8CBF3F;
                --primaer-hell: #2C3524;
                --akzent: #E0906F;
                --akzent-hell: #3A2A24;
                --rand: #3C3E39;
                --gedaempft: #A8A79F;
            }
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0; padding: 0;
            background: var(--hintergrund); color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.4;
            /* Kein Überschwingen beim Scrollen -- man wischt hier viel. */
            overscroll-behavior-y: contain;
        }

        /* Der Platz, den die feste Fußleiste unten einnimmt. */
        body { padding-bottom: calc(5.5rem + env(safe-area-inset-bottom)); }

        .kopf {
            position: sticky; top: 0; z-index: 20;
            background: var(--hintergrund);
            border-bottom: 2px solid var(--primaer);
            padding: 0.6rem 0.9rem 0.5rem;
        }
        .kopf-zeile { display: flex; align-items: baseline; justify-content: space-between; gap: 0.6rem; }
        .kopf h1 {
            font-size: 1.05rem; font-weight: 600; margin: 0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .zaehler { font-size: 0.85rem; color: var(--gedaempft); white-space: nowrap; }
        .balken { height: 6px; background: var(--flaeche); border-radius: 3px; margin-top: 0.45rem; overflow: hidden; }
        .balken div { height: 100%; background: var(--primaer); }

        .bereich {
            position: sticky; top: 56px; z-index: 10;
            background: var(--flaeche); color: var(--primaer);
            font-size: 0.8rem; font-weight: 600; letter-spacing: 0.02em;
            padding: 0.35rem 0.9rem;
            border-top: 1px solid var(--rand); border-bottom: 1px solid var(--rand);
            margin: 0;
        }
        .bereich-anderswo { background: var(--akzent-hell); color: var(--akzent); }

        /* Die ganze Zeile ist der Knopf: 64 Punkte hoch, volle Breite.
           Ein 20 Punkte grosses Kaestchen trifft im Laden niemand. */
        .zeile { display: block; margin: 0; }
        .zeile button {
            display: flex; align-items: center; gap: 0.7rem;
            width: 100%; min-height: 64px;
            padding: 0.7rem 0.9rem;
            border: none; border-bottom: 1px solid var(--rand);
            background: var(--hintergrund); color: var(--text);
            font: inherit; text-align: left; cursor: pointer;
            -webkit-tap-highlight-color: var(--primaer-hell);
        }
        .zeile:nth-of-type(even) button { background: color-mix(in srgb, var(--hintergrund) 94%, var(--flaeche)); }
        .zeile button:active { background: var(--primaer-hell); }
        .kaestchen { font-size: 1.5rem; line-height: 1; color: var(--gedaempft); flex: 0 0 auto; }
        /* .abgehakt kommt vom Server, .ist-abgehakt setzt das Skript beim
           Antippen -- beide sollen gleich aussehen, damit der Zustand nicht
           erst nach dem naechsten Seitenaufbau stimmt. */
        .abgehakt .kaestchen,
        .ist-abgehakt .kaestchen { color: var(--primaer); }
        .abgehakt .name,
        .ist-abgehakt .name { color: var(--gedaempft); text-decoration: line-through; }

        /* Ging das Speichern schief, blinkt die Zeile kurz rot auf. Ein Haken,
           der stehen bleibt, obwohl nichts gespeichert wurde, waere die
           schlimmere Auskunft. */
        .haken-fehler button { background: color-mix(in srgb, var(--hintergrund) 80%, #c0392b) !important; }

        /*
         * Der Hinweis auf Nachträge sitzt UNTEN, dicht über der Fußleiste.
         *
         * Oben stand er zuerst -- und verdeckte dort Listenname, Zähler und
         * Fortschrittsbalken, also genau das, woran man sich beim Einkaufen
         * orientiert. Der Kopf ist sticky mit z-index 20; alles, was dort
         * schwebt, nimmt ihm den Platz weg.
         *
         * Unten verdeckt er höchstens die letzte Zeile, liegt in Daumennähe
         * und lässt den Rest der Ansicht in Ruhe. Verschieben tut er nichts --
         * eine Zeile, die unter dem Daumen wegrutscht, wäre der Fehlgriff, den
         * er vermeiden soll.
         */
        /* 5.5rem ist die Höhe, die body.padding-bottom für die Fußleiste
           reserviert -- daran ausgerichtet, statt sie neu zu schätzen.
           z-index 15 liegt über den Bereichsüberschriften (10), aber unter
           Kopf (20) und Fußleiste (30): Beide sollen sichtbar bleiben. */
        .nachschub { position: fixed; left: 0; right: 0; z-index: 15;
            bottom: calc(5.7rem + env(safe-area-inset-bottom, 0px));
            display: flex; justify-content: center; pointer-events: none; }
        .nachschub button { pointer-events: auto; margin: 0;
            padding: 0.4rem 0.9rem; border-radius: 999px;
            background: var(--akzent); color: #FDFBF7; border: none;
            font-size: 0.85rem; font-weight: 600;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.28); }

        /* Abgehaktes verlaesst die Liste. Das Wegblenden ist kurz genug, um
           nicht zu warten, und lang genug, um zu sehen, was getroffen wurde. */
        .zeile.geht-weg { opacity: 0; transform: translateX(1.5rem);
            transition: opacity 0.45s ease, transform 0.45s ease; }
        @media (prefers-reduced-motion: reduce) {
            .zeile.geht-weg { transition: opacity 0.2s ease; transform: none; }
        }
        .name { font-size: 1.15rem; }
        .name b { font-weight: 600; }
        .zusatz { display: block; font-size: 0.8rem; color: var(--gedaempft); margin-top: 0.15rem; }

        .fuss {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 30;
            display: flex; gap: 0.5rem; align-items: center;
            padding: 0.6rem 0.9rem calc(0.6rem + env(safe-area-inset-bottom));
            background: var(--hintergrund);
            border-top: 1px solid var(--rand);
        }
        .fuss form { flex: 1; margin: 0; }
        .fuss button, .fuss a {
            display: flex; align-items: center; justify-content: center;
            width: 100%; min-height: 52px;
            border-radius: 10px; border: 1px solid var(--primaer);
            background: var(--primaer); color: #FDFBF7;
            font: inherit; font-size: 1.05rem; font-weight: 600;
            text-decoration: none; cursor: pointer;
        }
        .fuss .schlicht {
            width: auto; padding: 0 0.9rem;
            background: var(--hintergrund); color: var(--text);
            border-color: var(--rand); font-weight: 400;
        }

        .meldung {
            margin: 0.6rem 0.9rem 0;
            padding: 0.6rem 0.8rem;
            border: 1px solid var(--primaer); border-left-width: 4px; border-radius: 6px;
            background: var(--primaer-hell); font-size: 0.9rem;
        }
        .leer { padding: 3rem 1.2rem; text-align: center; color: var(--gedaempft); }
    </style>
</head>
<body>
    @if (session('status'))
        <p class="meldung" role="status" aria-live="polite">{{ session('status') }}</p>
    @endif

    @yield('inhalt')

    {{--
        Der Service Worker macht die zuletzt geöffnete Liste im Funkloch
        lesbar. Er wird erst nach dem Laden angemeldet: Im Supermarkt zählt,
        wie schnell die Liste erscheint, und nicht, wie schnell der Cache
        bereitsteht.
    --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {
                    // Ohne Service Worker funktioniert alles wie zuvor, nur
                    // eben nicht offline. Kein Grund für eine Fehlermeldung.
                });
            });
        }
    </script>
</body>
</html>
