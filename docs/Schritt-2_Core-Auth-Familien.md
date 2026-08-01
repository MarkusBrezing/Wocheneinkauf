# Schritt 2 – Core, Auth, Familien und Einladungen

**Ziel laut Architekturkonzept (Abschnitt 6):** *Anmeldung funktioniert, Mandant existiert.*
**Abgedeckte Use Cases:** A-01 … A-15 sowie G-01 (Health-Check) und die Grundlage für G-06 (Aktivitätsprotokoll).
**Stack:** Laravel 13.23 auf PHP 8.4, MariaDB 10.11 (`192.168.178.95`), Auth über Laravel Fortify 1.37 (headless).

---

## A · Was entstanden ist

### Datenbank

Zehn Migrationen bilden Abschnitt 1 des Schemas v1.1 ab:

| Migration | Tabellen |
|---|---|
| `0001_01_01_000000_create_families_table` | `families` |
| `0001_01_01_000001_create_users_table` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000002_create_cache_table` | `cache`, `cache_locks` |
| `0001_01_01_000003_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| `2026_08_01_000100_create_family_user_table` | `family_user` |
| `2026_08_01_000200_create_invitations_table` | `invitations` |
| `2026_08_01_000300_create_login_attempts_table` | `login_attempts` |
| `2026_08_01_000400_create_family_settings_table` | `family_settings` |
| `2026_08_01_000500_create_activity_log_table` | `activity_log` |
| `2026_08_01_000600_add_current_family_foreign_key_to_users_table` | FK `users.current_family_id` |

Die letzte Migration löst **R-09**: `users.current_family_id → families` und `families ← family_user ← users` bilden einen Zyklus. Stünde der Fremdschlüssel schon in der `users`-Migration, scheiterte `migrate:fresh`, weil `families` zu diesem Zeitpunkt noch nicht existiert.

**R-05** ist umgesetzt: `public_id` und `token_hash` liegen in `ascii`/`ascii_bin` statt utf8mb4 — ein Viertel der Indexgröße.

### Zwei bewusste Abweichungen vom Schema v1.1

Beide entstehen dort, wo **das Framework** schreibt und der Spaltentyp des Schemas nicht dazu passt:

| Schema v1.1 | Umgesetzt | Grund |
|---|---|---|
| `sessions.ip_address VARBINARY(16)` | `VARCHAR(45)` | Laravels Session-Handler schreibt die IP als Zeichenkette. Eine IPv6-Adresse in Textform braucht bis zu 45 Zeichen und würde in 16 Byte abgeschnitten. |
| `password_reset_tokens.token_hash CHAR(64)` (SHA-256) | `token VARCHAR(255)` (bcrypt) | `DatabaseTokenRepository` verwendet fest verdrahtet die Spalte `token` und hasht mit bcrypt. Die Schutzabsicht — nie ein Klartext-Token in der Datenbank — bleibt erfüllt. |

Bei `invitations.token_hash` und `login_attempts.ip_address` schreiben wir selbst; dort gilt das Schema unverändert (SHA-256 bzw. `VARBINARY(16)` über `inet_pton`).

### Mandantentrennung

Das Herzstück aus Konzept 2.1, verteilt auf vier Klassen:

```
app/Core/Support/FamilyContext.php   Für welche Familie arbeitet dieser Request?
app/Core/Scopes/FamilyScope.php      Global Scope: WHERE family_id = ?
app/Core/Concerns/BelongsToFamily.php Trait, den jedes Fachmodel ab Schritt 3 nutzt
app/Core/Concerns/HasPublicId.php    ULID in public_id, damit IDs nicht zählbar sind
```

Eine Entscheidung, die über das Konzept hinausgeht: **Ohne Familienkontext liefert der Scope keine Zeilen, nicht alle.** Der Entwurf im Konzept liest `auth()->user()->current_family_id` direkt — in Konsolenbefehlen, Queue-Jobs und beim Einlösen einer Einladung gibt es aber keinen angemeldeten Nutzer. „Fail closed" macht daraus im schlimmsten Fall eine leere Liste statt eines Datenlecks. Wer absichtlich mandantenübergreifend arbeitet, muss das sichtbar machen:

