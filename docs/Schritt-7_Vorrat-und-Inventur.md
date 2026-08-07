# Schritt 7 – Vorrat und Inventur

**Ziel laut Architekturkonzept (Abschnitt 6):** *Bestandsführung mit Inventurlauf.*
**Abgedeckte Use Cases:** E-01 … E-11 (E-12 bis E-14 sind KANN und bleiben offen).

---

## A · Was entstanden ist

| Migration | Tabellen |
|---|---|
| `2026_08_04_000100_create_pantry_tables` | `pantry_items`, `inventories`, `inventory_lines`, `pantry_movements` |

```
app/Modules/Pantry/
  Models/    PantryItem, Inventory, InventoryLine, PantryMovement
  Services/  PantryManager, InventoryRunner, ShortfallToList
```

Der Grundvorrat aus Schritt 4 (`products.pantry_target_amount`) bleibt, was er ist: die **Absicht**, immer etwas davon im Haus zu haben. `pantry_items` ist die **Wirklichkeit** dazu — was tatsächlich wo liegt, in welcher Menge und bis wann haltbar. Beides getrennt zu halten hat einen praktischen Grund: Derselbe Artikel liegt oft an zwei Orten (Nudeln in der Speisekammer *und* im Keller), das Soll gilt aber für den Haushalt insgesamt.

**Mandantentrennung** wie überall: `BelongsToFamily` auf allen vier Models, Verbund-Fremdschlüssel `(pantry_item_id, family_id)` bzw. `(inventory_id, family_id)` in der Datenbank (R-03), und IDs aus Formularen werden über den Global Scope nachgeladen statt ihnen zu vertrauen.

---

## B · Buchen statt Setzen

Jede Änderung am Bestand ist eine **Buchung** in `pantry_movements`, mit Grund (`purchase`, `consumption`, `waste`, `correction`, `inventory`) und Verweis auf ihren Auslöser. Das kostet eine Zeile pro Vorgang und bringt zwei Dinge, die den Preis wert sind: Man kann nachsehen, wohin die Mehl-Vorräte verschwunden sind, und eine Inventur kann später sagen, *was* seit dem letzten Zählen passiert ist.

Zwei Regeln stecken in `PantryManager::book()`:

- **Nie unter null.** Wer 2 kg im System hat und 3 kg verbraucht, hat danach 0 — nicht −1. Ein negativer Bestand ist keine Information, sondern ein Erfassungsfehler.
- **Gebucht wird, was passiert ist.** Wenn die Abbuchung an der Null abgeschnitten wird, steht in der Bewegung die tatsächliche Differenz (−2), nicht die gewünschte (−3). Sonst summieren sich die Bewegungen nicht mehr zum Bestand.

`setTo()` ist die Umkehrung für den Fall, dass jemand den *Stand* kennt und nicht die Änderung — es rechnet die Differenz aus und bucht sie. Die Inventur nutzt genau diesen Weg.

---

## C · Der Inventurlauf (E-03 … E-08)

Drei Zustände, zwei Ausgänge:

```
open ──count()──► open ──complete()──► completed
                    └───cancel()────► cancelled
```

**Beim Start** entstehen sofort alle Zeilen und frieren dabei Ist und Soll ein (`expected_qty`, `target_qty`). Würde man sie erst beim Zählen anlegen, wäre die Abweichung hinterher nicht mehr berechenbar — der Ist-Stand hätte sich ja inzwischen geändert. `difference` ist eine generierte Spalte: gezählt minus erwartet, von der Datenbank gerechnet, damit sie nicht auseinanderlaufen kann.

**Beim Zählen** darf man sich Zeit lassen und zu mehreren arbeiten; jede Zeile merkt sich, wer sie wann gezählt hat. Eine **leere Eingabe setzt zurück** auf „noch nicht gezählt" — wer sich vertippt hat, soll das zurücknehmen können, ohne raten zu müssen, ob dann eine Null gilt.

**Beim Abschluss** werden nur die gezählten Zeilen gebucht. Was niemand angesehen hat, bleibt unangetastet: Eine Inventur, die ungezählte Positionen auf null setzt, wäre gefährlicher als gar keine. **Beim Abbruch** passiert mit den Beständen nichts — das ist die Zusage, die es braucht, damit jemand überhaupt anfängt.

Nur **eine offene Inventur je Familie**, erzwungen über die generierte Spalte `offen_marker` samt UNIQUE — dasselbe Muster wie bei der aktiven Einkaufsliste.

---

## D · Fehlmengen auf die Liste (E-09)

Der eigentliche Zweck des ganzen Aufwands: Am Ende steht nicht ein Bericht, sondern eine Liste. Gerechnet wird Soll minus Ist; was darüber liegt, bleibt im Schrank.

Zwei Wege führen dahin — `fromPantry()` nimmt den laufenden Bestand, `fromInventory()` die gezählten Mengen eines abgeschlossenen Laufs. Beide gehen durch `ShoppingListManager::addOrMerge`, also fällt eine Fehlmenge mit einem schon von Hand eingetragenen Posten zusammen, statt danebenzustehen (C-17).

---

## E · Die Seiten

| Seite | Was man dort tut |
|---|---|
| `/vorrat` | Bestand ansehen, Position anlegen, buchen, Fehlmengen auf die Liste |
| `/vorrat/{id}/verlauf` | Bewegungen einer Position nachlesen (E-11) |
| `/vorrat/inventur/uebersicht` | Läufe starten und einsehen |
| `/vorrat/inventur/{id}` | Zählmaske (E-04) |
| `/vorrat/inventur/{id}` nach Abschluss | Bericht mit Abweichungen (E-08) |

Rechte wie beim Artikelkatalog: **jedes Mitglied** darf Vorrat pflegen und zählen. Vorratspflege ist Alltag, keine Struktur.

---

## F · Tests

`tests/Feature/Vorrat/VorratTest.php` — 17 Tests. Abgedeckt sind unter anderem: derselbe Artikel an zwei Orten, kein negativer Bestand, gebucht wird das tatsächlich Passierte, die zweite offene Inventur scheitert an der Datenbank, Abbruch lässt Bestände in Ruhe, ungezählte Zeilen bleiben unangetastet, Fehlmengen fallen mit vorhandenen Listeneinträgen zusammen, fremder Vorrat endet in 404.

---

## Abschluss-Check für Schritt 7

- [x] E-01 Vorratsposition anlegen und pflegen
- [x] E-02 Bestand buchen (Zugang, Verbrauch, Verwurf, Korrektur)
- [x] E-03 Inventurlauf starten (ganz, je Ort, Auswahl)
- [x] E-04 Zählen, auch verteilt über mehrere Personen
- [x] E-05 Mindestbestand und Meldebestand
- [x] E-06 Inventur abschließen und Bestände übernehmen
- [x] E-07 Inventur abbrechen
- [x] E-08 Abweichungsbericht
- [x] E-09 Fehlmengen auf die Einkaufsliste
- [x] E-10 Haltbarkeitsdatum und Ablaufwarnung
- [x] E-11 Bewegungshistorie je Position

**Offen (KANN):** E-12 Barcode-Erfassung, E-13 Standortverwaltung als eigene Stammdaten, E-14 Verbrauchsprognose.
