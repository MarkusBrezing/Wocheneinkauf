@extends('layouts.app')
@section('title', 'Neues Passwort setzen')

@section('inhalt')
    <h1>Neues Passwort setzen</h1>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label for="email">E-Mail</label>
        <input id="email" name="email" type="email"
               value="{{ old('email', $request->email) }}" required autocomplete="username">

        <label for="password">Neues Passwort</label>
        <input id="password" name="password" type="password" required autofocus autocomplete="new-password">

        <label for="password_confirmation">Passwort wiederholen</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

        <button type="submit">Passwort speichern</button>
    </form>
@endsection
