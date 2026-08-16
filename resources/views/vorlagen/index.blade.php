@extends('layouts.app')
@section('title', 'Vorlagen')

@section('inhalt')
    <h1>Vorlagen</h1>

    <p class="gedaempft">
        Eine Vorlage ist eine benannte Sammlung von Artikeln für einen Anlass —
        „Wocheneinkauf", „Grillabend", „Urlaubsvorrat". Die <strong>Standardvorlage</strong>
        wird jeder neuen Einkaufsliste automatisch mitgegeben, also auch der Liste,
        die nach dem Abschluss eines Einkaufs beginnt.
    </p>

    <div class="tabellenrahmen">
        <table>
            <thead>
                <tr><th>Vorlage</th><th style="width:5rem">Artikel</th><th style="width:7rem">Standard</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($vorlagen as $vorlage)
                    <tr>
                        <td>
                            <a href="{{ route('vorlagen.show', $vorlage) }}">{{ $vorlage->name }}</a>
                            @if ($vorlage->description)
                                <span class="gedaempft" style="font-size:0.85rem">— {{ $vorlage->description }}</span>
                            @endif
                        </td>
                        <td>{{ $vorlage->items_count }}</td>
                        <td>
                            <form method="POST" action="{{ route('vorlagen.standard', $vorlage) }}" class="inline">
                                @csrf
                                <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem"
                                        title="{{ $vorlage->is_default ? 'Automatik abschalten' : 'Für neue Listen verwenden' }}">
                                    {{ $vorlage->is_default ? '★ Standard' : '☆ setzen' }}
                                </button>
                            </form>
                        </td>
                        <td style="white-space:nowrap">
                            <form method="POST" action="{{ route('vorlagen.anwenden', $vorlage) }}" class="inline">
                                @csrf
                                <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">
                                    Auf die Liste
                                </button>
                            </form>
                            <form method="POST" action="{{ route('vorlagen.destroy', $vorlage) }}" class="inline"
                                  onsubmit="return confirm('Vorlage ' + {{ Js::from($vorlage->name) }} + ' löschen?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="gedaempft">Noch keine Vorlage angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2>Neue Vorlage</h2>
    <form method="POST" action="{{ route('vorlagen.store') }}">
        @csrf

        <label for="name">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required placeholder="Wocheneinkauf">

        <label for="description">Beschreibung <span class="gedaempft">(optional)</span></label>
        <input id="description" name="description" type="text" value="{{ old('description') }}"
               placeholder="Was jede Woche gebraucht wird">

        <button type="submit">Anlegen</button>
    </form>

    <h2>Aus der aktuellen Liste</h2>
    <p class="gedaempft">
        Der bequemste Weg zu einer Vorlage: einmal von Hand zusammenstellen, dann
        behalten. Übernommen wird alles, was auf der Liste steht — auch
        Abgehaktes.
    </p>
    <form method="POST" action="{{ route('vorlagen.aus-liste') }}">
        @csrf

        <label for="name_aus_liste">Name</label>
        <input id="name_aus_liste" name="name" type="text" required placeholder="Wie diese Woche">

        <label for="description_aus_liste">Beschreibung <span class="gedaempft">(optional)</span></label>
        <input id="description_aus_liste" name="description" type="text">

        <button type="submit" class="knopf-schlicht">Aktuelle Liste als Vorlage sichern</button>
    </form>
@endsection