```php
Model::withoutGlobalScope(FamilyScope::class)->get();
app(FamilyContext::class)->runFor($familyId, fn () => ...);
```

`Invitation` trägt den Trait bewusst **nicht**: Wer eine Einladung öffnet, gehört noch zu keiner Familie — der Scope würde den Datensatz verstecken. Der Zugriff läuft dort über `$family->invitations()` oder über den geheimen Token.

### Authentifizierung

Fortify liefert Registrierung, Login, Passwort-Reset, E-Mail-Bestätigung und Rate-Limiting; die Oberfläche sind schlanke Blade-Formulare ohne Build-Schritt. **Zwei-Faktor-Authentisierung und Passkeys sind abgeschaltet** und ihre Migrationen entfernt — der Use-Case-Katalog sieht sie nicht vor, und ihre Spalten stehen nicht im Schema v1.1.

`CreateNewUser` erledigt A-01 und A-07 in einem: ohne Einladung entsteht mit dem Konto eine eigene Familie (Nutzer wird Owner), mit gültiger Einladung tritt er stattdessen der einladenden Familie bei.

### Brute-Force-Schutz (A-14) — zwei Ebenen

1. **Fortifys Zähler im Cache:** fünf Versuche je Minute und E-Mail/IP-Kombination.
2. **Sperre aus `login_attempts`:** zehn Fehlversuche in 15 Minuten sperren für 15 Minuten. Überlebt Neustart und `cache:clear`.

Die zweite Ebene läuft **nicht** über den Rate Limiter, obwohl das naheliegt. Ein `Limit` mit null erlaubten Versuchen blockiert nämlich nicht: `RateLimiter::tooManyAttempts()` setzt den Zähler zurück, solange kein Timer-Key im Cache liegt — und genau der fehlt beim ersten Versuch nach einem Neustart. Die Prüfung hängt deshalb als eigener Schritt (`EnsureAccountIsNotLockedOut`) in Fortifys Anmeldekette, wo sie außerdem eine verständliche Fehlermeldung erzeugt statt eines nackten HTTP 429.

Eine erfolgreiche Anmeldung im selben Zeitfenster hebt die Sperre auf — sonst könnte ein Fremder ein Konto aussperren, indem er es beschießt.

### DSGVO (A-11, A-12)

- **Auskunft (Art. 15):** `/konto/auskunft` liefert Konto, Familien und Aktivitäten als JSON-Download.
- **Kontolöschung (Art. 17):** Soft Delete plus Anonymisierung der E-Mail zu `deleted-{id}@invalid`. Das ist zugleich die Lösung für **R-04**: `users.email` ist UNIQUE, ein soft-gelöschter Datensatz würde die Adresse sonst dauerhaft blockieren.
- **Familienlöschung:** endgültig (`forceDelete`), die Fremdschlüssel räumen per CASCADE ab. Verlangt Passwort *und* den Familiennamen als Bestätigung.
- Ein letzter Owner kann nicht gehen und eine Familie mit anderen Mitgliedern führerlos zurücklassen; ist er das letzte Mitglied, verschwindet die Familie mit ihm.

### Aufbewahrung (R-07)

`login_attempts` wird beim Schreiben lazy aufgeräumt (90 Tage, höchstens einmal pro Stunde). Das hält die „kein Cronjob nötig"-Prämisse aus Abschnitt 1.1 ein.

---

## B · Tests

41 Tests, 119 Assertions. Sie laufen gegen **MariaDB**, nicht gegen SQLite:

```bash
php artisan test
```

Zwei Gründe: Auf dem System ist kein `pdo_sqlite` installiert (nur `pdo_mysql`), und das Schema nutzt MariaDB-Eigenheiten (ENUM, `VARBINARY`, generierte Spalten). Das entspricht Abschnitt 1.2 des Konzepts — die Entwicklungsumgebung soll der Produktion ähneln.

Dafür existiert eine eigene Datenbank `wocheneinkauf_test` auf `192.168.178.95`, auf die der Benutzer `wocheneinkauf` Rechte hat. `phpunit.xml` zeigt darauf.

