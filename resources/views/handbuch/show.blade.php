@extends('layouts.handbuch')

@section('titel', $kapitel['titel'])

@section('handbuch')
    <div class="spalten">
        <nav class="inhalt-nav" aria-label="Kapitel">
            <strong style="font-size:0.8rem;letter-spacing:0.06em;color:var(--gedaempft)">KAPITEL</strong>
            <ol>
                @foreach ($kapitel_liste as $k)
                    <li>
                        <a href="{{ route('handbuch.show', $k['slug']) }}"
                           class="@if ($k['slug'] === $kapitel['slug']) aktiv @endif"
                           @if ($k['slug'] === $kapitel['slug']) aria-current="page" @endif>
                            <span class="nummer">{{ $k['nummer'] }}</span>{{ $k['titel'] }}
                        </a>
                    </li>
                @endforeach
            </ol>
        </nav>

        <article>
            {{-- Der Text stammt aus den eigenen Markdown-Dateien unter
                 docs/handbuch und wird beim Umwandeln von jeder Auszeichnung
                 befreit. --}}
            <div class="text">{!! $html !!}</div>

            <nav class="blaettern">
                <span>
                    @if ($vorheriges)
                        <a href="{{ route('handbuch.show', $vorheriges['slug']) }}">‹ {{ $vorheriges['titel'] }}</a>
                    @endif
                </span>
                <span>
                    @if ($naechstes)
                        <a href="{{ route('handbuch.show', $naechstes['slug']) }}">{{ $naechstes['titel'] }} ›</a>
                    @endif
                </span>
            </nav>
        </article>
    </div>
@endsection
