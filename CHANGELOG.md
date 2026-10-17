# Änderungen

Was sich von Version zu Version geändert hat — und vor allem, **was beim
Aktualisieren zu tun ist**. Wer eine Installation betreibt, liest vor jedem
Update den Abschnitt *Beim Aktualisieren* jeder Version zwischen der
installierten und der neuen. Der Ablauf selbst steht in
[`docs/Installation.md`](docs/Installation.md#aktualisieren).

Die Versionsnummer sagt, wie viel Aufmerksamkeit ein Update braucht:

| Stelle | Beispiel | Bedeutung |
|---|---|---|
| dritte | 1.1.0 → 1.1.1 | Fehlerbehebung. Keine Migration, keine neue Einstellung |
| zweite | 1.1.0 → 1.2.0 | Neue Funktionen. Kann Migrationen mitbringen — steht dann dabei |
| erste | 1.x → 2.0.0 | Etwas ändert sich so, dass Handarbeit nötig ist. Steht ausdrücklich dabei |

Jede Version ist ein Git-Tag (`v1.1.0`) und ein Release auf GitHub.

---

## 1.1.0 · 17.10.2026

### Beim Aktualisieren

- **Eine Migration:** `php artisan migrate --force`. Sie ergänzt die Spalte
  `is_offer` in `shopping_list_items`; vorhandene Einträge werden zu „kein
  Angebot".
- Keine neuen Abhängigkeiten, keine neue Einstellung in der `.env`.
- `docs/handbuch/` hat sich geändert. Wer per FTP hochlädt, nimmt den Ordner
  mit — er ist Laufzeitbestandteil, nicht Dokumentation.

### Neu

- **Artikeleingabe mit eigener Vorschlagsliste.** Die Liste öffnet sich schon
  beim Hineinklicken und sortiert nach Passung statt nach Alphabet. Sie findet
  ohne Akzente und Umlaute, in Ein- und Mehrzahl und mit einem Tippfehler.
  Jede Zeile zeigt die Warengruppe und „steht drauf", wenn der Artikel schon
  offen auf der Liste steht. Ein gewählter Vorschlag setzt die Warengruppe und
  springt zur Menge; die letzte Zeile, „… neu aufschreiben", macht sichtbar,
  wann ein Eintrag ohne Katalogbezug entsteht. Die Felder stehen jetzt in der
  Reihenfolge Artikel, Menge, Warengruppe.
- **Aktionsangebote markieren.** Ein Häkchen „Angebot" in der Eingabe; die
  Marke erscheint in der Liste, in der Einkaufsansicht auf dem Telefon und in
  der Druckansicht.

### Behoben

- **Wochenplan:** Ein Doppelklick auf „Vorschlag annehmen" brach mit dem
  MariaDB-Fehler 1020 ab („Record has changed since last read"). Betroffen
  sind Server mit MariaDB 11.6 oder neuer, die `innodb_snapshot_isolation`
  standardmäßig einschalten.

### Dokumentation

- Dieses Änderungsprotokoll, Versionsnummern und ein ausführlicher Abschnitt
  *Aktualisieren* in der Installationsanleitung.

---

## 1.0.0 · 15.10.2026

Die erste veröffentlichte Fassung, samt Installationsanleitung für den eigenen
Server. Wer vor dem 17.10.2026 installiert hat, steht auf diesem Stand oder
einem Zwischenstand auf dem Weg zu 1.1.0 und folgt beim Update den Hinweisen
zu 1.1.0. Eine Migration, die schon gelaufen ist, führt `migrate` kein zweites
Mal aus.
