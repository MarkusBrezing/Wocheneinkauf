# Schritt 3 – Stammdaten und Märkte

**Ziel laut Architekturkonzept (Abschnitt 6):** *Fremde Familien können eigene Märkte anlegen.*
**Abgedeckte Use Cases:** B-01 … B-09.
**Schwerpunkt auf Wunsch:** durchgängige Mandantentrennung.

---

## A · Die Mandantentrennung, auf zwei Ebenen

Das ist der Kern dieses Schritts. Beide Ebenen greifen unabhängig voneinander:

### Ebene 1 – Global Scope im Code

Jedes Model dieses Schritts trägt `BelongsToFamily`. Damit filtert jede Abfrage automatisch auf die aktuelle Familie, und beim Anlegen wird `family_id` selbst gesetzt:

```
ProductGroup   ProductGroupRule   Product   Market   MarketSection   StandingItem
```

Ein angenehmer Nebeneffekt: **Route-Model-Binding läuft durch den Scope.** In den Controllern steht deshalb keine einzige Besitzprüfung — ein Datensatz einer fremden Familie führt schlicht zu 404. Getestet in `MandantentrennungTest::test_zugriff_auf_fremde_datensaetze_endet_in_404`.

### Ebene 2 – Verbund-Fremdschlüssel in der Datenbank (R-03)

Die Pivot-Tabelle `market_section_groups` führt `family_id` und referenziert ihre Eltern über den Verbund `(id, family_id)` statt nur über `id`:

```sql
FOREIGN KEY (market_section_id, family_id) REFERENCES market_sections (id, family_id)
FOREIGN KEY (product_group_id,  family_id) REFERENCES product_groups  (id, family_id)
```

Dafür tragen `market_sections` und `product_groups` je einen zusätzlichen `UNIQUE (id, family_id)` als FK-Ziel.

Der Unterschied zur Ebene 1 ist wesentlich: Ein Global Scope schützt nur, solange jemand über Eloquent geht. Diese Constraints greifen auch bei `DB::table()->insert()` oder einem Eingriff per phpMyAdmin. Genau das prüft `test_datenbank_lehnt_familienuebergreifende_verknuepfung_ab` — der Test umgeht Eloquent absichtlich und erwartet eine `QueryException`.

### Ebene 1½ – Eingaben nachladen statt vertrauen

Beim Zuordnen von Warengruppen zu einem Regalbereich (B-04) werden die übermittelten IDs **nicht** direkt in die Pivot-Tabelle geschrieben, sondern erst durch den Global Scope nachgeladen:

```php
$ids = ProductGroup::whereIn('id', $request->input('gruppen', []))->pluck('id');
```

Ein manipuliertes Formular mit einer fremden ID würde sonst zwar am Fremdschlüssel scheitern — aber mit einem Datenbankfehler statt einer sauberen Antwort. Geprüft in `test_zuordnung_ignoriert_untergeschobene_fremde_warengruppen`.

---

## B · Was entstanden ist

### Fünf Migrationen, sieben Tabellen

| Migration | Tabellen |
|---|---|
| `2026_08_01_001000_create_product_groups_table` | `product_groups` |
| `2026_08_01_001100_create_markets_table` | `markets`, `market_sections` |
| `2026_08_01_001200_create_market_section_groups_table` | `market_section_groups` |
| `2026_08_01_001300_create_products_table` | `products`, `standing_items` |
| `2026_08_01_001400_create_product_group_rules_table` | `product_group_rules` |

`standing_items` bekommt entgegen Schema v1, aber gemäß **R-06**, Zeitstempel — ohne sie wäre der Delta-Sync ab Schritt 4 für Fixartikel blind.

> **Überholt (02.08.2026):** Die Tabelle gibt es nicht mehr. Fixartikel und Grundvorrat waren dasselbe und sind im Artikelkatalog zusammengeführt — siehe [Schritt 4, Abschnitt I](Schritt-4_Einkaufsliste.md).

### Modulschnitt nach Konzept 3

```
app/Modules/
  Catalog/    Models/ ProductGroup, Product, ProductGroupRule
              Services/ ProductCategorizer, ProductMerger, StarterDataInstaller
              Data/ StarterData
              Console/ InstallStarterDataCommand
  Markets/    Models/ Market, MarketSection
  ShoppingList/ Models/ StandingItem
```

`StandingItem` liegt bewusst unter `ShoppingList`, obwohl sein Use Case (B-08) unter den Stammdaten geführt wird — so ordnet Abschnitt 3 des Konzepts die Fixartikel zu. Der Assistent „Neue Woche", der sie verwendet, kommt in Schritt 4.

Damit die Factories den Modulen folgen, mappt der `AppServiceProvider` sie modulweise:

```
App\Core\Models\User            → Database\Factories\Core\UserFactory
App\Modules\Catalog\Models\Item → Database\Factories\Catalog\ItemFactory
```

Der Modulname bleibt im Pfad, damit gleichnamige Models verschiedener Module sich später nicht in die Quere kommen.

