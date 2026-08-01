@extends('layouts.app')
@section('title', 'Passwort bestätigen')

@section('inhalt')
    <h1>Passwort bestätigen</h1>

    <p class="gedaempft">
        Dieser Schritt ist heikel genug, dass wir sicher sein wollen, dass du es bist.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <label for="password">Passwort</label>
        <input id="password" name="password" type="password" required autofocus autocomplete="current-password">

        <button type="submit">Bestätigen</button>
    </form>
@endsection
