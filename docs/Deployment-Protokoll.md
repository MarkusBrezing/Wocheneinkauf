# Deployment-Protokoll · Wocheneinkauf auf STRATO

Laufendes Protokoll der Inbetriebnahme. Jeder Schritt wird hier mit Ergebnis
festgehalten — auch die, die schiefgehen.

**Begonnen:** 16.08.2026
**Anleitung:** [Betrieb-Strato.md](Betrieb-Strato.md) — dort steht das *Wie*, hier das *Was wann*.

---

## Wer macht was

Ich habe **keinen Zugang zum STRATO-Server**: kein SSH, keine Zugangsdaten, kein
Kundenmenü. Zugangsdaten nehme ich auch nicht entgegen. Die Aufteilung ist deshalb:

| | Ich | Sie |
|---|---|---|
| Vorbereitung im Projekt | ✔ | |
| Befehle und Prüfschritte formulieren | ✔ | |
| Ausführen auf dem Server | | ✔ |
| Kundenmenü, Datenbank, Postfach | | ✔ |
| Befunde auswerten, Fehler beheben | ✔ | |

Sie führen aus und geben mir die Ausgabe; ich trage sie hier ein und sage, was
als Nächstes ansteht.

---

## Teil A · Lokale Vorbereitung

**Status: abgeschlossen am 16.08.2026**

| # | Schritt | Ergebnis |
|---|---|---|
| A1 | Vollständiger Testlauf | **330 Tests, 1399 Zusicherungen, grün** |
| A2 | Codeformat (`pint --test`) | sauber |
| A3 | Migrationsstand lokal | 24 Migrationen, alle ausgeführt |
| A4 | Laufzeit lokal | PHP 8.4.24, Laravel 13.23.0 |
| A5 | Frontend-Bau nötig? | **Nein.** Keine View nutzt `@vite`, alles CSS liegt in den Layouts |
| A6 | Warteschlangen-Dienst nötig? | **Nein.** Keine Klasse implementiert `ShouldQueue` |
| A7 | Cron nötig? | **Nein.** `routes/console.php` enthält keinen Zeitplan |
| A8 | Notbremse `.htaccess` im Projektwurzelverzeichnis | angelegt — liefert 403, falls eine Domain versehentlich auf den Projektordner zeigt |
| A9 | Umfang ohne `vendor/` | 3,3 MB |

### Befund A5–A7

Der Betrieb ist einfacher als bei einer üblichen Laravel-Anwendung: Es braucht
**PHP, eine Datenbank und einen SMTP-Zugang** — keinen dauerhaft laufenden
Prozess, keinen Node, keinen Cron. Das schließt auch die einfacheren
Webhosting-Tarife nicht aus.

---

## Teil B · Was vor dem ersten Schritt geklärt sein muss

Drei Angaben entscheiden über den Weg. Sie stehen ausschließlich in Ihrem
STRATO-Kundenmenü.

| # | Frage | Wo | Antwort |
|---|---|---|---|
| B1 | Welches Produkt? Webhosting, V-Server oder Managed Server | Kunden-Login, Paketübersicht | _offen_ |
| B2 | SSH-Zugang vorhanden? | Paketleistungen | _offen_ |
| B3 | PHP-Version einstellbar auf **8.3+**? | Hosting-Einstellungen | _offen_ |
| B4 | Datenbank: Host, Name, Benutzer, Passwort | Datenbankverwaltung | _angelegt? offen_ |
| B5 | SMTP-Zugang: Server, Port, Postfachadresse | E-Mail-Verwaltung | _offen_ |
| B6 | Zielverzeichnis der Domain frei setzbar? | Domainverwaltung → Zahnrad | **ja, bestätigt am 16.08.2026** |

> **B6 ist beantwortet:** Die interne Umleitung funktioniert, alle Domains
> zeigen auf einen Unterordner. Offen bleibt die Kontrolle, dass es der
> **richtige** Unterordner ist (`.../public`) und dass wirklich *keine* Adresse
> mehr auf `/` steht — siehe D1.

**Ohne B2 ändert sich der Weg:** Mit SSH wird auf dem Server gebaut (C2a), ohne
SSH lokal gebaut und hochgeladen (C2b).

---

## Teil C · Schritte auf dem Server

Noch nicht begonnen. Jeder Schritt nennt den Befehl, das erwartete Ergebnis und
hat ein Feld für das tatsächliche.

### C1 · Datenbank anlegen

Im Kundenmenü eine MariaDB-Datenbank samt Benutzer anlegen. Notieren: Host,
Name, Benutzer, Passwort.

- **Erwartet:** Zugangsdaten liegen vor
- **Ergebnis:** _offen_

### C2a · Code auf den Server (mit SSH)

