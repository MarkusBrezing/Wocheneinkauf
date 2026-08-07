# Schritt 6 – Rezepte

**Ziel laut Architekturkonzept (Abschnitt 6):** *Rezeptdatenbank aus `data.js` migriert.*
**Abgedeckte Use Cases:** D-01 … D-08.
**Vorgezogen:** Der Schritt entstand vor Schritt 5, weil die Datei `data.js` für den Import noch nicht vorliegt — das Modul steht bereit, sobald sie da ist.

---

## A · Was entstanden ist

| Migration | Tabellen |
|---|---|
| `2026_08_02_000300_create_recipes_table` | `recipes`, `recipe_ingredients`, `tags`, `recipe_tag` |

```
app/Modules/Recipes/
  Models/    Recipe, RecipeIngredient, Tag
  Services/  RecipeToList
```

Die Struktur folgt `schema_v1.1.sql` und damit dem, was `data.js` heute schon führt: Zutaten mit Originaltext, Zubereitung als Markdown, Nährwerte als JSON, dazu `is_pantry`, `buy_label` und `merge_key` — die drei Kennzeichen, mit denen die alte App die Übernahme auf die Einkaufsliste steuert.

**Mandantentrennung** wie überall: `BelongsToFamily` auf allen drei Models, Route-Model-Binding über `public_id` durch den Global Scope, und Verbund-Fremdschlüssel `(recipe_id, family_id)` bzw. `(tag_id, family_id)` in der Datenbank (R-03). Der Slug ist je Familie eindeutig; bei Namensgleichheit hängt das Model `-2` an, statt den UNIQUE-Fehler bis in den Controller durchschlagen zu lassen.

---

## B · Die Übernahme auf die Einkaufsliste

Das ist der Teil, an dem sich entscheidet, ob ein Rezeptmodul im Alltag taugt (`RecipeToList`). Drei Dinge passieren, und jedes war in der alten App eine eigene Fehlerquelle:

**1. Skalieren (D-04).** Der Faktor ist *gewünschte Portionen ÷ Portionen des Rezepts*. Er gilt nur für Zutaten mit Menge — „etwas Petersilie" bleibt „etwas Petersilie", statt zu „1,5 etwas" zu werden.

**2. Auswählen (D-05).** Zutaten mit `is_pantry` sind in der Maske **abgewählt**: Was im Schrank steht, muss nicht mit. Wer sie doch braucht, hakt sie an — die ausdrückliche Auswahl schlägt die Voreinstellung. Sobald das Vorratsmodul (Schritt 7) steht, kann diese Vorauswahl den tatsächlichen Bestand berücksichtigen statt nur das Kennzeichen; die Anschlussstelle ist `RecipeToList::gewaehlt()`.

**3. Zusammenfassen (D-06).** Zwei Zutaten mit demselben `merge_key` — oder derselben Normalform des Einkaufsnamens — werden zu einem Listeneintrag, Mengen addiert. Das gilt innerhalb eines Rezepts und über mehrere hinweg: Suppe mit 2 Zwiebeln und Curry mit 1 Zwiebel ergeben **einen** Eintrag über 3 Zwiebeln, weil die Übernahme durch `ShoppingListManager::addOrMerge` läuft (C-17) und nicht an ihm vorbei.

`buy_label` bestimmt dabei, was auf der Liste steht: Die Zutat heißt „Olivenöl", auf der Liste steht „Olivenöl, gutes fürs Dressing".

Addiert wird nur bei gleicher Einheit — dieselbe Zurückhaltung wie auf der Liste selbst. Sonst bleibt die erste Angabe stehen und die zweite wandert in die Notiz.

---

## C · Suchen und Filtern (D-01)

Die Suche geht über Name, Anmerkungen **und Zutaten**. Der Grund ist der häufigere Anlass: Man sucht ein Rezept öfter über das, was weg muss („noch Lauch im Kühlschrank"), als über seinen Namen.

Mehrere Tags werden als **UND** verstanden — „vegetarisch" *und* „schnell" grenzt ein, statt die Liste aufzublähen. Tags tragen einen Typ (Protein, Küche, Ernährung, Gang, Saison), der die Gruppierung im Filter bestimmt und später die Regeln des Wochenplan-Generators füttert: „kein Rezept mit demselben Protein-Tag an zwei Tagen hintereinander" (F-06).

Neue Schlagworte entstehen direkt am Rezept, ohne Umweg über eine eigene Verwaltung — mit `firstOrCreate`, damit dieselbe Bezeichnung nicht doppelt angelegt wird.

---

## D · Rechte

Wie beim Artikelkatalog darf **jedes Mitglied** Rezepte pflegen. Ein Rezept ordnet nichts für andere an — anders als Warengruppen oder Regalbereiche, die die Sortierung für alle festlegen. Damit bleibt die Grenze aus Schritt 3 unverändert: Strukturdaten sind Owner und Admin vorbehalten, Alltagsdaten nicht.

---

## E · Tests

210 Tests insgesamt (17 neue für dieses Modul), 822 Assertions.

```bash
php artisan test tests/Feature/Rezepte
```

| Prüft | Fälle |
|---|---|
| Anlegen, Zutaten, Slug-Eindeutigkeit | D-03 |
| Katalogverknüpfung beim Erfassen | D-08 |
| Skalierung, unskalierte Zutaten ohne Menge | D-04 |
| Vorratszutaten, ausdrückliche Auswahl | D-05 |
| `merge_key`, `buy_label`, zwei Rezepte auf eine Liste | D-06 |
| Suche über Zutaten, Tag-Filter, Favoriten | D-01, D-07 |
| Fremdzugriff (404) und Verbund-Fremdschlüssel | Mandantentrennung |

---

## Abschluss-Check für Schritt 6

- [ ] `php artisan test` → 210 Tests grün
- [ ] `/health` zeigt `"ausgefuehrt": 20`, `"offen": 0`
- [ ] Rezept mit vier Portionen anlegen, auf acht umrechnen → alle Mengen verdoppelt
- [ ] Zutat als „Vorrat" markieren → sie ist bei der Übernahme abgewählt
- [ ] Zwei Rezepte mit Zwiebeln übernehmen → ein Listeneintrag
- [ ] Rezept eines fremden Mandanten aufrufen → 404

---

## Was noch fehlt

- **D-09 (Import aus URL)** und **D-10 (Duplizieren)** sind als KANN eingestuft und nicht gebaut.
- **Der Datenimport aus `data.js`** — Schritt 5 der Roadmap. Die Datei liegt auf diesem Rechner nicht vor; sobald sie da ist, sind die Zielstrukturen vorhanden: `recipes` samt Zutaten, Tags und der Verknüpfung in den Artikelkatalog.
- **Die Zubereitung wird als Text ausgegeben**, nicht als gerendertes Markdown. Ein Renderer ist bewusst noch nicht eingebaut: Er bringt eine Abhängigkeit und die Frage nach der Ausgabesicherheit mit, und beides lohnt erst, wenn die importierten Rezepte zeigen, welche Auszeichnungen tatsächlich vorkommen.
