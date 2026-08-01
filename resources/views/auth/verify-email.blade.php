@extends('layouts.app')
@section('title', 'E-Mail bestätigen')

@section('inhalt')
    <h1>E-Mail bestätigen</h1>

    <p>
        Wir haben einen Bestätigungslink an <strong>{{ auth()->user()->email }}</strong>
        geschickt. Bitte öffne ihn, um dein Konto freizuschalten.
    </p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">Link erneut senden</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="knopf-schlicht">Abmelden</button>
    </form>
@endsection
