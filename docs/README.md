# Dokumentation

Vier Ebenen, vier Leserkreise. Wer etwas sucht, fängt hier an.

| Ebene | Dokument | Für wen |
|---|---|---|
| **Werbung** | [Wocheneinkauf-Flyer.docx](Wocheneinkauf-Flyer.docx) · [als PDF](Wocheneinkauf-Flyer.pdf) | Wer die Anwendung noch nicht kennt. Eine Seite. |
| **Bedienung** | [handbuch/](handbuch/) — 11 Kapitel | Alle, die damit einkaufen. Steht auch in der Anwendung unter `/handbuch`. |
| **Technik** | [Wocheneinkauf-Technische-Dokumentation.md](Wocheneinkauf-Technische-Dokumentation.md) | Wer den Code weiterentwickelt. Mit UML-Diagrammen. |
| **Datenbank** | [Wocheneinkauf-Datenbank.md](Wocheneinkauf-Datenbank.md) | Wer Abfragen schreibt oder migriert. Alle 37 Tabellen. |

Dazu die **Schrittprotokolle** `Schritt-2` bis `Schritt-9`: Was in jedem Bauabschnitt entstanden ist und warum es so entschieden wurde. Sowie [Wocheneinkauf-Seiten-und-Daten.docx](Wocheneinkauf-Seiten-und-Daten.docx) — jede Seite mit ihren Benutzeraktionen und den Tabellen dahinter.

---

## Was sich von selbst erzeugt

Zwei Dokumente entstehen aus dem laufenden System und dürfen nicht von Hand bearbeitet werden:

```bash
php scripts/db-doku.php
```

Liest Struktur, Typen, Schlüssel und Indizes aus `information_schema` und mischt sie mit den Erläuterungen aus `scripts/db-doku-prosa.php`. Nach jeder Migration erneut laufen lassen — das Skript meldet auch, wenn eine neue Tabelle noch ohne Beschreibung ist.

```bash
node scripts/flyer.js
```

Erzeugt den Flyer neu. Die PDF-Fassung entsteht daraus mit
`soffice --headless --convert-to pdf docs/Wocheneinkauf-Flyer.docx`.

Das **Handbuch** hat nur eine Quelle: die Markdown-Dateien in `handbuch/`. Die Webseite rendert sie zur Laufzeit (`App\Core\Support\Handbuch`), Kapitelnummer und Überschrift liest sie aus der Datei selbst. Ein neues Kapitel ist deshalb eine neue Datei mit vorangestellter Nummer — mehr nicht.

---

## Die Diagramme

Die technische Dokumentation trägt ihre UML-Diagramme als Mermaid-Quelltext im Markdown. GitHub, GitLab und VS Code zeichnen sie unmittelbar; wer sie als Bild braucht, kopiert den Block nach <https://mermaid.live>. Enthalten sind Kontext- und Paketdiagramm, fünf Klassendiagramme, fünf Sequenzdiagramme, vier Zustandsdiagramme, das ER-Diagramm (in der Datenbankdokumentation) und ein Verteilungsdiagramm.
