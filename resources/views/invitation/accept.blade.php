@extends('layouts.app')
@section('title', 'Einladung')

@section('inhalt')
    <h1>Einladung annehmen</h1>

    <p>
        Du wurdest eingeladen, bei <strong>{{ $einladung->family->name }}</strong>
        mitzumachen — als <em>{{ $einladung->role }}</em>.
    </p>

    <form method="POST" action="{{ route('invitation.accept', $token) }}">
        @csrf
        <button type="submit">Beitreten</button>
    </form>

    <p class="gedaempft"><a href="{{ route('dashboard') }}">Nein danke, zurück zur Übersicht</a></p>
@endsection
