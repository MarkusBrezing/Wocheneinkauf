# Schritt 9 – PWA und Rechtstexte

**Ziel laut Architekturkonzept (Abschnitt 6):** *PWA, Offline-Cache, lokale Fonts, Impressum/AGB/Datenschutz — öffentliche Freigabe.*

---

## A · Zum Startbildschirm hinzufügen

| Datei / Route | Zweck |
|---|---|
| `GET /manifest.webmanifest` (`ManifestController`) | Web-App-Manifest |
| `public/sw.js` | Service Worker |
| `public/offline.html` | Ersatzseite ohne Netz |
| `public/symbole/*.png` | Symbole, 192 und 512, je einmal normal und maskierbar |

Das Manifest ist eine **Route und keine Datei**: So steht der App-Name an einer Stelle, und der Medientyp `application/manifest+json` stimmt unabhängig davon, was der Webserver über die Endung `.webmanifest` denkt.

`start_url` ist `/einkaufsliste/einkaufen` und nicht das Dashboard. Wer das Symbol auf dem Telefon antippt, steht meistens im Laden — nicht am Schreibtisch. Dazu drei Verknüpfungen im Kontextmenü des Symbols: Einkaufen, Liste bearbeiten, Wochenplan.

Die Symbole entstehen aus GD-Primitiven (Korb in Creme auf Moosgrün) und liegen in zwei Ausführungen vor: `any` randlos und `maskable` mit Sicherheitszone, damit Android sie beschneiden kann, ohne den Henkel abzuschneiden.

---

## B · Der Offline-Cache

Der Zweck ist eng gefasst und soll es bleiben: Im Supermarkt ist das Netz schlecht, und wer schon vor dem Regal steht, will wenigstens **sehen**, was auf der Liste steht.

| Anfrage | Verhalten |
|---|---|
| Seitenaufrufe (`mode: navigate`) | erst Netz, dann Speicher, sonst `offline.html` |
| `/einkaufsliste/einkaufen` | wird zusätzlich abgelegt — aber nur bei `200` und ohne Weiterleitung |
| Symbole, Manifest | erst Speicher, dann Netz |
| Alles andere, inklusive POST | unverändert ins Netz |

**Erst Netz, dann Speicher** und nicht umgekehrt: Andersherum wäre schneller, aber eine Einkaufsliste von gestern ist schlimmer als eine, die eine Sekunde braucht.

**Keine Weiterleitung im Speicher.** Wäre die Anmeldeseite einmal unter der Adresse der Einkaufsliste abgelegt, führte jeder weitere Aufruf offline dorthin — eine Falle, aus der man ohne Netz nicht mehr herauskäme.

**Abhaken braucht Empfang.** Ein Warteschlangen-Verfahren für Offline-Änderungen gehört zum Sync-Frontend (C-07, C-08) und nicht in den Service Worker; die Endpunkte dafür stehen seit Schritt 4 bereit.

**Beim Abmelden fliegt alles raus.** Das Abmelde-Formular schickt dem Service Worker eine Nachricht, die sämtliche Caches löscht. Ohne das bliebe die Einkaufsliste einer Familie auf einem Gerät liegen, das anschließend jemand anderem gehört.

Der Service Worker wird erst **nach** `load` angemeldet — im Supermarkt zählt, wie schnell die Liste erscheint, nicht wie schnell der Cache bereitsteht.

---

## C · Schriften

Es gibt nichts einzubinden: Die Anwendung nutzt seit Schritt 2 ausschließlich Systemschriften (`system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`). Damit entfällt sowohl die Verbindung zu Google Fonts als auch der Punkt in der Datenschutzerklärung, den sie nötig machen würde. Der Roadmap-Punkt „lokale Fonts" ist auf diesem Weg erledigt.

---

## D · Rechtstexte

| Seite | Route |
|---|---|
| Impressum | `/impressum` |
| Datenschutzerklärung | `/datenschutz` |
| Nutzungsbedingungen | `/nutzungsbedingungen` |

Alle drei sind **ohne Anmeldung** erreichbar — ein Impressum hinter einem Login ist keines. Sie verwenden ein eigenes, schmales Layout ohne Navigation; verlinkt sind sie aus der Fußzeile jeder Seite.

**Angaben in der Konfiguration, Texte in den Views** (`config/rechtliches.php`, gespeist aus der `.env`). Die Texte sind für alle gleich, die Angaben nicht — wer die App selbst betreibt, trägt Name, Anschrift und E-Mail in die `.env` ein, statt Blade-Dateien zu ändern.

Fehlt eine Pflichtangabe, steht an ihrer Stelle ein sichtbar markiertes **`[bitte eintragen]`** (Blade-Direktive `@angabe`). Die stille leere Zeile wäre die schlechteste Möglichkeit: Die Seite sähe fertig aus, wäre es aber nicht — und ein unvollständiges Impressum ist abmahnfähig.

Die Datenschutzerklärung beschreibt, was tatsächlich passiert, und ist an drei Stellen enger als die üblichen Vorlagen, weil die Anwendung enger ist:

- **kein Tracking, keine Analyse, keine externen Inhalte** — deshalb auch kein Einwilligungsbanner,
- **zwei technisch notwendige Cookies** (Sitzung, CSRF-Token),
- **Speicherung im Browser** für die Einkaufsansicht, gelöscht beim Abmelden.

Auskunft und Löschung sind keine Absichtserklärung, sondern verlinkt: Unter *Konto* gibt es seit Schritt 2 den vollständigen Datenexport (A-11) und die Kontolöschung (A-12).

---

## E · Was vor einer öffentlichen Freigabe noch fehlt

Der Schritt macht die Anwendung technisch freigabefähig, nicht rechtlich fertig. Offen bleiben die Punkte, die niemand im Code erledigen kann (Abschnitt 8 des Konzepts):

- **Auftragsverarbeitungsvertrag** mit dem Hoster abschließen (in der Erklärung bereits genannt, muss aber tatsächlich vorliegen),
- **Löschkonzept** mit festen Fristen, das über die Papierkorb-Logik hinausgeht,
- die Angaben in der `.env` **ausfüllen** — solange sie fehlen, zeigt das Impressum sichtbare Lücken,
- Prüfung durch eine Person mit juristischer Ausbildung; die Texte hier sind sorgfältig, aber keine Rechtsberatung.

---

## Abschluss-Check für Schritt 9

- [x] Web-App-Manifest mit Symbolen (normal und maskierbar)
- [x] Service Worker mit Offline-Cache für die Einkaufsansicht
- [x] Ersatzseite ohne Netz
- [x] Zum Startbildschirm hinzufügen samt Verknüpfungen
- [x] Cache-Löschung beim Abmelden
- [x] Schriften lokal beziehungsweise vom System
- [x] Impressum, Datenschutzerklärung, Nutzungsbedingungen
- [x] Fußzeile mit Verweisen auf allen Seiten
- [ ] AV-Vertrag, Löschkonzept, juristische Prüfung (außerhalb des Codes)
