<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Wocheneinkauf')</title>

    {{-- Bewusst systemeigene Schriften statt Google Fonts: keine Anfrage an
         Dritte, damit die Datenschutzerklaerung schlank bleibt. --}}
    <style>
        :root { color-scheme: light dark; --rand: #d0d3d8; --gedaempft: #5b6068; --akzent: #2f6f4f; }
        @media (prefers-color-scheme: dark) {
            :root { --rand: #3a3f46; --gedaempft: #a0a6ae; --akzent: #6bbd90; }
        }
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5; margin: 0; padding: 1.5rem;
            max-width: 46rem; margin-inline: auto;
        }
        h1 { font-size: 1.5rem; } h2 { font-size: 1.15rem; margin-top: 2rem; }
        label { display: block; margin-top: 0.85rem; font-weight: 600; font-size: 0.9rem; }
        input, select { width: 100%; padding: 0.5rem; margin-top: 0.25rem;
            border: 1px solid var(--rand); border-radius: 6px; font: inherit;
            background: transparent; color: inherit; }
        button, .knopf { margin-top: 1rem; padding: 0.55rem 1.1rem; font: inherit;
            border: 1px solid var(--akzent); border-radius: 6px; cursor: pointer;
            background: var(--akzent); color: #fff; text-decoration: none;
            display: inline-block; }
        .knopf-schlicht { background: transparent; color: inherit; border-color: var(--rand); }
        .fehler { color: #b3261e; font-size: 0.9rem; margin-top: 0.5rem; }
        @media (prefers-color-scheme: dark) { .fehler { color: #f2b8b5; } }
        .hinweis { padding: 0.7rem 0.9rem; border: 1px solid var(--akzent);
            border-radius: 6px; margin-bottom: 1rem; }
        .gedaempft { color: var(--gedaempft); font-size: 0.9rem; }
        nav { border-bottom: 1px solid var(--rand); padding-bottom: 0.75rem;
            margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;
            align-items: baseline; }
        nav a { color: inherit; }
        table { border-collapse: collapse; width: 100%; margin-top: 0.75rem; }
        th, td { text-align: left; padding: 0.45rem 0.6rem; border-bottom: 1px solid var(--rand); }
        .tabellenrahmen { overflow-x: auto; }
        form.inline { display: inline; }
        code { word-break: break-all; }
    </style>
</head>
<body>
    @auth
        <nav>
            <strong><a href="{{ route('dashboard') }}">Wocheneinkauf</a></strong>
            <a href="{{ route('family.edit') }}">Familie</a>
            <a href="{{ route('account.edit') }}">Konto</a>
            <span class="gedaempft" style="margin-left:auto">
                {{ auth()->user()->name }}@if (auth()->user()->currentFamily) · {{ auth()->user()->currentFamily->name }}@endif
            </span>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">Abmelden</button>
            </form>
        </nav>
    @endauth

    @if (session('status'))
        <p class="hinweis">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul class="fehler">
            @foreach ($errors->all() as $fehler)
                <li>{{ $fehler }}</li>
            @endforeach
        </ul>
    @endif

    @yield('inhalt')
</body>
</html>
