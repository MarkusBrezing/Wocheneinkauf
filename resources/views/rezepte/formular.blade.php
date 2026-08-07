@extends('layouts.app')
@section('title', $rezept->exists ? $rezept->name.' bearbeiten' : 'Neues Rezept')

@section('inhalt')
    <h1>{{ $rezept->exists ? 'Rezept bearbeiten' : 'Neues Rezept' }}</h1>

    <form method="POST" action="{{ $rezept->exists ? route('rezepte.update', $rezept) : route('rezepte.store') }}">
        @csrf
        @if ($rezept->exists) @method('PUT') @endif

        <label for="name">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $rezept->name) }}" required
               placeholder="Linsencurry">

        <label for="servings">Portionen</label>
        <input id="servings" name="servings" type="number" min="1" max="99"
               value="{{ old('servings', $rezept->servings ?: 4) }}" required>

        <label for="effort">Aufwand</label>
        <select id="effort" name="effort">
            @foreach (['quick' => 'schnell', 'normal' => 'normal', 'elaborate' => 'aufwendig'] as $wert => $label)
                <option value="{{ $wert }}" @selected(old('effort', $rezept->effort) === $wert)>{{ $label }}</option>
            @endforeach
        </select>

        <label for="prep_minutes">Vorbereitung in Minuten <span class="gedaempft">(optional)</span></label>
        <input id="prep_minutes" name="prep_minutes" type="number" min="0" max="9999"
               value="{{ old('prep_minutes', $rezept->prep_minutes) }}">

        <label for="cook_minutes">Garzeit in Minuten <span class="gedaempft">(optional)</span></label>
        <input id="cook_minutes" name="cook_minutes" type="number" min="0" max="9999"
               value="{{ old('cook_minutes', $rezept->cook_minutes) }}">

        <label for="instructions">Zubereitung</label>
        <textarea id="instructions" name="instructions" rows="10"
                  style="width:100%;padding:0.5rem;border:1px solid var(--rand);border-radius:6px;
                         background:var(--hintergrund);color:inherit;font:inherit">{{ old('instructions', $rezept->instructions) }}</textarea>

        <label for="notes">Anmerkungen <span class="gedaempft">(optional)</span></label>
        <textarea id="notes" name="notes" rows="3"
                  style="width:100%;padding:0.5rem;border:1px solid var(--rand);border-radius:6px;
                         background:var(--hintergrund);color:inherit;font:inherit">{{ old('notes', $rezept->notes) }}</textarea>

        <label for="plant_points">Pflanzenpunkte <span class="gedaempft">(für den Wochenplan)</span></label>
        <input id="plant_points" name="plant_points" type="number" min="0" max="255"
               value="{{ old('plant_points', $rezept->plant_points ?: 0) }}">

        <label for="source_url">Quelle <span class="gedaempft">(optional)</span></label>
        <input id="source_url" name="source_url" type="url" value="{{ old('source_url', $rezept->source_url) }}"
               placeholder="https://…">

        <button type="submit">{{ $rezept->exists ? 'Speichern' : 'Anlegen' }}</button>
        <a href="{{ $rezept->exists ? route('rezepte.show', $rezept) : route('rezepte.index') }}"
           class="knopf knopf-schlicht">Abbrechen</a>
    </form>
@endsection
