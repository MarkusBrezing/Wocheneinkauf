@extends('layouts.app')
@section('title', $vorlage->name)

@section('inhalt')
    <h1>{{ $vorlage->name }}</h1>

    <p class="gedaempft">
        {{ $vorlage->items->count() }} {{ $vorlage->items->count() === 1 ? 'Artikel' : 'Artikel' }}
        @if ($vorlage->is_default)
            · <strong>Standardvorlage</strong> — jede neue Liste beginnt damit
        @endif
        · <a href="{{ route('vorlagen.index') }}">Alle Vorlagen</a>
    </p>

    <form method="POST" action="{{ route('vorlagen.anwenden', $vorlage) }}">
        @csrf
        <button type="submit">Auf die aktuelle Liste setzen</button>
    </form>

    <h2>Artikel</h2>

    <div class="tabellenrahmen">
        <table>
            <tbody>
                @forelse ($vorlage->items as $position)
                    <tr>
                        <td>
                            <span>
                                @if ($position->mengeAnzeige() !== '')
                                    <strong>{{ $position->mengeAnzeige() }}</strong>
                                @endif
                                {{ $position->raw_name }}
                            </span>
                            @if ($position->note)
                                <span class="gedaempft">— {{ $position->note }}</span>
                            @endif
                            <span class="gedaempft" style="font-size:0.8rem">{{ $position->productGroup?->label }}</span>

                            <details style="margin-top:0.2rem">
                                <summary class="gedaempft" style="cursor:pointer;font-size:0.85rem">bearbeiten</summary>
                                <form method="POST" action="{{ route('vorlagen.positionen.update', $position) }}"
                                      style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;margin-top:0.4rem">
                                    @csrf
                                    @method('PUT')
                                    <input name="text" type="text" value="{{ $position->bearbeitungstext() }}" required
                                           style="margin:0;width:auto" aria-label="Menge und Artikel">
                                    <input name="note" type="text" value="{{ $position->note }}" placeholder="Notiz"
                                           style="margin:0;width:auto" aria-label="Notiz">
                                    <select name="product_group_id" style="margin:0;width:auto" aria-label="Warengruppe">
                                        <option value="">— ohne —</option>
                                        @foreach ($gruppen as $gruppe)
                                            <option value="{{ $gruppe->id }}" @selected($position->product_group_id === $gruppe->id)>
                                                {{ $gruppe->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.35rem 0.7rem">Speichern</button>
                                </form>
                                <form method="POST" action="{{ route('vorlagen.positionen.destroy', $position) }}"
                                      style="margin-top:0.4rem"
                                      onsubmit="return confirm('„{{ $position->raw_name }}“ aus der Vorlage entfernen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">Entfernen</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td class="gedaempft">Die Vorlage ist noch leer.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('vorlagen.positionen.store', $vorlage) }}"
          style="display:flex;gap:0.4rem;align-items:flex-end;flex-wrap:wrap;margin-top:1rem">
        @csrf
        <div style="flex:1 1 14rem">
            <label for="text">Artikel hinzufügen</label>
            <input id="text" name="text" type="text" required placeholder="2 l Vollmilch" style="margin:0">
        </div>
        <div>
            <label for="product_group_id">Warengruppe</label>
            <select id="product_group_id" name="product_group_id" style="margin:0;width:auto">
                <option value="">— automatisch —</option>
                @foreach ($gruppen as $gruppe)
                    <option value="{{ $gruppe->id }}">{{ $gruppe->label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="margin:0">Hinzufügen</button>
    </form>

    <h2>Einstellungen</h2>
    <form method="POST" action="{{ route('vorlagen.update', $vorlage) }}">
        @csrf
        @method('PUT')

        <label for="name">Name</label>
        <input id="name" name="name" type="text" value="{{ $vorlage->name }}" required>

        <label for="description">Beschreibung <span class="gedaempft">(optional)</span></label>
        <input id="description" name="description" type="text" value="{{ $vorlage->description }}">

        <label for="sort">Sortierung</label>
        <input id="sort" name="sort" type="number" value="{{ $vorlage->sort }}" min="0" max="65535">

        <button type="submit" class="knopf-schlicht">Speichern</button>
    </form>

    <form method="POST" action="{{ route('vorlagen.standard', $vorlage) }}" style="margin-top:0.5rem">
        @csrf
        <button type="submit" class="knopf-schlicht">
            {{ $vorlage->is_default ? 'Nicht mehr für neue Listen verwenden' : 'Für neue Listen verwenden' }}
        </button>
    </form>
@endsection
