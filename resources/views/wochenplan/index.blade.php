@extends('layouts.app')

@section('title', 'Wochenplan')

@section('inhalt')
    <style>
        .planwoche { display: flex; align-items: baseline; gap: 1rem; flex-wrap: wrap;
            justify-content: space-between; }
        .tag { border: 1px solid var(--rand); border-radius: 8px; margin-top: 0.9rem;
            background: var(--flaeche); overflow: hidden; }
        .tag.heute { border-color: var(--primaer); border-width: 2px; }
        .tagkopf { display: flex; justify-content: space-between; align-items: baseline;
            padding: 0.5rem 0.8rem; background: var(--primaer-hell); font-weight: 600; }
        .tagkopf .datum { font-weight: 400; color: var(--gedaempft); font-size: 0.9rem; }
        .slot { padding: 0.65rem 0.8rem; border-top: 1px solid var(--rand);
            background: var(--hintergrund); }
        .slot:first-of-type { border-top: none; }
        .slot-kopf { display: flex; gap: 0.6rem; align-items: baseline; flex-wrap: wrap; }
        .slot-name { font-weight: 600; }
        .slot-name.gekocht { text-decoration: line-through; color: var(--gedaempft); }
        .slot-leer { color: var(--gedaempft); font-style: italic; }
        .slot-werkzeuge { margin-left: auto; display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .slot-werkzeuge button, .slot-werkzeuge .knopf { margin: 0; padding: 0.2rem 0.55rem;
            font-size: 0.85rem; }
        .slot-eingabe { display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;
            align-items: flex-end; }
        .slot-eingabe select { flex: 1 1 16rem; margin: 0; }
        .slot-eingabe input[type=text] { flex: 1 1 10rem; margin: 0; }
        .slot-eingabe input[type=number] { flex: 0 0 5rem; margin: 0; }
        .slot-eingabe button { margin: 0; }
        .marke { font-size: 0.75rem; padding: 0.1rem 0.4rem; border-radius: 999px;
            border: 1px solid var(--rand); background: var(--flaeche); color: var(--gedaempft); }
        .marke.vorschlag { border-color: var(--akzent); color: var(--akzent); background: var(--akzent-hell); }
        .werkzeugleiste { display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;
            margin-top: 1rem; }
        .werkzeugleiste form { margin: 0; }
        .werkzeugleiste button { margin: 0; }
        @media (max-width: 34rem) {
            .slot-werkzeuge { margin-left: 0; width: 100%; }
        }
    </style>

    <div class="planwoche">
        <h1>Wochenplan · KW {{ $plan->kalenderwoche() }}</h1>
        <span>
            <a href="{{ route('wochenplan.index', ['woche' => $vorwoche]) }}">‹ Vorwoche</a>
            ·
            <a href="{{ route('wochenplan.index') }}">Aktuelle Woche</a>
            ·
            <a href="{{ route('wochenplan.index', ['woche' => $folgewoche]) }}">Folgewoche ›</a>
        </span>
    </div>

    <p class="gedaempft">
        {{ $plan->week_start->format('d.m.Y') }} bis {{ $plan->week_start->copy()->addDays(6)->format('d.m.Y') }}
        @if ($plan->list)
            · bereits auf die <a href="{{ route('einkaufsliste.index') }}">Einkaufsliste</a> übernommen
        @endif
    </p>

    {{--
        Der Vorschlag wird erst gezeigt und dann übernommen. Direkt einzutragen
        wäre schneller, aber wer die Woche schon halb geplant hat, will vorher
        sehen, was sich ändert.
    --}}
    @if ($vorschlag && $vorschlag->result !== [])
        <div class="hinweis">
            <strong>Vorschlag für die Woche</strong>
            <ul style="margin:0.4rem 0 0.6rem;padding-left:1.2rem">
                @foreach ($vorschlag->result as $eintrag)
                    @php($slot = $plan->slots->firstWhere('id', $eintrag['slot_id']))
                    @php($rezept = $rezepte->firstWhere('id', $eintrag['recipe_id']))
                    <li>
                        {{ $slot?->plan_date->locale('de')->isoFormat('dd, D.M.') }}:
                        <strong>{{ $rezept?->name ?? 'unbekannt' }}</strong>
                        <span class="gedaempft">({{ $eintrag['score'] }} Punkte)</span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('wochenplan.vorschlag.annehmen', $vorschlag) }}" class="inline">
                @csrf
                <button type="submit" style="margin:0">Übernehmen</button>
            </form>
            <form method="POST" action="{{ route('wochenplan.vorschlag.verwerfen', $vorschlag) }}" class="inline">
                @csrf
                <button type="submit" class="knopf-schlicht" style="margin:0">Neu würfeln</button>
            </form>
        </div>
    @endif

    <div class="werkzeugleiste">
        <form method="POST" action="{{ route('wochenplan.vorschlag', $plan) }}">
            @csrf
            <input type="hidden" name="meal" value="{{ $mahlzeiten->first() ?? 'dinner' }}">
            <button type="submit" class="knopf-akzent">Woche vorschlagen</button>
        </form>
        <form method="POST" action="{{ route('wochenplan.auf-die-liste', $plan) }}">
            @csrf
            <button type="submit">Auf die Einkaufsliste</button>
        </form>
        <form method="POST" action="{{ route('wochenplan.kopieren', $plan) }}">
            @csrf
            <button type="submit" class="knopf-schlicht">In die Folgewoche kopieren</button>
        </form>
        <a class="knopf knopf-schlicht" href="{{ route('wochenplan.drucken', ['woche' => $plan->week_start->toDateString()]) }}">
            Drucken
        </a>
        <a class="knopf knopf-schlicht" href="{{ route('wochenplan.historie') }}">Historie</a>
    </div>

    @foreach ($plan->tage() as $tag)
        @php($tagesSlots = $slots->get($tag->toDateString(), collect()))
        <div class="tag @if ($tag->isToday()) heute @endif">
            <div class="tagkopf">
                <span>{{ $tag->locale('de')->isoFormat('dddd') }}</span>
                <span class="datum">{{ $tag->format('d.m.') }}</span>
            </div>

            @foreach ($tagesSlots as $slot)
                <div class="slot">
                    <div class="slot-kopf">
                        {{-- Kein Mahlzeit-Aufkleber mehr: Seit der Plan nur
                             noch Abendessen kennt, stünde auf jeder der sieben
                             Zeilen dasselbe Wort. Der Screenreader bekommt die
                             Mahlzeit weiterhin über die aria-label der
                             Eingabefelder weiter unten. --}}

                        @if ($slot->istBelegt())
                            <span class="slot-name @if ($slot->is_cooked) gekocht @endif">
                                @if ($slot->recipe)
                                    <a href="{{ route('rezepte.show', $slot->recipe) }}">{{ $slot->recipe->name }}</a>
                                @else
                                    {{ $slot->custom_text }}
                                @endif
                            </span>
                            @if ($slot->servings)
                                <span class="marke">{{ $slot->servings }} Portionen</span>
                            @endif
                            @if ($slot->origin === 'suggested')
                                <span class="marke vorschlag">vorgeschlagen</span>
                            @endif
                        @else
                            <span class="slot-leer">noch offen</span>
                        @endif

                        @if ($slot->is_locked)
                            <span class="marke">gesperrt</span>
                        @endif

                        <span class="slot-werkzeuge">
                            @if ($slot->istBelegt())
                                <form method="POST" action="{{ route('wochenplan.slots.gekocht', $slot) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="knopf-schlicht">
                                        {{ $slot->is_cooked ? 'Haken zurück' : 'Gekocht' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('wochenplan.slots.wuerfeln', $slot) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="knopf-schlicht">Anderes</button>
                                </form>
                                <form method="POST" action="{{ route('wochenplan.slots.clear', $slot) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="knopf-schlicht">Leeren</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('wochenplan.slots.sperren', $slot) }}" class="inline">
                                @csrf
                                <button type="submit" class="knopf-schlicht">
                                    {{ $slot->is_locked ? 'Entsperren' : 'Sperren' }}
                                </button>
                            </form>
                        </span>
                    </div>

                    <form method="POST" action="{{ route('wochenplan.slots.fill', $slot) }}" class="slot-eingabe">
                        @csrf @method('PUT')
                        <select name="recipe_id" aria-label="Rezept für {{ $slot->mahlzeitLabel() }} am {{ $tag->format('d.m.') }}">
                            <option value="">— Rezept wählen —</option>
                            @foreach ($rezepte as $rezept)
                                <option value="{{ $rezept->id }}" @selected($slot->recipe_id === $rezept->id)>
                                    {{ $rezept->name }}
                                </option>
                            @endforeach
                        </select>
                        <input type="text" name="custom_text" maxlength="160"
                               placeholder="oder Freitext, z. B. Reste"
                               value="{{ $slot->custom_text }}"
                               aria-label="Freitext für {{ $slot->mahlzeitLabel() }} am {{ $tag->format('d.m.') }}">
                        <input type="number" name="servings" min="1" max="99" step="1"
                               placeholder="Port." value="{{ $slot->servings }}"
                               aria-label="Portionen">
                        <button type="submit">Setzen</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endforeach

    <h2>Wie der Vorschlag zustande kommt</h2>
    <p class="gedaempft">
        Bewertet wird jedes Rezept nach sieben Kriterien: wie lange es her ist,
        wie viel davon schon im Vorrat liegt, ob Protein und Küche zur Woche
        passen, wie viele Pflanzenpunkte es bringt, ob der Aufwand zum
        Wochentag passt und ob Zutaten bald ablaufen. Gesperrte und schon
        belegte Plätze bleiben unangetastet.
    </p>
@endsection
