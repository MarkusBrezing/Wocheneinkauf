@extends('layouts.rechtliches')

@section('titel', 'Nutzungsbedingungen')

@section('rechtstext')
    <h1>Nutzungsbedingungen</h1>

    <p class="gedaempft">Stand: {{ now()->format('d.m.Y') }}</p>

    <h2>1 · Geltungsbereich</h2>
    <p>
        Diese Bedingungen gelten für die Nutzung der Anwendung „{{ config('app.name') }}"
        durch angemeldete Nutzerinnen und Nutzer. Mit der Registrierung werden sie anerkannt.
    </p>

    <h2>2 · Leistung</h2>
    <p>
        Die Anwendung stellt Einkaufslisten, einen Artikelkatalog, Rezepte, eine Vorratsführung
        und einen Wochenplan für den privaten Gebrauch bereit. Ein Anspruch auf ständige
        Verfügbarkeit besteht nicht; für Wartung und Weiterentwicklung kann der Betrieb
        vorübergehend unterbrochen werden.
    </p>

    <h2>3 · Konto</h2>
    <p>
        Ein Konto ist persönlich. Zugangsdaten sind geheim zu halten und dürfen nicht
        weitergegeben werden. Wer den Verdacht hat, dass Unbefugte Zugriff haben, ändert
        umgehend das Passwort.
    </p>

    <h2>4 · Familien und Rollen</h2>
    <p>
        Daten gehören einer Familie, nicht einer einzelnen Person. Alle Mitglieder einer Familie
        sehen deren Listen, Rezepte und Vorräte und können sie bearbeiten. Struktur­daten --
        Warengruppen, Märkte und Regalbereiche -- ändern nur Inhaberin oder Inhaber und
        Administratoren, weil sie für alle gelten. Wer eine Familie verlässt, verliert den
        Zugriff auf deren Daten; die Daten selbst bleiben der Familie.
    </p>

    <h2>5 · Inhalte</h2>
    <p>
        Für eingestellte Inhalte -- etwa selbst erfasste Rezepte -- ist die einstellende Person
        verantwortlich. Es dürfen keine Inhalte eingestellt werden, die Rechte Dritter verletzen,
        insbesondere keine wortgleich übernommenen Rezepttexte aus fremden Quellen. Ein Verweis
        auf die Quelle ist dafür vorgesehen (Feld „Quelle").
    </p>

    <h2>6 · Haftung</h2>
    <p>
        Die Anwendung wird ohne Gewähr für Richtigkeit und Vollständigkeit bereitgestellt.
        Die Haftung ist auf Vorsatz und grobe Fahrlässigkeit beschränkt; hiervon unberührt
        bleibt die Haftung für Schäden aus der Verletzung des Lebens, des Körpers oder der
        Gesundheit sowie nach dem Produkthaftungsgesetz. Angaben zu Nährwerten und
        Zutaten ersetzen keine Ernährungsberatung; bei Allergien ist stets die Verpackung
        maßgeblich.
    </p>

    <h2>7 · Beendigung</h2>
    <p>
        Das Konto lässt sich jederzeit ohne Angabe von Gründen unter <em>Konto</em> löschen.
        Der Betreiber kann ein Konto sperren, wenn gegen diese Bedingungen verstoßen wird.
    </p>

    <h2>8 · Änderungen</h2>
    <p>
        Änderungen dieser Bedingungen werden vor dem Inkrafttreten in der Anwendung angekündigt.
        Wer nicht einverstanden ist, kann das Konto löschen.
    </p>

    <h2>9 · Schlussbestimmungen</h2>
    <p>
        Es gilt deutsches Recht. Sollte eine Bestimmung unwirksam sein, bleibt der übrige
        Inhalt wirksam.
    </p>
@endsection
