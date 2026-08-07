<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titel') · Handbuch · {{ config('app.name') }}</title>
    <link rel="icon" href="/symbole/icon-192.png" type="image/png">
    <meta name="theme-color" content="#457A00">

    {{-- Eigenständiges Layout wie bei den Rechtstexten: Das Handbuch muss
         auch ohne Anmeldung lesbar sein, und die Anwendungsnavigation wäre
         darin nur verwirrend -- sie zeigt Seiten, die man ohne Konto gar
         nicht öffnen kann. --}}
    <style>
        :root {
            color-scheme: light;
            --hintergrund: #FDFBF7; --flaeche: #F2EFE9; --text: #1C1D1B;
            --primaer: #457A00; --primaer-hell: #EDF3E2; --akzent: #C86D51;
            --rand: #DCD6CB; --gedaempft: #6A6B66;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --hintergrund: #1C1D1B; --flaeche: #262824; --text: #FDFBF7;
                --primaer: #8CBF3F; --primaer-hell: #2C3524; --akzent: #E0906F;
                --rand: #3C3E39; --gedaempft: #A8A79F;
            }
        }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.65; margin: 0; padding: 1.5rem;
            background: var(--hintergrund); color: var(--text); }
        .rahmen { max-width: 64rem; margin-inline: auto; }
        .kopf { display: flex; gap: 1rem; flex-wrap: wrap; align-items: baseline;
            border-bottom: 2px solid var(--primaer); padding-bottom: 0.6rem; margin-bottom: 1.5rem; }
        .kopf strong a { color: var(--primaer); text-decoration: none; }
        .kopf .rest { margin-left: auto; display: flex; gap: 1rem; font-size: 0.9rem; }
        a { color: var(--primaer); }
        a:hover { color: var(--akzent); }

        /* Zwei Spalten am PC, eine am Telefon -- das Inhaltsverzeichnis rutscht
           dann über den Text statt daneben. */
        .spalten { display: grid; grid-template-columns: 15rem 1fr; gap: 2.5rem; align-items: start; }
        @media (max-width: 52rem) { .spalten { grid-template-columns: 1fr; gap: 1.5rem; } }

        .inhalt-nav { position: sticky; top: 1.5rem; font-size: 0.92rem; }
        .inhalt-nav ol { list-style: none; margin: 0.4rem 0 0; padding: 0; }
        .inhalt-nav li { margin-bottom: 0.3rem; }
        .inhalt-nav a { display: block; padding: 0.25rem 0.5rem; border-radius: 5px;
            text-decoration: none; color: var(--text); }
        .inhalt-nav a:hover { background: var(--flaeche); }
        .inhalt-nav a.aktiv { background: var(--primaer); color: #FDFBF7; font-weight: 600; }
        .inhalt-nav .nummer { color: var(--gedaempft); margin-right: 0.35rem; }
        .inhalt-nav a.aktiv .nummer { color: #FDFBF7; }

        /* Der aus Markdown erzeugte Text. */
        .text h1 { font-size: 1.6rem; margin-top: 0; }
        .text h2 { font-size: 1.2rem; margin-top: 2.2rem; padding-bottom: 0.25rem;
            border-bottom: 1px solid var(--rand); }
        .text h3 { font-size: 1rem; margin-top: 1.6rem; }
        .text p, .text li { max-width: 42rem; }
        .text ul, .text ol { padding-left: 1.3rem; }
        .text code { background: var(--flaeche); padding: 0.05rem 0.3rem; border-radius: 3px;
            font-size: 0.92em; }
        .text pre { background: var(--flaeche); padding: 0.8rem 1rem; border-radius: 6px;
            overflow-x: auto; }
        .text pre code { background: none; padding: 0; }
        .text blockquote { margin: 1.2rem 0; padding: 0.7rem 1rem;
            border-left: 4px solid var(--primaer); background: var(--primaer-hell);
            border-radius: 0 6px 6px 0; }
        .text blockquote p { margin: 0; }
        .text table { border-collapse: collapse; width: 100%; margin: 1rem 0; font-size: 0.95rem;
            display: block; overflow-x: auto; }
        .text th, .text td { text-align: left; padding: 0.45rem 0.7rem;
            border-bottom: 1px solid var(--rand); vertical-align: top; }
        .text thead th { background: var(--flaeche); }
        .text tbody tr:nth-child(even) { background: color-mix(in srgb, var(--flaeche) 45%, transparent); }
        .text em:first-child { color: var(--gedaempft); }

        .blaettern { display: flex; gap: 1rem; justify-content: space-between; flex-wrap: wrap;
            margin-top: 3rem; padding-top: 1rem; border-top: 1px solid var(--rand); }
        .fusszeile { margin-top: 2.5rem; padding-top: 1rem; border-top: 1px solid var(--rand);
            display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.85rem; }
        .fusszeile a { color: var(--gedaempft); text-decoration: none; }
        .fusszeile a:hover { color: var(--primaer); text-decoration: underline; }

        @media print {
            .kopf .rest, .inhalt-nav, .blaettern, .fusszeile { display: none; }
            .spalten { grid-template-columns: 1fr; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="rahmen">
        <header class="kopf">
            <strong><a href="{{ url('/') }}">{{ config('app.name') }}</a></strong>
            <span class="gedaempft">Anwenderhandbuch</span>
            <span class="rest">
                <a href="{{ route('handbuch.index') }}">Übersicht</a>
                <a href="{{ route('handbuch.komplett') }}">Alles auf einer Seite</a>
            </span>
        </header>

        @yield('handbuch')

        <nav class="fusszeile">
            <a href="{{ url('/') }}">Zur Anwendung</a>
            <a href="{{ route('impressum') }}">Impressum</a>
            <a href="{{ route('datenschutz') }}">Datenschutz</a>
            <a href="{{ route('nutzungsbedingungen') }}">Nutzungsbedingungen</a>
        </nav>
    </div>
</body>
</html>
