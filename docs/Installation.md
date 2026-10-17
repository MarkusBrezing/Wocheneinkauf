# Installation

Wocheneinkauf auf einem eigenen Server einrichten — anbieterneutral.

Wer auf **STRATO** oder einem vergleichbaren Webhosting-Paket ohne
Serverzugriff landet, findet in [Betrieb-Strato.md](Betrieb-Strato.md) die
Fassung für diesen Fall, inklusive der Wege ohne SSH.

---

## Was gebraucht wird

| | |
|---|---|
| **PHP** | 8.3 oder neuer |
| **MariaDB** | 10.6 oder neuer (oder MySQL 8.0+) |
| **Composer** | 2.x |
| **Webserver** | Apache oder nginx |
| **SMTP-Zugang** | **Pflicht**, siehe unten |

Kein Node, kein Redis, kein Warteschlangen-Dienst, kein Cron. Sitzungen,
Zwischenspeicher und Warteschlange laufen alle über die Datenbank — es gibt
keinen zweiten Prozess, der dauerhaft laufen müsste.

### PHP-Erweiterungen

Die von Laravel geforderten, plus die, die diese Anwendung tatsächlich
benutzt:

```
pdo_mysql  mbstring  openssl  tokenizer  xml  ctype  json  bcmath  fileinfo  curl  intl
```

Auf Debian und Ubuntu:

```bash
sudo apt install php8.3-{cli,fpm,mysql,mbstring,xml,bcmath,curl,intl,zip}
```

Prüfen lässt sich das hinterher mit `php -m`.

### Warum MariaDB und nicht SQLite

Zwei Dinge im Schema gibt es in SQLite nicht in dieser Form:

- **Generierte Spalten mit UNIQUE.** Dass es je Familie höchstens *eine*
  aktive Einkaufsliste geben kann, erzwingt eine `STORED`-Spalte zusammen mit
  einem eindeutigen Index — nicht Anwendungscode, der sich umgehen ließe.
- **Zusammengesetzte Fremdschlüssel** `(id, family_id)`. Sie machen es der
  Datenbank unmöglich, einen Datensatz einer fremden Familie zu verknüpfen.

Beides trägt die Mandantentrennung. Deshalb laufen auch die Tests gegen eine
echte MariaDB.

---

## Einrichten

### 1 · Code und Abhängigkeiten

```bash
git clone https://github.com/MarkusBrezing/Wocheneinkauf.git
cd Wocheneinkauf
git checkout v1.1.0
composer install --no-dev --optimize-autoloader
```

`git checkout` holt eine **Version** statt des Entwicklungsstands von `main`.
Die neueste steht oben in [`CHANGELOG.md`](../CHANGELOG.md); `git tag` listet
alle. Für den Betrieb immer eine Version — `main` kann Dinge enthalten, die
noch in keiner stehen.

Für eine Entwicklungsumgebung `composer install` ohne die beiden Schalter —
sonst fehlen die Testwerkzeuge.

### 2 · Datenbank

```sql
CREATE DATABASE wocheneinkauf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wocheneinkauf'@'localhost' IDENTIFIED BY 'ein-langes-passwort';
GRANT ALL PRIVILEGES ON wocheneinkauf.* TO 'wocheneinkauf'@'localhost';
FLUSH PRIVILEGES;
```

`utf8mb4` ist nicht optional: Artikelnamen enthalten Umlaute, Rezepte
gelegentlich Emoji.

### 3 · Konfiguration

```bash
cp .env.example .env
php artisan key:generate
```

Dann die `.env` ausfüllen. Die Werte, auf die es ankommt:

```ini
APP_URL=https://ihre-domain.de     # ohne Schrägstrich am Ende
APP_ENV=production
APP_DEBUG=false

APP_LOCALE=de
APP_FALLBACK_LOCALE=en             # NICHT de -- siehe Fallstricke

DB_HOST=127.0.0.1
DB_DATABASE=wocheneinkauf
DB_USERNAME=wocheneinkauf
DB_PASSWORD=…

MAIL_MAILER=smtp
MAIL_HOST=…
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=post@ihre-domain.de  # die VOLLSTÄNDIGE Adresse
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=post@ihre-domain.de

SESSION_SECURE_COOKIE=true         # nur mit HTTPS, siehe Fallstricke

IMPRESSUM_NAME=…                   # Pflicht bei öffentlichem Betrieb
IMPRESSUM_STRASSE=…
IMPRESSUM_ORT=…
IMPRESSUM_EMAIL=…
```

### 4 · Schema

```bash
php artisan migrate --force
```

Es laufen 24 Migrationen. `--force` ist nötig, weil Laravel in
`APP_ENV=production` sonst nachfragt.

### 5 · Dateirechte

Der Webserver muss in genau zwei Bäumen schreiben dürfen:

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

Fehlen diese Rechte, antwortet die Anwendung mit 500 — **und das
Fehlerprotokoll bleibt leer**, weil Laravel nicht einmal die Logdatei anlegen
kann. Das ist die irreführendste Fehlersituation der ganzen Einrichtung.

### 6 · Webserver

Das Web-Verzeichnis zeigt auf **`public/`**, niemals auf das
Projektwurzelverzeichnis. Darüber liegen `.env`, `vendor/` und `storage/`.

**Apache** — `mod_rewrite` aktiv, `AllowOverride All`:

```apache
<VirtualHost *:443>
    ServerName ihre-domain.de
    DocumentRoot /var/www/wocheneinkauf/public

    <Directory /var/www/wocheneinkauf/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Die mitgelieferte `public/.htaccess` erledigt den Rest.

**nginx:**

```nginx
server {
    listen 443 ssl;
    server_name ihre-domain.de;
    root /var/www/wocheneinkauf/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

### 7 · Zwischenspeicher

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Optional, aber auf schwacher Hardware deutlich spürbar.

> **Merken:** `config:cache` friert die `.env` ein. Jede spätere Änderung
> daran — Mailpasswort, Impressum — wirkt erst nach erneutem `config:cache`.
> Das ist die häufigste Ursache für „ich hab's doch geändert".

---

## Erster Start

1. `https://ihre-domain.de/register` aufrufen und ein Konto anlegen. Der erste
   Benutzer wird Inhaber einer neu angelegten Familie.
2. **Bestätigungsmail abwarten.** Ohne bestätigte Adresse geht es nicht
   weiter — auch für den Inhaber nicht.
3. Unter **Stammdaten › Artikel** das **Grundsortiment** laden: 145 Artikel,
   wie sie ein vierköpfiger Haushalt in Deutschland üblicherweise führt.
4. Unter **Stammdaten › Märkte** einen Markt anlegen und seine Regalbereiche
   in der Reihenfolge erfassen, in der man durch den Laden geht. Davon lebt
   die Sortierung der Einkaufsliste.
5. Weitere Mitglieder unter **Verwaltung › Familie** einladen.

Das [Anwenderhandbuch](handbuch/) erklärt den Rest und ist unter `/handbuch`
auch in der laufenden Anwendung abrufbar.

---

## Fallstricke

Vier Stellen, an denen die Einrichtung erfahrungsgemäß hängenbleibt. Alle vier
haben dasselbe Muster: Sie zeigen sich erst im Betrieb, nie in der
Entwicklung.

### `APP_FALLBACK_LOCALE` darf nicht `de` sein

Laravel liefert keine deutschen Sprachdateien mit; die dieser Anwendung liegen
in `lang/de`. Steht Haupt- **und** Rückfallsprache auf `de`, gibt es bei einer
fehlenden Übersetzung nichts, worauf zurückzufallen wäre — dann steht der
Schlüssel selbst auf dem Bildschirm, etwa `validation.min.string` auf der
Registrierungsseite.

Mit `en` kommt im schlimmsten Fall ein englischer Satz statt eines Schlüssels.

### Das `.htaccess`-Paar

Im Projektwurzelverzeichnis liegt eine `.htaccess` mit `Require all denied` —
eine Notbremse für den Fall, dass das Web-Verzeichnis versehentlich dorthin
zeigt, statt die `.env` auszuliefern.

Liegt das Projekt **innerhalb** des Dokumentenstamms, liest Apache diese Datei
auf dem Weg zu `public/index.php` mit, und das Verbot vererbt sich. Deshalb
steht in `public/.htaccess` ein `Require all granted`, das es für genau dieses
Verzeichnis wieder aufhebt.

**Die beiden Dateien gehören zusammen.** Wer eine anfasst, prüft die andere —
sonst antwortet die Anwendung auf jede Adresse mit 403.

### `docs/handbuch/` ist Laufzeitbestandteil

Die Markdown-Dateien dort sind die Quelle des Handbuchs unter `/handbuch`.
Fehlen sie, antwortet die Seite trotzdem mit 200 — nur mit **leerer Liste**,
und der Fehler zeigt sich erst einen Klick weiter als 404.

Wer beim Ausrollen `docs/` auslässt, weil es wie Dokumentation aussieht,
baut sich diesen Fehler ein. Prüfen:

```bash
curl -s https://ihre-domain.de/handbuch | grep -c 'href="/handbuch/'
```

Erwartet: 11 oder mehr.

### `SESSION_SECURE_COOKIE` und HTTPS

Steht der Schalter auf `true`, verlässt das Sitzungscookie den Browser nur über
HTTPS. Ohne aktives Zertifikat **scheitert die Anmeldung lautlos**: keine
Fehlermeldung, nur die Anmeldemaske wieder von vorn.

Richten Sie HTTPS zuerst ein. Der Schalter ist kein Hindernis, sondern das
Einzige, was eine Anmeldemaske vom offenen Netz trennt, solange keine
Verschlüsselung steht.

---

## Abnahme

```bash
curl -sI https://ihre-domain.de/                    # 200
curl -sI https://ihre-domain.de/.env                # 403 oder 404, niemals 200
curl -s  https://ihre-domain.de/health              # {"status":"ok","db":"up",…}
curl -s  https://ihre-domain.de/handbuch | grep -c 'href="/handbuch/'
curl -sI http://ihre-domain.de/                     # 301 auf https
```

`/health` ist ohne Anmeldung erreichbar und meldet absichtlich keine
Versionsnummern.

---

## Aktualisieren

Neue Fassungen erscheinen als **Versionen** — als Git-Tag (`v1.1.0`) und als
Release auf GitHub. Welche Version läuft, sagt `git describe --tags`.

### Zuerst lesen

[`CHANGELOG.md`](../CHANGELOG.md) nennt für jede Version unter *Beim
Aktualisieren*, ob eine Migration, eine neue Einstellung in der `.env` oder
neue Abhängigkeiten dazukommen. Gelesen wird das für **jede** Version zwischen
der installierten und der neuen — wer von 1.0.0 auf 1.3.0 springt, liest
1.1.0, 1.2.0 und 1.3.0.

Nennt eine davon eine Migration: vorher die Datenbank sichern.

```bash
mysqldump -u wocheneinkauf -p wocheneinkauf > sicherung-$(date +%F).sql
```

### Ablauf

```bash
php artisan down
git fetch --tags
git checkout v1.1.0
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

- **`down` und `up`:** Dazwischen zeigt die Anwendung eine Wartungsseite statt
  halbfertiger Zustände. Bricht ein Schritt ab, bleibt sie in der Wartung —
  nach der Korrektur `php artisan up` nicht vergessen.
- **Die Cache-Zeile ist nicht optional**, wenn die Caches aktiv sind: Eine neue
  Route existiert bei zwischengespeicherten Routen schlicht nicht, bis
  `route:cache` erneut gelaufen ist. Eine neue Einstellung aus der `.env`
  ebenso wenig, bis `config:cache` gelaufen ist.
- **Neue Einstellungen** aus dem CHANGELOG gehören vor der Cache-Zeile in die
  `.env`; `.env.example` zeigt sie mit Erläuterung.
- **Danach prüfen:** `/health` meldet `"offen":0` bei den Migrationen. Steht
  dort eine Zahl, ist `migrate` nicht gelaufen.

### Telefone und Service Worker

Nichts zu tun. Seiten kommen immer zuerst aus dem Netz; der Service Worker
bewahrt nur die letzte Einkaufsansicht für das Funkloch auf. Beim nächsten
Aufruf mit Verbindung zeigt auch das Telefon die neue Fassung.

### Zurück auf die vorige Version

Hat die neuere Version eine Migration mitgebracht, passt das Schema nicht mehr
zum älteren Code. Dann zuerst das Schema zurücknehmen, **solange noch der
neuere Code liegt** — nur der kennt den Rückweg seiner Migration:

```bash
php artisan down
php artisan migrate:rollback --force    # nur bei Migration, mit dem neueren Code
git checkout v1.0.0
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

`migrate:rollback` nimmt den letzten Lauf von `migrate` zurück, also genau das,
was das Update angelegt hat. Was seitdem in neuen Spalten gespeichert wurde,
geht dabei verloren. Sicherer ist die Sicherung von vorhin: einspielen, dann
`git checkout`.

### Ohne SSH

Die Version lokal auschecken, dort `composer install --no-dev
--optimize-autoloader`, und hochladen wie bei der Einrichtung — mit `vendor/`
und `docs/handbuch/` (siehe [Fallstricke](#docshandbuch-ist-laufzeitbestandteil)).
Wie eine Migration ohne Konsole auf den Server kommt, beschreibt
[Betrieb-Strato.md](Betrieb-Strato.md#11--aktualisieren).

---

## Tests

Die Tests brauchen eine eigene Datenbank:

```sql
CREATE DATABASE wocheneinkauf_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON wocheneinkauf_test.* TO 'wocheneinkauf'@'localhost';
```

Dann:

```bash
php artisan test
```

365 Tests, rund drei Minuten. Sie laufen gegen eine echte MariaDB, nicht gegen
SQLite — siehe oben.

**Immer nur einen Lauf gleichzeitig.** Zwei parallele Läufe teilen sich
dieselbe Testdatenbank und ziehen einander die Migrationen weg; das Ergebnis
sind Dutzende Fehler der Art „table doesn't exist", die wie echte Befunde
aussehen und keine sind.
