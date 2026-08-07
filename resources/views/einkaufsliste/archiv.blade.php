@extends('layouts.app')
@section('title', 'Archiv')

@section('inhalt')
    <h1>Archiv</h1>

    <p class="gedaempft">
        Abgeschlossene Einkäufe. Die Spalte „offen" zeigt, was beim Abschluss
        gefehlt hat — diese Artikel stehen auf der Liste, die danach begonnen hat.
    </p>

    <div class="tabellenrahmen">
        <table>
            <thead>
                <tr>
                    <th>Einkauf</th><th>Markt</th><th style="width:5rem">Artikel</th>
                    <th style="width:5rem">offen</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($listen as $eintrag)
                    <tr>
                        <td>
                            <a href="{{ route('einkaufsliste.archiv.show', $eintrag) }}">{{ $eintrag->anzeigename() }}</a>
                            <span class="gedaempft" style="font-size:0.8rem">
                                · archiviert {{ $eintrag->archived_at?->format('d.m.Y H:i') }}
                            </span>
                        </td>
                        <td class="gedaempft">{{ $eintrag->market?->name ?? '—' }}</td>
                        <td>{{ $eintrag->items_count }}</td>
                        <td>{{ $eintrag->offene_count }}</td>
                        <td style="white-space:nowrap">
                            <form method="POST" action="{{ route('einkaufsliste.archiv.laden', $eintrag) }}" class="inline">
                                @csrf
                                <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem"
                                        title="Artikel in die aktuelle Liste übernehmen">Laden</button>
                            </form>
                            @if ($darfLoeschen)
                                <form method="POST" action="{{ route('einkaufsliste.archiv.destroy', $eintrag) }}" class="inline"
                                      onsubmit="return confirm('Archiveintrag „{{ $eintrag->anzeigename() }}“ endgültig löschen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">Löschen</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="gedaempft">Noch kein Einkauf archiviert.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $listen->links() }}

    <p style="margin-top:1.5rem">
        <a href="{{ route('einkaufsliste.index') }}" class="knopf knopf-schlicht">Zurück zur Liste</a>
    </p>
@endsection
