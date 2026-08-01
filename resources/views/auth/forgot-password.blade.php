@extends('layouts.app')
@section('title', 'Passwort zurücksetzen')

@section('inhalt')
    <h1>Passwort zurücksetzen</h1>

    <p class="gedaempft">
        Trag deine E-Mail-Adresse ein. Wenn ein Konto dazu existiert, schicken wir
        einen Link zum Setzen eines neuen Passworts.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <label for="email">E-Mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <button type="submit">Link anfordern</button>
    </form>

    <p class="gedaempft"><a href="{{ route('login') }}">Zurück zur Anmeldung</a></p>
@endsection