| Testdatei | Deckt ab |
|---|---|
| `RegistrationTest` | A-01: Familienanlage, Owner-Rolle, ULID-Vergabe |
| `LoginThrottleTest` | A-14: Protokollierung, Sperre, Aufhebung, Lazy-Cleanup |
| `InvitationTest` | A-06/A-07/A-09: Versand, Token-Hashing, Beitritt, Widerruf, Ablauf, Kontingent, fremde Einladung |
| `FamilyManagementTest` | A-08/A-10/A-13: Einstellungen, Rollen, Entfernen, Owner-Schutz, Familienwechsel |
| `TenancyTest` | Global Scope: Isolation, Fail-closed, automatische `family_id` |
| `AccountPrivacyTest` | A-11/A-12: Auskunft, Anonymisierung, Löschregeln |
| `HealthCheckTest` | G-01: DB-Status und Migrationsstand |

---

## C · Health-Check (G-01) erweitert

Der Endpunkt meldet jetzt zusätzlich den Migrationsstand — wie in Schritt 1 vorgemerkt:

```json
{
  "status": "ok",
  "db": "up",
  "migrationen": { "ausgefuehrt": 10, "offen": 0, "letzte": "2026_08_01_000600_..." },
  "app": "Wocheneinkauf",
  "laravel": "13.23.0",
  "php": "8.4.24"
}
```

Offene Migrationen ergeben `degraded` und HTTP 503: der Code ist dann neuer als das Schema — ein Deployment, das nur halb durchgelaufen ist.

Nebenbei behoben: In `routes/web.php` war die `/health`-Route aus Schritt 1 **zweimal identisch** definiert; die zweite überschrieb die erste wirkungslos.

---

## D · Umgebung nachziehen

```bash
cd /var/www/wocheneinkauf
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

In der `.env` gegenüber Schritt 1 geändert:

```
APP_NAME=Wocheneinkauf
SESSION_DRIVER=database     # war "file", solange keine DB angebunden war
DB_CONNECTION=mysql
DB_HOST=192.168.178.95
DB_DATABASE=wocheneinkauf
```

---

## Abschluss-Check für Schritt 2

- [ ] `php artisan test` → 41 Tests grün
- [ ] `/health` liefert `"status":"ok"` mit `"offen":0`
- [ ] Registrierung legt Familie an, Nutzer ist Owner
- [ ] Bestätigungsmail kommt an (bei `MAIL_MAILER=log`: in `storage/logs/laravel.log`)
- [ ] Einladungslink funktioniert, Datenbank enthält nur den Hash
- [ ] Zehn Fehlversuche sperren das Konto für 15 Minuten
- [ ] `/konto/auskunft` liefert die JSON-Datei

---

## Noch offen – vor der öffentlichen Freigabe

1. **E-Mail-Versand.** `MAIL_MAILER=log` reicht für die Entwicklung. Registrierungsbestätigung, Passwort-Reset und Einladungen brauchen produktiv zuverlässige Zustellung samt SPF/DKIM (Punkt 2 aus Abschnitt 7 des Konzepts, weiterhin offen).
2. **Rollenmodell `child`.** Die Rolle existiert und lässt sich vergeben, hat aber noch **keine eingeschränkten Rechte** — heute darf ein `child` alles außer verwalten. Punkt 4 aus Abschnitt 7 ist damit weiterhin zu entscheiden, spätestens wenn Schritt 4 die Einkaufsliste bringt.
3. **STRATO-Tarif: SSH und Cronjobs.** Unverändert der wichtigste offene Punkt für die Deployment-Kette.
4. **Rechtliches.** Datenschutzerklärung, AV-Vertrag und Löschkonzept fehlen. Die Löschfunktionen stehen technisch, die Dokumentation dazu nicht.
5. **Zwei-Faktor-Authentisierung.** Bewusst nicht gebaut. Falls sie doch gewünscht ist, ist jetzt der günstige Zeitpunkt — Fortify bringt sie mit, es fehlen nur Feature-Flag und Migration.

---

## Danach: Schritt 3

Catalog + Markets (B-01 … B-09) inklusive Seeder für den Startdatensatz. Ab dort tragen die Fachmodelle den `BelongsToFamily`-Trait, der in diesem Schritt entstanden ist.
