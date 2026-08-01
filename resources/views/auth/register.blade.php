@extends('layouts.app')
@section('title', 'Konto anlegen')

@section('inhalt')
    <h1>Konto anlegen</h1>

    @if ($einladung ?? null)
        <p class="hinweis">
            Du wurdest zu <strong>{{ $einladung->family->name }}</strong> eingeladen.
            Nach der Registrierung gehörst du dieser Familie an.
        </p>
    @else
        <p class="gedaempft">
            Mit der Registrierung wird eine eigene Familie angelegt, in der du alle
            Rechte hast. Weitere Mitglieder kannst du danach einladen.
        </p>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        @if ($einladung ?? null)
            <input type="hidden" name="einladung" value="{{ $token }}">
        @endif

        <label for="name">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name">

        <label for="email">E-Mail</label>
        <input id="email" name="email" type="email"
               value="{{ old('email', $einladung->email ?? '') }}" required autocomplete="username">

        @unless ($einladung ?? null)
            <label for="family_name">Name der Familie <span class="gedaempft">(optional)</span></label>
            <input id="family_name" name="family_name" type="text" value="{{ old('family_name') }}"
                   placeholder="Familie Mustermann">
        @endunless

        <label for="password">Passwort</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">

        <label for="password_confirmation">Passwort wiederholen</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

        <button type="submit">Konto anlegen</button>
    </form>

    <p class="gedaempft"><a href="{{ route('login') }}">Ich habe schon ein Konto</a></p>
@endsection