```bash
git clone https://github.com/MarkusBrezing/Wocheneinkauf.git wocheneinkauf
cd wocheneinkauf && composer install --no-dev --optimize-autoloader
```

- **Erwartet:** `vendor/` liegt vor, keine Fehlermeldung
- **Ergebnis:** _offen_

### C2b · Code auf den Server (ohne SSH)

Lokal `composer install --no-dev --optimize-autoloader`, dann per FTP hochladen
— **einschließlich `vendor/`**. Nicht hochladen: `.env`, `node_modules/`,
`storage/logs/`, `.git/`.

- **Erwartet:** Verzeichnisbaum vollständig auf dem Server
- **Ergebnis:** _offen_

### C3 · `.env` anlegen

Vorlage: `.env.example`. Einzutragen sind `APP_ENV=production`,
`APP_DEBUG=false`, `APP_URL`, die Datenbankangaben aus C1 und der Mailzugang aus
C4. Danach:

```bash
php artisan key:generate
```

- **Erwartet:** `APP_KEY=base64:…` steht in der `.env`
- **Ergebnis:** _offen_

### C4 · Mailversand einrichten

Der kritische Schritt: Ohne ihn kommt niemand über die Registrierung hinaus
(`User` implementiert `MustVerifyEmail`).

```bash
php artisan tinker --execute="Mail::raw('Probe', fn (\$m) => \$m->to('IHRE@ADRESSE.DE')->subject('Wocheneinkauf'));"
```

- **Erwartet:** Die Mail kommt an
- **Ergebnis:** _offen_
- **Bei Fehlschlag:** `storage/logs/laravel.log` — der SMTP-Fehler steht dort im Klartext

### C5 · Schema anlegen

```bash
php artisan migrate --force
```

- **Erwartet:** 24 Migrationen laufen durch
- **Ergebnis:** _offen_

### C6 · Rechte und Zwischenspeicher

```bash
chmod -R 775 storage bootstrap/cache
```

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- **Erwartet:** keine Fehlermeldung
- **Ergebnis:** _offen_
- **Merken:** Nach *jeder* Änderung an der `.env` muss `config:cache` erneut laufen

### C7 · Zielverzeichnis und HTTPS

Domain auf `.../wocheneinkauf/public` zeigen lassen, Zertifikat aktivieren,
HTTP auf HTTPS umleiten.

- **Erwartet:** `https://ihre-domain.de` zeigt die Startseite
- **Ergebnis:** _offen_

### C8 · Rechtstexte füllen

`IMPRESSUM_NAME`, `IMPRESSUM_STRASSE`, `IMPRESSUM_ORT`, `IMPRESSUM_EMAIL` in die
`.env`, danach `config:cache`.

- **Erwartet:** `/impressum` zeigt nirgends mehr `[bitte eintragen]`
- **Ergebnis:** _offen_

---

## Teil D · Abnahme

### D1 · Kein Zugriff auf die `.env`

Für **jede** Adresse, unter der das Paket erreichbar ist — auch Subdomains und
die technische Adresse:

```bash
curl -sI https://ihre-domain.de/.env
```

- **Erwartet:** `404` (oder `403`, wenn die Notbremse aus A8 greift)
- **Ergebnis:** _offen_

### D2 · Systemstatus

```bash
curl -s https://ihre-domain.de/health
```

- **Erwartet:** `{"status":"ok","db":"up","migrationen":{"offen":0,…}}`
- **Ergebnis:** _offen_

### D3 · Registrierung von Hand

1. Konto anlegen → **kommt die Bestätigungsmail an?**
2. Bestätigen, anmelden
3. Stammdaten › Artikel → Grundsortiment laden (145 Artikel)
4. Einkaufsliste: „2 l Milch" aufschreiben

- **Ergebnis:** _offen_

### D4 · Auf dem Telefon

1. `/einkaufsliste/einkaufen` öffnen
2. Bietet der Browser „Zum Startbildschirm hinzufügen"?
3. Flugmodus einschalten, Seite neu laden → bleibt die Liste lesbar?

- **Ergebnis:** _offen_

---

## Teil E · Befunde und offene Punkte

| Datum | Befund |
|---|---|
| 16.08.2026 | Lokale Vorbereitung abgeschlossen, 330 Tests grün |
| 16.08.2026 | `.htaccess` als Notbremse ergänzt — war in der Anleitung beschrieben, aber nicht angelegt |
| | |

### Außerhalb des Codes, vor der öffentlichen Freigabe

- **Auftragsverarbeitungsvertrag mit STRATO.** Die Datenschutzerklärung behauptet
  bereits, dass er besteht — er muss auch vorliegen.
- **Löschkonzept** mit festen Fristen.
- **Juristische Prüfung** der Rechtstexte. Sie sind sorgfältig, aber keine
  Rechtsberatung.
