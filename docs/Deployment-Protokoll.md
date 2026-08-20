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
| A1 | Vollständiger Testlauf | **335 Tests, 1421 Zusicherungen, grün** |
| A2 | Codeformat (`pint --test`) | sauber |
| A3 | Migrationsstand lokal | 24 Migrationen, alle ausgeführt |
| A4 | Laufzeit lokal | PHP 8.4.24, Laravel 13.23.0 |
| A5 | Frontend-Bau nötig? | **Nein.** Keine View nutzt `@vite`, alles CSS liegt in den Layouts |
| A6 | Warteschlangen-Dienst nötig? | **Nein.** Keine Klasse implementiert `ShouldQueue` |
| A7 | Cron nötig? | **Nein.** `routes/console.php` enthält keinen Zeitplan |
| A8 | Notbremse `.htaccess` im Projektwurzelverzeichnis | angelegt — liefert 403, falls eine Domain versehentlich auf den Projektordner zeigt |
| A9 | Umfang ohne `vendor/` | 3,3 MB |
| A10 | Sicherheitsprüfung der Anwendung | **1 Fund behoben** (Stored XSS), 4 Härtungen — siehe Teil E |
| A11 | Auslieferungspaket gebaut | `wocheneinkauf-strato.tar.gz` (5,7 MB) und `.zip` (9,5 MB), entpackt 57 MB — mit `vendor/`, ohne Dev-Pakete, ohne `.env`, ohne `.git` |
| A12 | `.env.produktion` als ausgefüllte Vorlage | im Paket — nur die `<…>`-Werte fehlen |

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
| B2 | SSH-Zugang vorhanden? | Paketleistungen | **ja, eingerichtet am 20.08.2026** |
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

> **B2 ist beantwortet (20.08.2026): SSH ist da.** Damit gilt der Hauptweg —
> C3, C5, C6 wie beschrieben. Die Ersatzschritte C3b/C5b/C6b und
> `schema-strato.sql` bleiben als Rückfallebene stehen, falls die Konsole
> ausfällt oder `migrate` scheitert.
>
> **Die Fallgrube bei STRATO ist die PHP-Version in der Konsole.** Das Webhosting
> und die Kommandozeile benutzen dort oft *verschiedene* PHP-Binärdateien: im
> Kundenmenü steht 8.3, `php -v` in der Shell meldet 7.4. Die Anwendung verlangt
> ^8.3 — ein `composer install` oder `migrate` unter der falschen Version
> scheitert oder, schlimmer, läuft halb durch. Deshalb ist S1 die erste
> Amtshandlung, nicht die zweite.

---

## Teil C · Schritte auf dem Server

Jeder Schritt nennt den Befehl, das erwartete Ergebnis und hat ein Feld für das
tatsächliche.

### S1 · Standortbestimmung in der Shell

Vor allem anderen. Der Block beantwortet in einem Durchgang, wo das Projekt
liegt, welche PHP-Version die Konsole benutzt, welche weiteren PHP-Binärdateien
es gibt und welche Werkzeuge vorhanden sind.

```bash
echo "── Ort ──"; pwd; echo "── PHP in der Shell ──"; php -v | head -1
echo "── weitere PHP-Binärdateien ──"; ls -d /usr/bin/php* /usr/local/bin/php* /opt/php*/bin/php 2>/dev/null
echo "── Projekt ──"; find ~ -maxdepth 5 -name artisan 2>/dev/null
echo "── Werkzeuge ──"; for w in composer git unzip mysql; do printf '%-10s %s\n' "$w" "$(command -v $w || echo '—')"; done
echo "── Grenzen ──"; php -r 'echo "memory_limit=", ini_get("memory_limit"), " ", (function_exists("proc_open")?"proc_open ok":"proc_open GESPERRT"), PHP_EOL;'
```

- **Erwartet:** PHP 8.3 oder höher, `artisan` wird gefunden
- **Ergebnis 20.08.2026:** Heimatverzeichnis `/mnt/<server>/<kennung>/htdocs`,
  **PHP 8.4.24 (cgi-fcgi)** in der Shell — dieselbe Version wie lokal, kein
  Versionsproblem. Neben dem Standard-`php` liegen `php56` bis `php85` bereit.
  `artisan` unter `htdocs/wocheneinkauf/paket/artisan`. `git`, `unzip` und der
  `mysql`-Client vorhanden, **`composer` fehlt** — belanglos, `vendor/` kommt
  aus dem Paket
