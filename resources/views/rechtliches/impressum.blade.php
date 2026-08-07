@extends('layouts.rechtliches')

@section('titel', 'Impressum')

@section('rechtstext')
    <h1>Impressum</h1>

    <p class="gedaempft">Angaben gemäß § 5 DDG (bis 13.05.2024: § 5 TMG).</p>

    <h2>Anbieter</h2>
    <p>
        @angabe($betreiber['name'])<br>
        @angabe($betreiber['strasse'])<br>
        @angabe($betreiber['ort'])<br>
        {{ $betreiber['land'] }}
    </p>

    <h2>Kontakt</h2>
    <p>
        E-Mail: @angabe($betreiber['email'])<br>
        @if ($betreiber['telefon'])
            Telefon: {{ $betreiber['telefon'] }}
        @endif
    </p>

    @if ($betreiber['ust_id'])
        <h2>Umsatzsteuer-Identifikationsnummer</h2>
        <p>{{ $betreiber['ust_id'] }}</p>
    @endif

    <h2>Verantwortlich für den Inhalt</h2>
    <p>@angabe($betreiber['name'])</p>

    <h2>Streitbeilegung</h2>
    <p>
        Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung bereit:
        <a href="https://ec.europa.eu/consumers/odr/" rel="noopener noreferrer" target="_blank">ec.europa.eu/consumers/odr</a>.
        Zur Teilnahme an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle
        sind wir weder verpflichtet noch bereit.
    </p>

    <h2>Haftung für Inhalte und Links</h2>
    <p>
        Für eigene Inhalte auf diesen Seiten sind wir nach den allgemeinen Gesetzen verantwortlich.
        Wir sind allerdings nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen
        zu überwachen. Für die Inhalte verlinkter externer Seiten ist deren jeweiliger Anbieter
        verantwortlich; zum Zeitpunkt der Verlinkung waren keine Rechtsverstöße erkennbar.
        Bei Bekanntwerden von Rechtsverletzungen entfernen wir die betroffenen Inhalte umgehend.
    </p>
@endsection
