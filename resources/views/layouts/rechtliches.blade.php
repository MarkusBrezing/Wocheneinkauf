<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titel') · {{ config('app.name') }}</title>
    <meta name="robots" content="index, follow">
    <link rel="icon" href="/symbole/icon-192.png" type="image/png">

    {{-- Eigenständiges Layout ohne Navigation: Die Rechtstexte müssen auch
         ohne Anmeldung erreichbar sein, und ein Impressum hinter einem Login
         wäre keines. --}}
    <style>
        :root {
            color-scheme: light;
            --hintergrund: #FDFBF7; --flaeche: #F2EFE9; --text: #1C1D1B;
            --primaer: #457A00; --akzent: #C86D51; --rand: #DCD6CB; --gedaempft: #6A6B66;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --hintergrund: #1C1D1B; --flaeche: #262824; --text: #FDFBF7;
                --primaer: #8CBF3F; --akzent: #E0906F; --rand: #3C3E39; --gedaempft: #A8A79F;
            }
        }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6; margin: 0 auto; padding: 1.5rem; max-width: 44rem;
            background: var(--hintergrund); color: var(--text); }
        h1 { font-size: 1.5rem; }
        h2 { font-size: 1.05rem; margin-top: 2rem; }
        a { color: var(--primaer); }
        a:hover { color: var(--akzent); }
        p, li { max-width: 40rem; }
        table { border-collapse: collapse; width: 100%; margin-top: 0.75rem; font-size: 0.95rem; }
        th, td { text-align: left; padding: 0.45rem 0.6rem; border-bottom: 1px solid var(--rand);
            vertical-align: top; }
        thead th { background: var(--flaeche); }
        .gedaempft { color: var(--gedaempft); font-size: 0.9rem; }
        .fehlt { background: #FDE9A9; color: #1C1D1B; padding: 0 0.3rem; border-radius: 3px; }
        .fusszeile { margin-top: 3rem; padding-top: 1rem; border-top: 1px solid var(--rand);
            display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.9rem; }
        .zurueck { display: inline-block; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <a class="zurueck" href="{{ url('/') }}">‹ {{ config('app.name') }}</a>

    @yield('rechtstext')

    <nav class="fusszeile">
        <a href="{{ route('handbuch.index') }}">Handbuch</a>
        <a href="{{ route('impressum') }}">Impressum</a>
        <a href="{{ route('datenschutz') }}">Datenschutz</a>
        <a href="{{ route('nutzungsbedingungen') }}">Nutzungsbedingungen</a>
    </nav>
</body>
</html>
