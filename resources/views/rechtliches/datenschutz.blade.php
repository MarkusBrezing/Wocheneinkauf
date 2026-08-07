@extends('layouts.rechtliches')

@section('titel', 'Datenschutzerklärung')

@section('rechtstext')
    <h1>Datenschutzerklärung</h1>

    <p class="gedaempft">Stand: {{ now()->format('d.m.Y') }}</p>

    <h2>1 · Verantwortlicher</h2>
    <p>
        @angabe($betreiber['name'])<br>
        @angabe($betreiber['strasse'])<br>
        @angabe($betreiber['ort'])<br>
        E-Mail: @angabe($betreiber['email'])
    </p>

    <h2>2 · Welche Daten verarbeitet werden</h2>
    <p>Diese Anwendung ist eine Einkaufs- und Haushaltsplanung. Verarbeitet werden:</p>
    <table>
        <thead>
            <tr><th>Daten</th><th>Wozu</th><th>Rechtsgrundlage</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Name, E-Mail-Adresse, Passwort (als Hash)</td>
                <td>Anmeldung und Zuordnung zu einer Familie</td>
                <td>Art. 6 Abs. 1 lit. b DSGVO (Vertrag)</td>
            </tr>
            <tr>
                <td>Einkaufslisten, Artikel, Rezepte, Vorrat, Wochenpläne</td>
                <td>Der Zweck der Anwendung</td>
                <td>Art. 6 Abs. 1 lit. b DSGVO</td>
            </tr>
            <tr>
                <td>Einladungen an E-Mail-Adressen</td>
                <td>Aufnahme weiterer Familienmitglieder</td>
                <td>Art. 6 Abs. 1 lit. b DSGVO</td>
            </tr>
            <tr>
                <td>Server-Protokolle (IP-Adresse, Zeitpunkt, aufgerufene Seite)</td>
                <td>Betrieb und Abwehr von Angriffen</td>
                <td>Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse)</td>
            </tr>
        </tbody>
    </table>

    <h2>3 · Was <em>nicht</em> stattfindet</h2>
    <p>
        Es gibt kein Tracking, keine Analysewerkzeuge, keine Werbung und keine Einbindung
        externer Inhalte. Schriften werden vom eigenen Server geliefert beziehungsweise sind
        die des Betriebssystems -- es gibt also keine Verbindung zu Google Fonts oder einem
        Content Delivery Network. Aus demselben Grund verzichtet die Anwendung auf Cookies,
        die nicht für den Betrieb nötig sind; es gibt daher auch kein Einwilligungsbanner.
    </p>

    <h2>4 · Cookies</h2>
    <p>
        Gesetzt werden ausschließlich zwei technisch notwendige Cookies: die Sitzungskennung,
        damit die Anmeldung über mehrere Seitenaufrufe hält, und ein CSRF-Token zum Schutz vor
        untergeschobenen Formularen. Beide verfallen mit dem Ende der Sitzung oder beim Abmelden.
    </p>

    <h2>5 · Speicherung im Browser</h2>
    <p>
        Für die Einkaufsansicht kann der Browser die zuletzt geöffnete Liste zwischenspeichern,
        damit sie im Supermarkt auch ohne Empfang lesbar bleibt. Diese Daten liegen ausschließlich
        auf dem eigenen Gerät. Beim Abmelden werden sie gelöscht.
    </p>

    <h2>6 · Empfänger und Hosting</h2>
    <p>
        Die Anwendung läuft auf Servern von {{ config('rechtliches.hoster.name') }},
        {{ config('rechtliches.hoster.anschrift') }}. Mit dem Anbieter besteht ein Vertrag über
        Auftragsverarbeitung nach Art. 28 DSGVO. Eine Weitergabe an weitere Dritte findet nicht
        statt; Daten werden nicht in Länder außerhalb der EU übermittelt.
    </p>

    <h2>7 · Speicherdauer</h2>
    <p>
        Kontodaten werden gespeichert, solange das Konto besteht. Server-Protokolle werden nach
        spätestens 14 Tagen gelöscht. Gelöschte Listen und Artikel bleiben für eine Übergangszeit
        wiederherstellbar (Papierkorb) und werden anschließend endgültig entfernt.
    </p>

    <h2>8 · Rechte der betroffenen Personen</h2>
    <p>
        Es bestehen die Rechte auf Auskunft (Art. 15), Berichtigung (Art. 16), Löschung (Art. 17),
        Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch
        (Art. 21 DSGVO). Auskunft und Löschung lassen sich unmittelbar in der Anwendung auslösen:
        unter <em>Konto</em> gibt es einen vollständigen Datenexport und die Kontolöschung.
        Außerdem besteht ein Beschwerderecht bei einer Datenschutz-Aufsichtsbehörde.
    </p>

    <h2>9 · Datensicherheit</h2>
    <p>
        Die Übertragung erfolgt verschlüsselt (HTTPS). Passwörter werden ausschließlich als
        Hash gespeichert. Die Daten verschiedener Familien sind auf drei Ebenen getrennt:
        durch die Anwendung, durch Fremdschlüssel in der Datenbank und durch geprüfte Zugriffe
        beim Nachladen einzelner Datensätze.
    </p>
@endsection
