@extends('layouts.app')
@section('title', 'Konto')

@section('inhalt')
    <h1>Mein Konto</h1>

    <h2>Name und E-Mail</h2>
    <p class="gedaempft">
        Eine geänderte E-Mail-Adresse muss erneut bestätigt werden (A-15).
    </p>
    <form method="POST" action="{{ route('user-profile-information.update') }}">
        @csrf
        @method('PUT')

        <label for="name">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required>

        <label for="email">E-Mail</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>

        <button type="submit">Speichern</button>
    </form>

    <h2>Passwort ändern</h2>
    <form method="POST" action="{{ route('user-password.update') }}">
        @csrf
        @method('PUT')

        <label for="current_password">Aktuelles Passwort</label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">

        <label for="new_password">Neues Passwort</label>
        <input id="new_password" name="password" type="password" required autocomplete="new-password">

        <label for="new_password_confirmation">Neues Passwort wiederholen</label>
        <input id="new_password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

        <button type="submit">Passwort ändern</button>
    </form>

    <h2>Meine Daten herunterladen</h2>
    <p class="gedaempft">
        Auskunft nach Art. 15 DSGVO: alles, was zu deiner Person gespeichert ist,
        als JSON-Datei.
    </p>
    <a class="knopf knopf-schlicht" href="{{ route('account.export') }}">Auskunft herunterladen</a>

    <h2>Konto löschen</h2>
    <p class="gedaempft">
        Endgültig, nach Art. 17 DSGVO. Deine E-Mail-Adresse wird dabei
        anonymisiert und ist danach wieder für eine Neuanmeldung frei.
    </p>
    <form method="POST" action="{{ route('account.destroy') }}"
          onsubmit="return confirm('Konto wirklich unwiderruflich löschen?')">
        @csrf
        @method('DELETE')

        <label for="password_konto">Zur Bestätigung: dein Passwort</label>
        <input id="password_konto" name="password" type="password" required autocomplete="current-password">

        <button type="submit">Konto löschen</button>
    </form>

    @if ($istOwner)
        <h2>Familie „{{ $family->name }}" löschen</h2>
        <p class="gedaempft">
            Löscht die Familie mit allen Daten — auch für die
            {{ $weitereMitglieder }} weiteren Mitglieder. Nicht rückgängig zu machen.
        </p>
        <form method="POST" action="{{ route('account.family.destroy') }}"
              onsubmit="return confirm('Familie mit allen Daten löschen?')">
            @csrf
            @method('DELETE')

            <label for="bestaetigung">Familiennamen zur Bestätigung eingeben</label>
            <input id="bestaetigung" name="bestaetigung" type="text" required placeholder="{{ $family->name }}">

            <label for="password_familie">Dein Passwort</label>
            <input id="password_familie" name="password" type="password" required autocomplete="current-password">

            <button type="submit">Familie löschen</button>
        </form>
    @endif
@endsection