### Dublettenvermeidung (B-06)

`products.name_normalized` ist die Vergleichsform: klein, ohne Klammerzusatz, ohne doppelte Leerzeichen. „Tomaten (Bio)" und „  TOMATEN " sind damit derselbe Artikel. Das ersetzt das Zeichenketten-Gefummel aus `sameIngredient()` und `nameVariants()` der alten App.

**R-04 ist im Model verankert, nicht im Aufrufer.** Ein `deleting`-Hook hängt beim Soft Delete `~{id}` an `name_normalized`, ein `restored`-Hook nimmt es wieder weg. Läge diese Logik nur im `ProductMerger`, würde ein normal gelöschter Artikel seinen Namen dauerhaft blockieren — der UNIQUE-Key `(family_id, name_normalized)` greift auch bei gesetztem `deleted_at`.

Das Zusammenführen hängt alle Verweise um und übernimmt fehlende Angaben (Einheit, Warengruppe, Grundvorrats-Kennzeichen) vom aufgelösten Artikel. **Für Schritt 4 vorgemerkt:** `ProductMerger::verweiseUmhaengen()` ist die einzige Stelle, an der neue auf `products` zeigende Tabellen ergänzt werden müssen — Listeneinträge, Rezeptzutaten, Vorratspositionen.

### Auto-Kategorisierung (B-07)

Zwei Stufen, in dieser Reihenfolge:

1. **Der Artikelkatalog.** Kennt die Familie den Artikel bereits, gilt dessen Zuordnung — sie hat ihn irgendwann bewusst einsortiert. Wer „Milchreis" als Trockenware führt, bekommt ihn nicht durch die Regel `milch` ins Kühlregal geschoben.
2. **Die Stichwortregeln**, kleinste `priority` zuerst.

Regeln dürfen reguläre Ausdrücke sein. Da sie aus der Datenbank kommen und von Nutzern stammen, wird ein fehlerhafter Ausdruck protokolliert und übersprungen statt die Kategorisierung zu sprengen (`test_eine_fehlerhafte_regel_legt_die_kategorisierung_nicht_lahm`).

Ein Beispiel für die Priorität: `ei` steckt als Teilstring in Zwiebel, Weizen und Eis. Die Regel für Eier ist deshalb der Ausdruck `^eier?\b` mit `priority` 40 — sie läuft vor den allgemeinen Regeln.

### Startdatensatz (B-09)

Beim Anlegen einer Familie entstehen automatisch 15 Warengruppen, 68 Stichwortregeln und ein Standardmarkt „Supermarkt" mit 10 Regalbereichen in Laufreihenfolge, jeder bereits mit Warengruppen bestückt. Eingehängt im `FamilyProvisioner`, also auch beim Einlösen einer Einladung.

Der Vorgang ist **wiederholbar**: Vorhandenes bleibt unangetastet, und ein bereits gesetzter Standardmarkt wird nicht überschrieben. Für Familien aus Schritt 2:

```bash
php artisan catalog:install-starter-data
php artisan catalog:install-starter-data --family=01KYYQ7H9KSF901CQAHDY64EHX
```

### Standardmarkt (B-05)

`is_default` steht bewusst **nicht** in `#[Fillable]`. Es darf je Familie nur einen Standard geben, und das lässt sich nur sicherstellen, wenn das Setzen durch `Market::makeDefault()` läuft. Diese Entscheidung fiel, nachdem der erste Durchlauf einen Standardmarkt anlegte, der keiner war — `firstOrCreate()` hatte den nicht-fillable Wert stillschweigend verworfen.

Zwei Folgeregeln: Der erste Markt einer Familie wird automatisch Standard, und ein Standardmarkt lässt sich weder deaktivieren noch löschen, solange es Alternativen gibt.

---

## C · Rechtevergabe — eine Festlegung, die du prüfen solltest

Der Use-Case-Katalog sagt zu B-01 … B-09 nichts über Rollen. Ich habe eine Grenze gezogen und sie in `Controller::requireAdmin()` dokumentiert:

| Bereich | Wer darf ändern |
|---|---|
| Warengruppen, Märkte, Regalbereiche, deren Zuordnung | **Owner und Admin** |
| Artikelkatalog samt Grundvorrat | **jedes Mitglied** |
| Lesen | jedes Mitglied |

Die Begründung: Strukturelle Stammdaten gelten für alle gleichzeitig, ein Fehlgriff trifft die ganze Familie. Artikel anzulegen ist dagegen Alltag.

**Die Rolle `child` hat weiterhin keine Sonderrechte** — sie darf wie `member` Artikel pflegen. Punkt 4 aus Abschnitt 7 des Konzepts bleibt damit offen und wird spätestens in Schritt 4 relevant, wenn es ans Abhaken und Löschen von Listeneinträgen geht.

---

## D · Tests

78 Tests insgesamt (37 neue), 215 Assertions.

```bash
php artisan test
```

