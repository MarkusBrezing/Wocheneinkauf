@extends('layouts.app')
@section('title', 'Einladung ungültig')

@section('inhalt')
    <h1>Diese Einladung gilt nicht mehr</h1>

    <p>
        Der Link ist abgelaufen, wurde widerrufen oder bereits so oft benutzt,
        wie es vorgesehen war. Bitte lass dir eine neue Einladung schicken.
    </p>

    <a class="knopf knopf-schlicht" href="{{ url('/') }}">Zur Startseite</a>
@endsection