- **Wenn `php -v` unter 8.3 meldet:** nicht weitermachen, sondern die passende
  Binärdatei aus der zweiten Zeile verwenden — also z. B. `/usr/bin/php8.3 artisan …`
  statt `php artisan …`. Ein Alias in `~/.bashrc` erspart das Tippen
- **Wenn `proc_open` gesperrt ist:** `composer` läuft nicht. Dann bleibt es beim
  hochgeladenen `vendor/` aus dem Paket — was ohnehin der eingeschlagene Weg ist

#### S1a · Es gibt kein CLI-PHP — jeder Befehl braucht `-q`

Auf diesem Paket meldet **jede** der zwölf PHP-Binärdateien `cgi-fcgi`, auch
`/usr/local/bin/php` und `/usr/bin/php84`. Eine Kommandozeilenvariante ist nicht
installiert. Das ist bei STRATO-Webhosting der Normalfall, kein Defekt.

Die Folgen und der Umgang damit:

| Symptom | Ursache | Abhilfe |
|---|---|---|
| `X-Powered-By: PHP/8.4.24` vor jeder Ausgabe | die SAPI hält sich für einen Webserver und sendet HTTP-Kopfzeilen | `php -q artisan …` |
| `Status: 500 Internal Server Error` statt einer Fehlermeldung | Ausnahmen werden in einen HTTP-Status verpackt, der Klartext geht verloren | `-q`, dann steht die Meldung wieder da |
| Befehl meldet Erfolg, hat aber nichts getan | Rückgabewerte sind unter CGI unzuverlässig | **nie auf die Meldung verlassen** — den Zustand prüfen |

