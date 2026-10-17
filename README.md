# Wocheneinkauf

Die Einkaufsliste einer Familie — vom Vorrat über den Wochenplan bis vor das
Regal im Laden.

Mehrere Haushaltsmitglieder teilen sich eine Liste. Wer im Laden steht, sieht
sie nach den Regalbereichen *seines* Marktes sortiert und hakt mit dem Daumen
ab; wer zu Hause noch etwas einfällt, trägt es nach, und es erscheint drüben
innerhalb von Sekunden.

Gebaut für den eigenen Haushalt, veröffentlicht, weil die Aufgabe verbreitet
ist und die Lösungen es selten sind.

## Was es kann

| Bereich | |
|---|---|
| **Einkaufsliste** | Freitext wie „2 l Milch" wird zerlegt, einsortiert und nach Regalbereichen des Marktes geordnet |
| **Einkaufen** | Eigene Ansicht fürs Telefon: große Trefferflächen, Abhaken ohne Seitenneuaufbau, offline lesbar |
| **Wochenplan** | Ein Abendessen je Tag, Vorschläge mit Blick auf Abwechslung, auf Knopfdruck auf die Liste |
| **Rezepte** | Zutaten mit dem Artikelstamm verknüpft, nach Küche gegliedert, Import über eine Sprach-KI |
| **Vorrat** | Sollmengen je Artikel; die Bestandsaufnahme schlägt Fehlmengen vor — übernommen wird nach Bestätigung |
| **Stammdaten** | Warengruppen, Märkte mit Regalbereichen, Artikel; ein Grundsortiment mit 145 Artikeln ist ladbar |

Dazu: mehrere Familien sauber getrennt, Rollen vom Inhaber bis zum Kind, und
als PWA auf dem Startbildschirm installierbar.

Das **Anwenderhandbuch** liegt in [`docs/handbuch/`](docs/handbuch/) und wird
von der Anwendung selbst unter `/handbuch` ausgeliefert — die Markdown-Dateien
sind die einzige Quelle, es gibt keine zweite Fassung, die veralten könnte.

## Technisch

- **Laravel 13**, PHP **8.3+**, MariaDB
- Kein Node im Betrieb, kein Queue-Worker, kein Cron — es braucht PHP, eine
  Datenbank und einen SMTP-Zugang. Das schließt einfache Webhosting-Tarife
  nicht aus.
- **377 Tests**, 1.666 Zusicherungen, gegen eine echte MariaDB statt SQLite

Die Mandantentrennung liegt auf drei Ebenen: ein Global Scope auf jedem Model,
zusammengesetzte Fremdschlüssel `(id, family_id)` in der Datenbank, und
geprüfte Zugriffe beim Nachladen einzelner Datensätze. Der Scope scheitert
dabei **geschlossen** — ohne Familienkontext liefert er nichts statt alles.

Eine ausführliche technische Dokumentation samt UML liegt in
[`docs/Wocheneinkauf-Technische-Dokumentation.md`](docs/Wocheneinkauf-Technische-Dokumentation.md),
das Datenbankschema in
[`docs/Wocheneinkauf-Datenbank.md`](docs/Wocheneinkauf-Datenbank.md).

## Einrichten

Der Schnellstart für eine Entwicklungsumgebung:

```bash
git clone https://github.com/MarkusBrezing/Wocheneinkauf.git
cd Wocheneinkauf
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

**→ [docs/Installation.md](docs/Installation.md)** beschreibt die Einrichtung
auf einem eigenen Server vollständig: Voraussetzungen, Webserver-Konfiguration
für Apache und nginx, Dateirechte, erster Start — und die vier Fallstricke,
die sich erst im Betrieb zeigen und nie in der Entwicklung.

Zwei davon vorweg, weil sie am meisten Zeit kosten:

- **`APP_FALLBACK_LOCALE` darf nicht `de` sein.** Sonst steht bei einer
  fehlenden Übersetzung der Schlüssel selbst auf dem Bildschirm.
- **Der Mailversand ist keine Kür.** Die Anwendung verlangt eine bestätigte
  E-Mail-Adresse — ohne SMTP-Zugang kommt niemand über die Registrierung
  hinaus, auch der Inhaber nicht.

### Aktualisieren

Neue Fassungen erscheinen als Versionen (`v1.1.0`), als Git-Tag und als
Release. **[`CHANGELOG.md`](CHANGELOG.md)** sagt je Version, was neu ist und ob
beim Aktualisieren etwas zu tun ist — eine Migration, eine neue Einstellung.
Den Ablauf samt Sicherung und Rückweg beschreibt
[docs/Installation.md](docs/Installation.md#aktualisieren).

### Tests

Die Tests laufen gegen eine echte MariaDB, nicht gegen SQLite — generierte
Spalten und zusammengesetzte Fremdschlüssel verhalten sich dort anders, und
genau darauf ruht die Mandantentrennung. Eine Datenbank `wocheneinkauf_test`
anlegen, dann:

```bash
php artisan test
```

### Betrieb

[`docs/Betrieb-Strato.md`](docs/Betrieb-Strato.md) beschreibt die Einrichtung
auf einem Webhosting-Paket ohne Serverzugriff, inklusive der Wege ohne SSH.
[`docs/Deployment-Protokoll.md`](docs/Deployment-Protokoll.md) ist das
Protokoll einer tatsächlichen Inbetriebnahme — mit allem, was dabei
schiefging, und warum.

## Datenschutz

Kein Tracking, keine Analysewerkzeuge, keine Werbung, keine externen Inhalte,
keine fremden Schriften — und deshalb auch kein Einwilligungsbanner. Gesetzt
werden zwei technisch notwendige Cookies: die Sitzungskennung und ein
CSRF-Token.

## Lizenz

MIT — siehe [LICENSE](LICENSE).
