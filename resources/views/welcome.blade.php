@extends('layouts.app')
@section('title', 'Wocheneinkauf')

@section('inhalt')
    <h1>Wocheneinkauf</h1>

    <p>
        Einkaufsliste, Vorrat und Wochenplanung für die ganze Familie —
        auf allen Geräten, auch im Supermarkt ohne Empfang.
    </p>

    <p>
        <a class="knopf" href="{{ route('register') }}">Konto anlegen</a>
        <a class="knopf knopf-schlicht" href="{{ route('login') }}">Anmelden</a>
    </p>
@endsection
