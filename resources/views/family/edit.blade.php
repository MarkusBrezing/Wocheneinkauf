@extends('layouts.app')
@section('title', 'Familie')

@section('inhalt')
    <h1>{{ $family->name }}</h1>

    @if ($darfVerwalten)
        <h2>Einstellungen</h2>
        <form method="POST" action="{{ route('family.update') }}">
            @csrf
            @method('PUT')

            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $family->name) }}" required>

            <label for="timezone">Zeitzone</label>
            <input id="timezone" name="timezone" type="text" list="zeitzonen"
                   value="{{ old('timezone', $family->timezone) }}" required>
            <datalist id="zeitzonen">
                @foreach (['Europe/Berlin', 'Europe/Vienna', 'Europe/Zurich', 'UTC'] as $zone)
                    <option value="{{ $zone }}"></option>
                @endforeach
            </datalist>

            <label for="week_starts_on">Wochenstart</label>
            <select id="week_starts_on" name="week_starts_on">
                @foreach (['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'] as $index => $tag)
                    <option value="{{ $index + 1 }}" @selected(old('week_starts_on', $family->week_starts_on) == $index + 1)>
                        {{ $tag }}
                    </option>
                @endforeach
            </select>

            <button type="submit">Speichern</button>
        </form>
    @endif

    <h2>Mitglieder</h2>
    <div class="tabellenrahmen">
        <table>
            <thead>
                <tr><th>Name</th><th>E-Mail</th><th>Rolle</th>@if ($istOwner)<th></th>@endif</tr>
            </thead>
            <tbody>
                @foreach ($mitglieder as $mitglied)
                    <tr>
                        <td>{{ $mitglied->name }}</td>
                        <td class="gedaempft">{{ $mitglied->email }}</td>
                        <td>
                            @if ($istOwner && ! $mitglied->is(auth()->user()))
                                <form method="POST" action="{{ route('family.members.update', $mitglied->public_id) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" onchange="this.form.submit()" style="width:auto">
                                        @foreach (['admin', 'member', 'child'] as $rolle)
                                            <option value="{{ $rolle }}" @selected($mitglied->pivot->role === $rolle)>{{ $rolle }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                {{ $mitglied->pivot->role }}
                            @endif
                        </td>
                        @if ($istOwner)
                            <td>
                                @unless ($mitglied->is(auth()->user()))
                                    <form method="POST" action="{{ route('family.members.destroy', $mitglied->public_id) }}" class="inline"
                                          onsubmit="return confirm('{{ $mitglied->name }} wirklich aus der Familie entfernen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">Entfernen</button>
                                    </form>
                                @endunless
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($darfVerwalten)
        <h2>Einladen</h2>
        <p class="gedaempft">
            Mit E-Mail-Adresse verschicken wir die Einladung direkt. Ohne Adresse
            bekommst du einen Link, den du selbst weitergibst.
        </p>
        <form method="POST" action="{{ route('invitations.store') }}">
            @csrf

            <label for="email">E-Mail <span class="gedaempft">(optional)</span></label>
            <input id="email" name="email" type="email" value="{{ old('email') }}">

            <label for="role">Rolle</label>
            <select id="role" name="role">
                @foreach (['member' => 'Mitglied', 'admin' => 'Administrator', 'child' => 'Kind'] as $wert => $bezeichnung)
                    <option value="{{ $wert }}" @selected(old('role') === $wert)>{{ $bezeichnung }}</option>
                @endforeach
            </select>

            <button type="submit">Einladung erstellen</button>
        </form>

        @if ($einladungen->isNotEmpty())
            <h2>Offene Einladungen</h2>
            <div class="tabellenrahmen">
                <table>
                    <thead>
                        <tr><th>Empfänger</th><th>Rolle</th><th>Gültig bis</th><th>Genutzt</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($einladungen as $einladung)
                            <tr>
                                <td>{{ $einladung->email ?? 'Offener Link' }}</td>
                                <td>{{ $einladung->role }}</td>
                                <td>{{ $einladung->expires_at->timezone($family->timezone)->format('d.m.Y H:i') }}</td>
                                <td>{{ $einladung->used_count }} / {{ $einladung->max_uses }}</td>
                                <td>
                                    <form method="POST" action="{{ route('invitations.destroy', $einladung->id) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="knopf-schlicht" style="margin:0;padding:0.2rem 0.6rem">Widerrufen</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection
