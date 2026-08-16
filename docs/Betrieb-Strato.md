# Veröffentlichen auf einem STRATO-Server

Was zu tun ist, damit die Anwendung dort läuft — und was ohne diese Schritte **nicht** funktioniert.

---

## 0 · Die eine Abhängigkeit, die alles blockiert

`App\Core\Models\User` implementiert `MustVerifyEmail`. Ohne funktionierenden Mailversand kommt **niemand** über die Registrierung hinaus: Der Bestätigungslink wird nie zugestellt, und ohne Bestätigung ist keine Seite erreichbar.

Mail ist damit keine Zusatzfunktion, sondern Startbedingung. Drei Dinge hängen daran:

| Vorgang | Wann |
|---|---|
| Bestätigung der E-Mail-Adresse | bei jeder Registrierung |
| Passwort zurücksetzen | wenn jemand es vergisst |
| Einladung in die Familie | `InvitationController::store` |

Was **nicht** nötig ist, weil die Anwendung es nicht benutzt:

- **Kein Warteschlangen-Dienst.** Keine einzige Klasse implementiert `ShouldQueue`; Mails gehen im selben Aufruf raus. Ein dauerhaft laufender `queue:work` entfällt.
- **Kein Cron.** `routes/console.php` enthält keinen Zeitplan.
- **Kein Node und kein Frontend-Bau.** Keine View verwendet `@vite`; alles CSS steht in den Layouts. `npm run build` ist überflüssig, obwohl die `package.json` es aus dem Laravel-Gerüst noch anbietet.

Das vereinfacht den Betrieb erheblich: gebraucht werden PHP, eine Datenbank und ein SMTP-Zugang.

---

## 1 · Voraussetzungen prüfen

| Bedarf | Wert |
|---|---|
| PHP | **8.3 oder neuer** (`composer.json`: `^8.3`); entwickelt wurde auf 8.4 |
| Erweiterungen | pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo, **gd** (nur zum Neuerzeugen der App-Symbole; im Betrieb nicht nötig) |
| Datenbank | MariaDB 10.6+ oder MySQL 8. Die Anwendung nutzt **generierte Spalten** und Verbund-Fremdschlüssel — SQLite scheidet aus |
| Composer | zum Installieren der Abhängigkeiten |
| HTTPS | **Pflicht**, nicht Kür: Der Service Worker (Offline-Einkaufsansicht) wird von Browsern nur über HTTPS geladen |

> **Vor allem anderen klären:** Lässt Ihr STRATO-Paket zu, dass das Web-Verzeichnis der Domain auf einen **Unterordner** zeigt? Laravel muss auf `public/` zeigen; liegt der Rest darüber im Web, sind `.env`, Datenbankzugang und Quelltext öffentlich abrufbar. Bei einem V-Server ist das eine Zeile in der vhost-Konfiguration, bei manchen Webhosting-Tarifen eine Einstellung im Kundenmenü. Geht es dort nicht, siehe Abschnitt 7.

---

## 2 · Datenbank anlegen

Im STRATO-Kundenmenü eine MariaDB-Datenbank samt Benutzer anlegen und notieren: Hostname, Datenbankname, Benutzer, Passwort. Der Hostname ist bei STRATO in der Regel **nicht** `localhost`, sondern ein eigener Datenbankserver — der Wert aus dem Kundenmenü gilt.

---

## 3 · Code auf den Server

```bash
git clone git@github.com:MarkusBrezing/Wocheneinkauf.git wocheneinkauf
cd wocheneinkauf
composer install --no-dev --optimize-autoloader
```

Ohne SSH-Zugang: lokal `composer install --no-dev --optimize-autoloader` ausführen und den Ordner **einschließlich `vendor/`** hochladen. Nicht hochladen: `.env`, `node_modules/`, `storage/logs/`, `.git/`.

---

## 4 · `.env` einrichten

`.env.example` kopieren und ausfüllen:

```dotenv
APP_NAME=Wocheneinkauf
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ihre-domain.de

DB_CONNECTION=mysql
DB_HOST=<aus dem STRATO-Kundenmenü>
DB_PORT=3306
DB_DATABASE=<Datenbankname>
DB_USERNAME=<Benutzer>
DB_PASSWORD=<Passwort>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Dann den Anwendungsschlüssel erzeugen — ohne ihn lassen sich Sitzungen nicht verschlüsseln:

```bash
php artisan key:generate
```

> `APP_DEBUG=false` ist keine Feinheit. Mit `true` zeigt jede Ausnahme Datenbankzugang und Dateipfade im Browser.

---

## 5 · Mailversand einrichten

Der Kern der Frage. In die `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.strato.de
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=post@ihre-domain.de
MAIL_PASSWORD=<Passwort des Postfachs>
MAIL_FROM_ADDRESS=post@ihre-domain.de
MAIL_FROM_NAME="${APP_NAME}"
```

Alternativ Port **587** mit `MAIL_SCHEME=smtp` (STARTTLS). Welche Kombination Ihr Tarif anbietet, steht im STRATO-Kundenmenü unter den Zugangsdaten des Postfachs — **dort nachsehen statt raten**, die Angaben oben sind der übliche Fall.

Drei Fallstricke, an denen es in der Praxis scheitert:

1. **`MAIL_USERNAME` ist die vollständige Adresse**, nicht der Teil vor dem `@`.
2. **`MAIL_FROM_ADDRESS` muss ein Postfach sein, das bei STRATO wirklich existiert.** Ein Relay weist eine erfundene Absenderadresse ab — dann bricht die Registrierung mit einem SMTP-Fehler ab.
3. **Standardmäßig steht `MAIL_MAILER=log`.** Vergisst man diese Zeile, landen alle Mails in `storage/logs/laravel.log`, und niemand bemerkt es, bis sich jemand registrieren will.

**Probe vor dem ersten Nutzer:**

```bash
php artisan tinker --execute="Mail::raw('Probe', fn (\$m) => \$m->to('ihre@adresse.de')->subject('Wocheneinkauf'));"
```

Kommt nichts an, zuerst `storage/logs/laravel.log` lesen — der SMTP-Fehler steht dort im Klartext.

**Zustellbarkeit:** Wenn Domain und Postfach beide bei STRATO liegen, sind SPF und DKIM in der Regel bereits gesetzt. Liegt die Domain woanders, gehört ein SPF-Eintrag für STRATOs Mailserver in die DNS-Zone, sonst landen die Bestätigungsmails im Spam.

---

## 6 · Schema, Rechte, Zwischenspeicher

```bash
php artisan migrate --force

# Nur schreiben können muss der Webserver in diesen beiden Bäumen:
chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Die Zwischenspeicher sind kein Muss, aber sie sparen bei jeder Anfrage Arbeit. **Nach jeder Änderung an der `.env` muss `php artisan config:cache` erneut laufen** — sonst gilt weiter der alte Stand, und das ist der zweithäufigste Grund dafür, dass ein Mailversand „nicht funktioniert".

---

## 7 · Web-Verzeichnis auf `public/` zeigen lassen

**V-Server / eigener vhost:**

```apache
DocumentRoot /var/www/wocheneinkauf/public
<Directory /var/www/wocheneinkauf/public>
    AllowOverride All
    Require all granted
</Directory>
```

**Webhosting ohne freie vhost-Wahl:** Im Kundenmenü das Zielverzeichnis der Domain auf `.../wocheneinkauf/public` setzen. Geht das nicht, bleibt der Weg, den Inhalt von `public/` in das Web-Verzeichnis zu legen und den Rest der Anwendung **eine Ebene darüber** — außerhalb des Webs. In `index.php` sind dann die beiden `require`-Pfade anzupassen.

> **Nicht** die Anwendung komplett ins Web-Verzeichnis legen und mit `.htaccess` absichern. Ein Konfigurationsfehler legt dann die `.env` offen.

### Eine STRATO-Falle mit Apache

Die App-Symbole liegen unter `public/symbole/` und **nicht** unter `public/icons/`. Apache bringt in `mods-enabled/alias.conf` ein `Alias /icons/` auf sein eigenes Symbolverzeichnis mit; der Alias greift vor dem Dateisystem. Wer die Symbole umbenennt, verliert sie unsichtbar. (Das ist beim Bauen einmal passiert.)

---

## 8 · HTTPS

Zertifikat im STRATO-Kundenmenü aktivieren (Let's Encrypt ist bei den meisten Tarifen enthalten) und HTTP dauerhaft auf HTTPS umleiten. Ohne HTTPS:

- lädt der **Service Worker** nicht → keine Offline-Einkaufsliste,
- bietet der Browser **„Zum Startbildschirm hinzufügen"** nicht an,
- gehen Anmeldedaten im Klartext über die Leitung.

---

## 9 · Rechtstexte ausfüllen

Bei einem öffentlichen Angebot Pflicht. In die `.env`:

```dotenv
IMPRESSUM_NAME="Vorname Nachname"
IMPRESSUM_STRASSE="Straße Hausnummer"
IMPRESSUM_ORT="PLZ Ort"
IMPRESSUM_EMAIL=kontakt@ihre-domain.de
```

Fehlt eine Pflichtangabe, zeigt `/impressum` an ihrer Stelle ein gelb markiertes **`[bitte eintragen]`** — sichtbar und deshalb schwer zu übersehen. Danach `php artisan config:cache` erneut ausführen.

Außerhalb des Codes bleiben: **Auftragsverarbeitungsvertrag mit STRATO** (in der Datenschutzerklärung bereits behauptet — er muss auch vorliegen), ein **Löschkonzept** mit Fristen und eine **juristische Prüfung** der Texte.

---

## 10 · Abnahme

```bash
curl -s https://ihre-domain.de/health
```

Erwartet: `{"status":"ok","db":"up","migrationen":{"offen":0,…}}`. Ein `degraded` mit `offen > 0` heißt: Der Code ist neuer als das Schema — `php artisan migrate --force` fehlt noch.

Danach von Hand:

1. Registrieren → **kommt die Bestätigungsmail an?**
2. Bestätigen, anmelden, Grundsortiment laden (Stammdaten › Artikel).
3. Auf dem Telefon `/einkaufsliste/einkaufen` öffnen → bietet der Browser „Zum Startbildschirm hinzufügen"?
4. Flugmodus einschalten, Seite neu laden → bleibt die Liste lesbar?
5. `/impressum` → steht nirgends mehr `[bitte eintragen]`?

---

## 11 · Aktualisieren

```bash
cd wocheneinkauf
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Der Service Worker kann eine alte Fassung der Einkaufsansicht zwischengespeichert haben. Er räumt beim nächsten Aufruf selbst auf; wer es erzwingen will, erhöht `VERSION` in `public/sw.js`.

---

## Was ich nicht prüfen konnte

Die STRATO-Angaben oben (SMTP-Server, Ports, Einstellmöglichkeiten im Kundenmenü) sind der übliche Fall, aber tarifabhängig. **Verlässlich sind allein die Angaben in Ihrem Kundenmenü** — insbesondere Datenbank-Hostname, SMTP-Zugangsdaten und die Frage, ob sich das Zielverzeichnis der Domain frei setzen lässt. Die drei Punkte entscheiden über den Aufwand; alles andere ist gewöhnliche Laravel-Inbetriebnahme.