| Testdatei | Deckt ab |
|---|---|
| `Stammdaten/MandantentrennungTest` | Beide Trennungsebenen, 404 bei Fremdzugriff, Kaskadenlöschung |
| `Stammdaten/WarengruppenUndMaerkteTest` | B-01 … B-05, Rechtevergabe, Eindeutigkeit je Familie |
| `Stammdaten/ProdukteTest` | B-06, B-08, Normalform, R-04-Konvention, Zusammenführen |
| `Stammdaten/KategorisierungTest` | B-07, B-09, Wiederholbarkeit, fehlerhafte Regeln |

---

## E · Nachtrag (02.08.2026): die Stichwortregeln waren zu grob

Beim Befüllen der Demodaten in Schritt 4 fiel auf, dass „Apfelsaft" bei Obst und Gemüse landete und „Äpfel" von keiner Regel erfasst wurde. Ein Probelauf über 40 typische Artikelnamen förderte **zehn** Fehlgriffe zutage — darunter zwei, die schwerer wogen als der Anlass:

```
✗ Basmatireis   → TIEFKUEHL      ✗ Hackfleisch   → TIEFKUEHL
```

Ursache war die Regel `eis` (priority 60, Teilstring): Sie steckt in Basmatir**eis**, Hackfl**eis**ch und **Eis**bergsalat. Der ursprüngliche Kommentar hatte an „ei" in Zwiebel gedacht, aber nicht an „eis" in Reis.

### Was geändert wurde

Deutsche Zusammensetzungen sind der Grund, warum es hier ohne eine Handvoll regulärer Ausdrücke nicht geht:

| Regel | vorher | nachher |
|---|---|---|
| Getränke | je ein Teilstring `wasser`, `saft`, `bier`, `wein` | ein Ausdruck am **Wortende**, `priority` 30 |
| Getränke am Wortanfang | — | `kaffee`, `tee` als Auffangnetz, `priority` 110 |
| Apfel | `apfel` | `(äpfel\|apfel)` |
| Wurst | `wurst` | `(würst\|wurst)` |
| Eis | `eis` | `\beis(creme\|würfel)?\b` |
| Obst | — | `melone`, `traube` ergänzt |

Der Kern ist die Wortende-Regel: Im Deutschen steht das Getränk hinten — Apfel**saft**, Mineral**wasser**, Eis**tee**, Rot**wein**. **Wasser**melone, **Wein**trauben, **Bier**schinken und **Kaffee**sahne enden eben nicht darauf und bleiben, wo sie hingehören. `priority` 30 zieht die Regel vor alle anderen, sonst gewänne bei „Apfelsaft" das Obst und bei „Eistee" die Tiefkühltruhe.

Die neue Staffelung steht als Kommentar über `StarterData::rules()`.

### Verteilung an bestehende Familien

`catalog:install-starter-data` reicht dafür **nicht** — der Befehl ist bewusst additiv und soll es bleiben, sonst überschriebe jeder Nachlauf die selbst gepflegten Regeln einer Familie. Hier mussten Muster aber geändert und entfernt werden.

Das erledigt die Datenmigration `2026_08_02_000100_update_starter_keyword_rules`. Sie fasst nur an, was noch **unverändert so ausgeliefert** wurde — gleiche Warengruppe, gleiche `priority`, gleiche Ausdrucksart. Wer eine Regel selbst angepasst hat, behält sie.

Bereits gespeicherte Artikel behalten ihre Warengruppe: Eine einmal getroffene Zuordnung ist möglicherweise eine bewusste Entscheidung, und die Regeln entscheiden nur, wenn niemand etwas gesagt hat (siehe Abschnitt B). In den Demodaten wurden die fünf betroffenen Artikel von Hand nachgezogen.

Geprüft in `Stammdaten/KategorisierungTest` — der Datenprovider dort ist jetzt die Fallsammlung: 27 Beispiele, die alle zehn Fehlgriffe einschließen.

---

## Abschluss-Check für Schritt 3

- [ ] `php artisan test` → 78 Tests grün
- [ ] `/health` zeigt `"ausgefuehrt": 15`, `"offen": 0`
- [ ] Eine neue Registrierung bringt 15 Warengruppen und einen Markt mit 10 Bereichen mit
- [ ] Artikel „Kirschtomaten" ohne Auswahl anlegen → landet in *Obst und Gemüse*
- [ ] Artikel löschen und unter gleichem Namen neu anlegen funktioniert
- [ ] Ein Mitglied ohne Admin-Rolle bekommt bei Warengruppen 403, kann sie aber sehen

---

## Danach: Schritt 4

ShoppingList (C-01 … C-17) mit Versionierung und Delta-Sync. Laut Konzept der Punkt, ab dem sich der Aufwand rechtfertigt — davor ist die alte Funktionalität nur teurer nachgebaut.

Vorzumerken:
- `ProductMerger::verweiseUmhaengen()` um `shopping_list_items` erweitern.
- Der Assistent „Neue Woche" (C-10) verwendet den Grundvorrat aus diesem Schritt.
- Die Sortierung der Liste nach Markt (C-06) nutzt `market_sections.sort` und die Zuordnung aus B-04.