Die letzte Zeile ist die wichtigste. Statt `befehl && naechster` wird jeder
Schritt einzeln ausgeführt und das Ergebnis nachgesehen: `grep -c
'^APP_KEY=base64:' .env` statt „`key:generate` sagte ok", `migrate:status | grep
-c Ran` statt „`migrate` lief durch".

### S2 · Die Zwischenebene `paket/` auflösen

Mit SSH ist das Verschieben eine Sekunde statt eines zweiten Uploads. Damit
zeigt die Domain wieder auf den ursprünglich geplanten Pfad.

```bash
cd ~/wocheneinkauf && shopt -s dotglob && mv paket/* . && rmdir paket && ls -a
```

- **Erwartet:** `app/`, `public/`, `vendor/`, `.htaccess`, `.env.produktion`
  liegen direkt in `wocheneinkauf/`, `paket/` ist weg
- **Ergebnis:** _offen_
- **`shopt -s dotglob` ist der Kern:** ohne diese Zeile lässt `mv paket/*` die
  Punkt-Dateien zurück, `rmdir` scheitert — und `.htaccess` sowie
  `.env.produktion` bleiben im alten Ordner liegen
- **Danach:** Zielverzeichnis der Domain wieder auf `.../wocheneinkauf/public`.
  Wer S2 überspringt, lässt es auf `.../wocheneinkauf/paket/public` — beides ist
  richtig, nur nicht gemischt

### C1 · Datenbank anlegen

Im Kundenmenü eine MariaDB-Datenbank samt Benutzer anlegen. Notieren: Host,
Name, Benutzer, Passwort.

- **Erwartet:** Zugangsdaten liegen vor
- **Ergebnis 20.08.2026:** angelegt. MariaDB **11.8.8**, Datenbank `<datenbankname>`,
  Host `<datenbankserver>.webspace-host.com` — also wie erwartet **nicht**
  `localhost`, sondern ein eigener Datenbankserver

### C2a · Code auf den Server (mit SSH)

```bash
git clone https://github.com/MarkusBrezing/Wocheneinkauf.git wocheneinkauf
cd wocheneinkauf && composer install --no-dev --optimize-autoloader
```

- **Erwartet:** `vendor/` liegt vor, keine Fehlermeldung
- **Ergebnis:** _offen_

### C2b · Code auf den Server (ohne SSH)

Das Paket aus A11 ist fertig — es enthält `vendor/` bereits, ohne Dev-Pakete.
Entpacken und den **Inhalt** (nicht den Ordner) per FTP nach
`/wocheneinkauf/` hochladen.

- **Erwartet:** Verzeichnisbaum vollständig auf dem Server, `vendor/` dabei
- **Ergebnis:** _offen_
- **Achtung:** Manche FTP-Programme übertragen Dateien mit führendem Punkt
  nicht. `.htaccess` in `public/` **und** im Projektwurzelverzeichnis muss
  ankommen — sonst greift weder die Laravel-Umleitung noch die Notbremse

### C3 · `.env` anlegen

Die mitgelieferte `.env.produktion` in `.env` umbenennen und die mit `<…>`
markierten Werte ausfüllen — Datenbank aus C1, Mailzugang aus C4, Impressum aus
C8. Alles Übrige ist bereits richtig eingestellt, auch
`SESSION_SECURE_COOKIE=true`. Danach:

```bash
php artisan key:generate
```

- **Erwartet:** `APP_KEY=base64:…` steht in der `.env`
- **Ergebnis 20.08.2026:** gesetzt. Zwei Anläufe nötig — siehe Befund unten
- **Stolperstelle beim Ausfüllen:** Beim Einfügen der Domain landete
  `https://einkauf.brezing.info/` in **Zeile 1** statt bei `APP_URL`, direkt vor
  dem Kommentar und ohne Zeilenumbruch. Die `.env` war damit unlesbar, und
  `key:generate` konnte nicht laufen — Artisan liest die `.env` beim Start, also
  scheitert auch der Befehl, der sie reparieren soll. Unter der CGI-Variante
  zeigte sich das nur als `Status: 500`, nicht als Meldung.
- **Die Datei auf unlesbare Zeilen prüfen** — listet alles, was weder Kommentar
  noch `NAME=Wert` ist:

  ```bash
  grep -vnE '^\s*(#|$|[A-Za-z_][A-Za-z0-9_]*=)' .env
  ```

### C3b · `APP_KEY` ohne Konsole auf dem Server

`key:generate` erzeugt nur eine Zufallszahl und schreibt sie in die `.env` —
das geht genauso gut von Hand. **Lokal** ausführen:

```bash
cd /home/dev/Wocheneinkauf && php artisan key:generate --show
```

Die Ausgabe (`base64:…`) in die `.env` auf dem Server hinter `APP_KEY=`
eintragen. Der Wert ist ein Geheimnis wie ein Passwort: nicht in Git, nicht in
eine Chatzeile, nicht in eine Mail.

- **Wirkung eines falschen oder fehlenden Werts:** jede Seite antwortet mit 500
- **Wirkung eines späteren Wechsels:** alle Sitzungen brechen ab, verschlüsselte
  Spalten werden unlesbar. Einmal setzen, dann nie wieder anfassen
- **Ergebnis:** _offen_

### C4 · Mailversand einrichten

Der kritische Schritt: Ohne ihn kommt niemand über die Registrierung hinaus
(`User` implementiert `MustVerifyEmail`).

```bash
php artisan tinker --execute="Mail::raw('Probe', fn (\$m) => \$m->to('IHRE@ADRESSE.DE')->subject('Wocheneinkauf'));"
```

- **Erwartet:** Die Mail kommt an
- **Ergebnis 20.08.2026:** kommt an — **landete aber im Junk-Ordner**
- **Bei Fehlschlag:** `storage/logs/laravel.log` — der SMTP-Fehler steht dort im Klartext

#### C4a · Junk-Ordner: SPF fehlte bei gleichzeitig scharfem DMARC

Der Versand funktionierte auf Anhieb, die Zustellung nicht. Die DNS-Abfrage
zeigte, warum:

| Eintrag | Stand vorher |
|---|---|
| SPF auf `brezing.info` | **fehlte** |
| DMARC auf `_dmarc.brezing.info` | `v=DMARC1;p=reject;` |

`p=reject` ist die schärfste DMARC-Stufe — sie weist empfangende Server an,
nicht beglaubigte Mail **abzuweisen**. Ohne SPF fehlte einer der beiden
Beglaubigungswege ganz. Dass Gmail nur einsortiert und nicht verworfen hat,
lag vermutlich an der DKIM-Signatur, die STRATO automatisch setzt. Verlassen
kann man sich darauf nicht: Bei `p=reject` wären die Bestätigungsmails der
Registrierung bei anderen Empfängern nicht im Junk gelandet, sondern
verschwunden.

- **Behoben am 20.08.2026:** TXT-Eintrag `v=spf1 redirect=_spf.strato.com`
- **Gegengeprüft:** `dig +short TXT brezing.info` liefert den Eintrag
- **SPF gehört auf die Domain der Absenderadresse**, nicht auf die der Webseite
- **Nur ein SPF-Satz je Name.** Zwei machen die Prüfung ungültig — schlimmer als keiner
- **Noch offen:** Praxistest. Eine Mail an Gmail schicken, dort *Nachricht ›
  Original anzeigen* — erst wenn SPF, DKIM und DMARC alle auf `PASS` stehen,
  ist `p=reject` unbedenklich

### C5 · Schema anlegen

```bash
php artisan migrate --force
```

- **Erwartet:** 24 Migrationen laufen durch
- **Ergebnis 20.08.2026:** 35 Tabellen, `migrate:status` meldet **24 gelaufen,
  0 offen**, Gesamtgröße 2,02 MB

### C5b · Schema ohne Konsole anlegen

Statt `migrate` wird eine fertige SQL-Datei in phpMyAdmin eingespielt. Sie
liegt im Paket und heißt `schema-strato.sql` (41 KB).

1. Kundenmenü › Datenbankverwaltung › **phpMyAdmin** öffnen
2. Links die Datenbank aus C1 auswählen — **nicht** die Serverwurzel
3. Reiter **Importieren** › Datei wählen › unten **OK**

Die Datei enthält alle 35 Tabellen samt Fremdschlüsseln und trägt den
Migrationsstand nach. Ein späteres `php artisan migrate` sieht deshalb
**0 offene Migrationen** und legt nichts doppelt an.

- **Erwartet:** phpMyAdmin meldet die Ausführung ohne Fehler, links stehen 35 Tabellen
- **Ergebnis:** _offen_
- **Achtung:** Die Datei beginnt jede Tabelle mit `DROP TABLE IF EXISTS`. Auf
  einer leeren Datenbank harmlos — auf einer **im Betrieb befindlichen** löscht
  sie alle Daten. Nur für die Ersteinrichtung
- **Belegt am 19.08.2026:** eingespielt und gegen das Original verglichen —
  35 Tabellen, 286 Spalten, 54 Fremdschlüssel, 31 Unique-Bedingungen, die
  generierte Spalte `aktiv_marker`, 24 Migrationen. Keine Abweichung.
  `migrate:status` auf der eingespielten Datenbank: 24 Ran, 0 Pending

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

### C6b · Ohne Konsole: beides entfällt

- **`chmod`:** Der FTP-Client setzt Rechte selbst — bei FileZilla per Rechtsklick
  › Dateiberechtigungen › `775`, „In Unterverzeichnisse übertragen" ankreuzen.
  Nötig für genau zwei Ordner: `storage/` und `bootstrap/cache/`. Fehlen die
  Rechte, ist der erste Seitenaufruf ein 500er, und `storage/logs/` bleibt leer —
  weil Laravel nicht einmal die Logdatei anlegen kann
- **`config:cache`:** entfällt ersatzlos. Der Zwischenspeicher ist eine
  Beschleunigung, keine Voraussetzung; ohne ihn liest Laravel die `.env` bei
  jedem Aufruf. Das kostet Millisekunden und hat den Vorteil, dass eine Änderung
  an der `.env` sofort wirkt, statt bis zum nächsten `config:cache` zu warten

### C7 · Zielverzeichnis und HTTPS

Domain auf `.../wocheneinkauf/public` zeigen lassen, Zertifikat aktivieren,
HTTP auf HTTPS umleiten.

- **Erwartet:** `https://ihre-domain.de` zeigt die Startseite
- **Ergebnis 20.08.2026: abgeschlossen.** Die Anwendung läuft unter
  **`https://brezing.info`** und `https://www.brezing.info`, HTTP wird per 301
  umgeleitet.

  **Nicht** unter `einkauf.brezing.info` — dafür war kein Zertifikat zu
  bekommen, siehe unten. Diese Subdomain zeigt jetzt bewusst auf
  `/wocheneinkauf` (das Projektwurzelverzeichnis) und liefert damit über die
  Notbremse einen sauberen **403**, statt eine unverschlüsselte Anmeldemaske
  anzubieten.

  Der Weg dorthin, weil er sich wiederholen kann:

  Das am 20.08.2026 ausgestellte Zertifikat deckte die falschen Namen ab:

  ```
  subject   CN = brezing.info
  SAN       DNS:brezing.info, DNS:www.brezing.info
  gültig    20.08.2026 – 16.02.2027   (Sectigo DV)
  ```

  `einkauf.brezing.info` fehlt. Der Server bricht den Handschlag deshalb mit
  **Alarm 40** ab und liefert gar kein Zertifikat aus
  (`no peer certificate available`) — er hat für diese SNI keines und bietet
  bewusst kein unpassendes an.

  **Ursache:** SSL wurde auf der Hauptdomain aktiviert. Haupt- und Subdomain
  sind im Kundenmenü getrennte Einträge mit je eigenem SSL-Schalter. Ein
  DV-Zertifikat deckt genau die Namen ab, die in seinem SAN stehen; Subdomains
  erbt es nicht.

  **Diagnose zum Nachschlagen** — zeigt sofort, welche Namen ein Zertifikat
  wirklich abdeckt:

  ```bash
  echo | openssl s_client -servername DOMAIN -connect DOMAIN:443 2>/dev/null | openssl x509 -noout -subject -dates -ext subjectAltName
  ```

  **Lösung:** STRATO bietet für Subdomains kein enthaltenes Zertifikat an. Statt
  eines zuzukaufen wurde die Anwendung auf die Hauptdomain gelegt, für die das
  Zertifikat bereits galt. `APP_URL` entsprechend geändert, danach
  `config:cache` erneut ausgeführt.

- **Merksatz zu `SESSION_SECURE_COOKIE=true`:** Solange kein Zertifikat aktiv
  war, verhinderte der Schalter die Anmeldung über unverschlüsselte
  Verbindungen — und war damit das Einzige, was eine offene Anmeldemaske vom
  offenen Netz trennte. **Zum „schon mal Testen" nicht auf `false` setzen.**
- **Befund 20.08.2026 — die Notbremse hat gegriffen:** Das Zielverzeichnis zeigte
  auf `/wocheneinkauf`, also auf das Projektwurzelverzeichnis statt auf
  `public/`. Ergebnis: **403 auf jede Adresse**, auch auf statische Dateien.
  Das war kein Defekt, sondern die `.htaccess` aus A8 bei der Arbeit — ohne sie
  wäre `/.env` mit Datenbankzugang und `APP_KEY` im Klartext abrufbar gewesen,
  und der Fehler wäre gar nicht aufgefallen, weil die Seite funktioniert hätte.
  Nach Korrektur auf `/wocheneinkauf/public`: 200.

  Erkennungsmerkmal für den nächsten Fall: Der 403 kam von Apache selbst
  (239 Bytes, `charset=iso-8859-1`), nicht von Laravel. Antwortet die Anwendung,
  sind die Seiten UTF-8 und deutlich größer.
- **Befund 19.08.2026:** Der Upload hat den Ordner `paket` mit übertragen statt
  nur seinen Inhalt. Auf dem Server liegt deshalb `/wocheneinkauf/paket/…`.
  Das ist kein Fehler — die Verschachtelung ist gleichgültig, solange das
  Zielverzeichnis eine Ebene tiefer zeigt:

  ```
  /wocheneinkauf/paket/public
  ```

  Umkopieren wäre der teurere Weg (8.055 Dateien) und bringt nichts.

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
- **Ergebnis 20.08.2026: bestanden.** Über HTTPS je **404** für `/.env`,
  `/storage/logs/laravel.log` und `/vendor/autoload.php` — und zwar Laravels
  404-Seite, nicht Apaches. Die Dateien liegen also außerhalb des
  Dokumentenstamms, es gibt nichts auszuliefern.
  `http://einkauf.brezing.info/` liefert **403**

### D1b · Schutzkopfzeilen kommen an

```bash
curl -sI https://ihre-domain.de/ | grep -i "content-security\|x-frame\|x-content\|referrer"
```

- **Erwartet:** vier Zeilen. Fehlen sie, filtert der Server sie heraus
- **Ergebnis 20.08.2026:** alle vier da — CSP mit `frame-ancestors 'none'`,
  `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: same-origin`. Apache/2.4.68 filtert nichts heraus
- **Nachgerüstet am 20.08.2026, weil die Abnahme es aufdeckte:** HTTP lieferte
  200 statt einer Umleitung — die Anwendung war unverschlüsselt vollständig
  benutzbar. Ein Anmeldeversuch hätte das Passwort im Klartext verschickt; er
  wäre danach zwar an `SESSION_SECURE_COOKIE` gescheitert, aber das Passwort
  wäre längst unterwegs gewesen. Zwei Ergänzungen:
  - **301 auf HTTPS** in `public/.htaccess`, mit zwei Bedingungen (`HTTPS=on`
    für Apache mit eigenem TLS, `X-Forwarded-Proto` für den Fall eines
    vorgelagerten Load Balancers)
  - **`Strict-Transport-Security: max-age=31536000`**, nur über HTTPS gesetzt.
    Schließt die Lücke, die eine Umleitung offen lässt: Die *erste* Anfrage
    ginge sonst jedes Mal im Klartext hinaus. Bewusst **ohne**
    `includeSubDomains` — das erzwänge HTTPS auch für `einkauf.brezing.info`,
    die kein Zertifikat hat und dann für ein Jahr unerreichbar wäre
  - **Rückbau bedenken:** Ein Jahr `max-age` hält sich der Browser auch dann,
    wenn die Kopfzeile später verschwindet. Fällt das Zertifikat aus, ist die
    Seite für diese Besucher nicht mehr erreichbar, auch nicht über HTTP
- **Ergebnis nach der Nachrüstung:** `http://brezing.info/` und
  `http://www.brezing.info/` antworten mit **301**, HSTS kommt an

### D1c · Das Handbuch hat Kapitel

Nachgetragen, weil genau das beim ersten Upload fehlte. `/handbuch` antwortet
auch ohne die Markdown-Dateien mit 200 — nur eben mit einer leeren Liste. Der
Fehler zeigt sich erst ein Klick weiter.

```bash
curl -s https://ihre-domain.de/handbuch | grep -c 'href="/handbuch/'
```

- **Erwartet:** 11 oder mehr. Bei **0** fehlt `docs/handbuch/` auf dem Server
- **Ergebnis 20.08.2026:** zunächst 0 — behoben, siehe Teil E

### D1d · Die Anwendung spricht Deutsch

Nachgetragen, weil auf der Registrierungsseite `validation.min.string` stand.
Der Aufruf erzwingt einen Validierungsfehler und sieht nach, ob eine Meldung
oder ein Schlüssel zurückkommt:

```bash
curl -s -X POST https://ihre-domain.de/register -d "name=Test" | grep -c 'validation\.'
```

- **Erwartet:** `0`. Jede Zahl darüber heißt: `lang/de/` fehlt auf dem Server,
  oder `APP_FALLBACK_LOCALE` steht auf `de`
- **Ergebnis 20.08.2026:** zunächst Treffer — behoben, siehe Teil E

### D2 · Systemstatus

```bash
curl -s https://ihre-domain.de/health
```

- **Erwartet:** `{"status":"ok","db":"up","migrationen":{"offen":0},…}`
- **Ergebnis 20.08.2026:** `{"status":"ok","db":"up","migrationen":{"offen":0},"time":"2026-08-20T01:04:52+00:00"}`

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
| 16.08.2026 | **Stored XSS gefunden und behoben.** Artikel-, Rezept- und Marktnamen standen roh in `confirm()`-Aufrufen. Blade macht aus `'` ein `&#039;`, der HTML-Parser macht daraus wieder `'` — *bevor* JavaScript den Text sieht. Ein Name wie `Mehl');alert(1);//` brach damit aus dem String aus. Betroffen waren 13 Stellen; alle laufen jetzt über `Js::from()`. Ausnutzbar war es für jedes Familienmitglied gegen jedes andere, auch die Rolle `child` gegen den Inhaber |
| 16.08.2026 | Schutzkopfzeilen ergänzt: CSP, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy` |
| 16.08.2026 | `/health` gekürzt — Laravel- und PHP-Version sowie Migrationsnamen standen ohne Anmeldung im Netz |
| 16.08.2026 | Mindestlänge für Passwörter von 8 auf 12 Zeichen |
| 16.08.2026 | `SESSION_SECURE_COOKIE` in `.env.example` aufgenommen — **im Betrieb auf `true` setzen** |
| 16.08.2026 | Nach der Reparatur: 335 Tests grün, davon 5 neue Sicherheitstests |
| 19.08.2026 | Upload angekommen, aber als `/wocheneinkauf/paket/…` statt `/wocheneinkauf/…`. Lösung ohne Dateibewegung: Zielverzeichnis auf `/wocheneinkauf/paket/public` — siehe C7 |
| 19.08.2026 | **Der Weg ohne SSH ist vollständig.** `key:generate` → lokal mit `--show` (C3b), `migrate` → `schema-strato.sql` in phpMyAdmin (C5b), `config:cache` → entfällt, `chmod` → FTP-Client (C6b). Damit ist kein einziger Schritt mehr an eine Serverkonsole gebunden |
| 19.08.2026 | `schema-strato.sql` erzeugt und **gegengeprüft**: eingespielt in eine leere Datenbank, Struktur gegen das Original verglichen — 35 Tabellen, 286 Spalten, 54 Fremdschlüssel, 31 Unique, 1 generierte Spalte, 24 Migrationen, keine Abweichung. `php artisan schema:dump` war nicht nutzbar: kein `mysqldump` auf dem Entwicklungsrechner |
| 19.08.2026 | Bewusst **nicht** gebaut: ein Einrichtungs-Endpunkt, der `migrate` über den Browser auslöst. Eine solche Route ist vor der ersten Anmeldung zwangsläufig unauthentifiziert und damit ein dauerhaftes Risiko für einen einmaligen Nutzen |
| 19.08.2026 | Testdatenbank war Prüfstand für den Import und ist wiederhergestellt: **335 Tests, 1421 Zusicherungen, grün** (166 s) |
| 20.08.2026 | **Kein CLI-PHP auf dem Paket.** Alle zwölf Binärdateien melden `cgi-fcgi`. Jeder Artisan-Aufruf braucht `-q`, und Rückgabewerte sind wertlos — geprüft wird der Zustand, nicht die Erfolgsmeldung. Siehe S1a |
| 20.08.2026 | **Die Notbremse aus A8 hat gegriffen.** Zielverzeichnis zeigte auf `/wocheneinkauf` statt `/wocheneinkauf/public` → 403 auf alles. Ohne die `.htaccess` wäre die `.env` öffentlich lesbar gewesen, und der Fehler wäre unbemerkt geblieben, weil die Seite funktioniert hätte |
| 20.08.2026 | **`public/.htaccess` brauchte `Require all granted`.** Das Projekt liegt bei STRATO innerhalb des Dokumentenstamms (`htdocs/wocheneinkauf/`). Apache liest die `.htaccess` jedes übergeordneten Verzeichnisses mit, das `Require all denied` aus der Projektwurzel vererbt sich also bis in `public/` — die Anwendung hätte auch bei **richtigem** Zielverzeichnis 403 geliefert. Beide Dateien sind ein Paar; ein Test in `SicherheitTest` hält sie jetzt zusammen |
| 20.08.2026 | **SPF fehlte bei gleichzeitigem DMARC `p=reject`** — siehe C4a. Behoben |
| 20.08.2026 | Abnahme über HTTP bestanden: Startseite 200, `/symbole/icon-192.png` 200, `/.env` 404, `/health` meldet `db:up` und 0 offene Migrationen, alle vier Schutzkopfzeilen kommen an |
| 20.08.2026 | **Kein Zertifikat für Subdomains bei STRATO.** Das enthaltene DV-Zertifikat deckt `brezing.info` und `www.brezing.info` ab, nicht `einkauf.brezing.info`. Die Anwendung wurde deshalb auf die Hauptdomain gelegt; die alte Subdomain zeigt jetzt auf das Projektwurzelverzeichnis und liefert über die Notbremse 403 statt einer unverschlüsselten Anmeldemaske |
| 20.08.2026 | **HTTP leitete nicht auf HTTPS um** — von der Abnahme aufgedeckt, nicht vom Testlauf: Die Regel lebt in `public/.htaccess`, und die prüft kein Test der Anwendung. 301-Umleitung und HSTS nachgerüstet, ein Test hält HSTS jetzt auf HTTPS fest und aus HTTP heraus |
| 20.08.2026 | Betriebsnotiz: `scp` läuft auf dem **lokalen** Rechner, nicht in der SSH-Sitzung. Aus der Sitzung heraus meldet es „No such file or directory" für den lokalen Pfad — die Übertragung schlägt fehl, ohne dass etwas kaputtgeht |
| 20.08.2026 | **`docs/handbuch/` ist Laufzeitbestandteil, nicht Dokumentation.** Der FTP-Lauf hat `docs/` ausgelassen — `vendor/` war vollständig, nur dieser Ordner fehlte. Folge: `/handbuch` rendert eine **leere Liste statt eines Fehlers**, und erst ein Klick weiter kommt der 404. Genau die Sorte Fehler, die eine Abnahme von außen findet und ein Testlauf nie: Lokal sind die Dateien immer da. Beim Paketbau und nach jedem Upload gehört `docs/handbuch/*.md` mitgeprüft |
| 20.08.2026 | Nach dem Upload lagen drei überflüssige Dateien im Projektwurzelverzeichnis: `.env.sicherung` (**Zugangsdaten im Klartext**), `.env.produktion` und `wocheneinkauf-strato.zip` (9,5 MB, die Anwendung ein zweites Mal). Über das Netz war keine erreichbar — sie liegen außerhalb von `public/`, und die Notbremse deckt den Ordner ab. Trotzdem entfernt |
| 20.08.2026 | Vollständigkeit des Uploads geprüft — Paket gegen Server, `find . -type f` beidseits: 8.055 zu 8.106 Dateien, Abweichung restlos aufgeklärt (fehlend `docs/`, zusätzlich `.env`-Varianten, das Zip und 74 Dateien in `storage/` und `bootstrap/cache/`, die zur Laufzeit entstehen) |
| 20.08.2026 | **Kein `lang/`-Verzeichnis — die Registrierung zeigte `validation.min.string`.** Drei Umstände trafen zusammen: Laravel 13 liefert keine Sprachdateien mit (die englischen Texte kommen aus dem Framework, deutsche gibt es dort nicht); die Produktionsvorlage setzte Haupt- **und** Rückfallsprache auf `de`, es gab also nichts, worauf zurückzufallen wäre; und lokal gilt `APP_LOCALE=en`, weshalb kein Testlauf es je bemerken konnte. Behoben: alle 149 Regeln übersetzt, dazu Anmeldung, Passwort-Zurücksetzen, Blätterlinks und lesbare Feldnamen (`product_group_id` → „Warengruppe"). `APP_FALLBACK_LOCALE=en` in der Vorlage. Neuer `UebersetzungTest` setzt die Sprache ausdrücklich auf `de` und vergleicht Schlüssel für Schlüssel gegen die englische Fassung |
| 20.08.2026 | Muster hinter den letzten drei Funden: **Alles, was nur im Betrieb anders eingestellt ist, prüft kein Testlauf.** Sprache, Zielverzeichnis, `.htaccess`, ausgelassene Ordner — jeder dieser Fehler kam erst bei der Abnahme von außen ans Licht. Die Abnahme ist deshalb kein Formalismus nach getaner Arbeit, sondern die einzige Stelle, an der diese Klasse von Fehlern überhaupt sichtbar wird |
| 20.08.2026 | Testbestand nach allen Nachbesserungen: **341 Tests, 1435 Zusicherungen, grün**, Pint sauber |
| 20.08.2026 | **Abnahme über HTTPS vollständig bestanden.** Startseite, Anmeldung, Registrierung, `/health`, Impressum, Datenschutz, Handbuch, Symbolbild und Manifest je 200; `/.env`, `/vendor/autoload.php` und `/storage/logs/laravel.log` je 404; HTTP → 301; fünf Schutzkopfzeilen; Impressum und Datenschutzerklärung ohne `[bitte eintragen]` |
| 20.08.2026 | SSH eingerichtet, S1 ausgewertet: PHP 8.4.24 in der Shell, kein Versionsproblem. Kein `composer`, aber `git`, `unzip`, `mysql`-Client |
| 20.08.2026 | **Fehler in der Notbremse gefunden, bevor er zuschlug.** Das Projekt liegt bei STRATO *innerhalb* des Dokumentenstamms (`htdocs/wocheneinkauf/`). Apache liest die `.htaccess` jedes übergeordneten Verzeichnisses mit — das `Require all denied` aus der Projektwurzel hätte sich bis `public/` vererbt und **jede Adresse mit 403 beantwortet**. Der Kommentar in der Datei behauptete, sie werde „nie gelesen"; das gilt nur, wenn das Projekt außerhalb des Dokumentenstamms liegt. Behoben durch `Require all granted` in `public/.htaccess`, beide Kommentare richtiggestellt, Testfall `test_die_notbremse_sperrt_nicht_die_anwendung_selbst` ergänzt |

### Außerhalb des Codes, vor der öffentlichen Freigabe

- **Auftragsverarbeitungsvertrag mit STRATO.** Die Datenschutzerklärung behauptet
  bereits, dass er besteht — er muss auch vorliegen.
- **Löschkonzept** mit festen Fristen.
- **Juristische Prüfung** der Rechtstexte. Sie sind sorgfältig, aber keine
  Rechtsberatung.
