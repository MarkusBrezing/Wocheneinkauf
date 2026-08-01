@extends('layouts.app')
@section('title', 'Anmelden')

@section('inhalt')
    <h1>Anmelden</h1>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">E-Mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Passwort</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">

        <label style="font-weight:400">
            <input type="checkbox" name="remember" style="width:auto"> Angemeldet bleiben
        </label>

        <button type="submit">Anmelden</button>
    </form>

    <p class="gedaempft">
        <a href="{{ route('password.request') }}">Passwort vergessen?</a>
        &middot;
        <a href="{{ route('register') }}">Neues Konto anlegen</a>
    </p>
@endsection
