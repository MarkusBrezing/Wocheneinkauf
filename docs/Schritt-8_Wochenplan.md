# Schritt 8 – Wochenplan

**Ziel laut Architekturkonzept (Abschnitt 6):** *Anforderung 5 erfüllt — der Wochenplan mit Vorschlagsgenerator.*
**Abgedeckte Use Cases:** F-01 … F-15 (vollständig).

---

## A · Was entstanden ist

| Migration | Tabellen |
|---|---|
| `2026_08_04_000200_create_meal_plan_tables` | `meal_plans`, `meal_plan_slots`, `meal_plan_suggestions`, dazu `recipes.exclude_from_suggestions` |

```
app/Modules/MealPlan/
  Models/    MealPlan, MealPlanSlot, MealPlanSuggestion
  Services/  MealPlanManager, SuggestionEngine
```

**Ein Plan je Familie und Kalenderwoche**, erzwungen über UNIQUE `(family_id, week_start)`. Welcher Wochentag den Anfang macht, sagt `families.week_starts_on` — in der Voreinstellung **Samstag**, weil dann eingekauft wird. Ein Aufruf mitten in der Woche landet deshalb im selben Plan wie der Aufruf am Wochenanfang.

**Leere Slots sind Datensätze, keine Lücken.** Beim Anlegen entstehen alle sieben Tage auf einmal. Das kostet sieben Zeilen und spart jede Sonderbehandlung: Ein leerer Platz lässt sich sperren, bewerten und einzeln neu würfeln, ohne dass beim Belegen erst etwas entstehen müsste.

**Mandantentrennung:** `BelongsToFamily` auf allen drei Models, Verbund-Fremdschlüssel `(meal_plan_id, family_id)` in der Datenbank (R-03). Bei `recipe_id` geht das nicht — MySQL lässt `ON DELETE SET NULL` nicht zu, wenn eine Spalte des Verbunds NOT NULL ist. Dort greift stattdessen die dritte Ebene: Die ID aus dem Formular wird durch den Global Scope **nachgeladen**, eine fremde trifft ins Leere (dafür gibt es einen Test).

---

## B · Der Vorschlagsgenerator (F-06)

Bewusst **regelbasiert und deterministisch**. Sieben Abendessen auszuwählen ist kein Problem, das eine KI braucht — es ist eine Punktevergabe. Das hat drei Vorteile, die im Alltag mehr wiegen als Raffinesse: Es läuft in Millisekunden, es ist testbar, und man kann erklären, warum ein Gericht vorgeschlagen wurde.

Jedes Rezept bekommt für jeden offenen Platz eine Punktzahl (`SuggestionEngine::GEWICHTE`):

| Kriterium | Punkte | Regel |
|---|---:|---|
| Letzte Zubereitung | 30 | je länger her, desto besser; ab einem halben Jahr macht es keinen Unterschied mehr |
| Protein-Abwechslung | 25 | volle Punkte nur, wenn am Vor- oder Folgetag kein Gericht mit demselben Protein-Tag steht |
| Vorratsdeckung | 20 | Anteil der Zutaten, die schon im Vorrat liegen |
| Aufwand | 20 | werktags zählt `quick`, am Wochenende darf es `elaborate` sein |
| Küchen-Abwechslung | 15 | höchstens zwei Gerichte derselben Küche je Woche |
| Ablaufdatum | 15 | was innerhalb einer Woche abläuft, drängt nach vorn |
| Pflanzenpunkte | 10 | gedeckelt bei zehn |
| Favorit | 5 | kleiner Bonus, kein Freifahrtschein |

Die Kriterien stammen aus Abschnitt 4.4 des Architekturkonzepts. Sie greifen auf das zurück, was in den Schritten 6 und 7 entstanden ist: Tags vom Typ `protein` und `cuisine`, `plant_points` und `effort` am Rezept, `pantry_items.current_qty` und `best_before` aus dem Vorrat.

**Vergeben bleibt vergeben.** Ein Rezept, das in dieser Woche schon irgendwo steht — auch von Hand eingetragen —, kommt nicht noch einmal. Sonst stünden am Ende sieben Currys da, oder der Generator schlüge ausgerechnet das Gericht vor, das ohnehin am Dienstag ansteht.

**Die Belegung wächst mit.** Nach jedem gesetzten Platz fließt er in die Bewertung des nächsten ein — deshalb entscheidet der zweite Vorschlag anders als der erste.

---

## C · Vorschlag, Annahme, Neuwurf (F-07, F-08)

Der Lauf wird **protokolliert, bevor er angenommen ist** (`meal_plan_suggestions`). Nur so lässt sich „nochmal würfeln" von „so lassen" unterscheiden: Was schon einmal vorgeschlagen und nicht angenommen wurde, ist beim nächsten Wurf ausgeschlossen.

Der Vorschlag wird erst **gezeigt** und dann übernommen. Direkt einzutragen wäre schneller, aber wer die Woche schon halb geplant hat, will vorher sehen, was sich ändert.

Beim Annehmen gilt: Wer inzwischen selbst etwas eingetragen oder den Platz gesperrt hat, behält es. Der Vorschlag ist älter als die Entscheidung.

Gesperrte Plätze (F-05) rührt der Generator nicht an — ohne das wäre jeder Wurf ein Alles-oder-nichts. Die Sperre gilt dem **Platz**, nicht dem Inhalt: Wer einen gesperrten Slot leert, hat weiterhin einen gesperrten, jetzt leeren Slot.

---

## D · Auf die Einkaufsliste (F-09)

`MealPlanManager::toShoppingList()` schiebt jedes eingeplante Rezept durch `RecipeToList` — denselben Weg, den die Übernahme eines einzelnen Rezepts geht. Damit gelten automatisch die Regeln aus Schritt 6: skaliert auf die Portionen des Slots, Vorratszutaten abgewählt, gleiche Zutaten aus verschiedenen Rezepten zu **einem** Eintrag zusammengefasst (D-04 … D-06).

Zwei Rezepte mit Zwiebeln ergeben einen Listeneintrag über drei Zwiebeln, nicht zwei Einträge. **Bereits gekochte Slots** bleiben außen vor — was gegessen ist, muss nicht mehr eingekauft werden.

Der Plan merkt sich die Liste (`meal_plans.list_id`), damit die Seite zeigen kann, dass die Übernahme schon gelaufen ist.

---

## E · Die Seiten

| Seite | Was man dort tut |
|---|---|
| `/wochenplan` | Woche ansehen, Plätze belegen/leeren/sperren, gekocht haken, würfeln, übernehmen, kopieren |
| `/wochenplan?woche=JJJJ-MM-TT` | eine andere Woche; ein unbrauchbares Datum führt still zur laufenden Woche |
| `/wochenplan?mahlzeiten[]=lunch&…` | zusätzliche Mahlzeiten; Standard ist nur das Abendessen |
| `/wochenplan/historie` | was wann zuletzt auf dem Tisch stand (F-12), dort auch „nie vorschlagen" (F-15) |
| `/wochenplan/drucken` | Ausdruck für den Kühlschrank (F-13), ohne Navigation |

Jeder Platz trägt sein eigenes Formular: ein Rezept aus der Auswahl **oder** ein Freitext („Reste", „Essen gehen") — beides gleichzeitig wäre widersprüchlich, deshalb löscht das eine das andere. Dazu optional abweichende Portionen (F-10).

Rechte wie bei Rezepten und Vorrat: **jedes Mitglied** darf planen. Ein Wochenplan ordnet nichts für andere an.

---

## F · Tests

`tests/Feature/Wochenplan/WochenplanTest.php` — 17 Tests. Abgedeckt sind unter anderem: die Woche beginnt am eingestellten Wochentag, ein zweiter Aufruf legt keinen zweiten Plan an, Rezept und Freitext schließen sich aus, Leeren lässt die Sperre stehen, der Vorschlag geht an handbelegten und gesperrten Plätzen vorbei, jedes Gericht kommt je Woche nur einmal, ausgeschlossene Rezepte werden nie vorgeschlagen (bleiben aber von Hand setzbar), die Übernahme auf die Liste fasst Zutaten zusammen, gekochte Gerichte kommen nicht mehr auf die Liste, ein fremder Plan endet in 404 und ein fremdes Rezept landet nicht im Plan.

---

## Abschluss-Check für Schritt 8

- [x] F-01 Wochenplan einer Kalenderwoche öffnen
- [x] F-02 Slot manuell mit einem Rezept belegen
- [x] F-03 Slot mit Freitext belegen
- [x] F-04 Slot leeren
- [x] F-05 Slot sperren
- [x] F-06 Wochenvorschlag generieren (Scoring nach Abschnitt 4.4)
- [x] F-07 Einzelnen Vorschlag neu würfeln
- [x] F-08 Vorschlag annehmen oder verwerfen
- [x] F-09 Wochenplan in die Einkaufsliste übernehmen
- [x] F-10 Portionen je Slot abweichend festlegen
- [x] F-11 Slot als „gekocht" markieren
- [x] F-12 Historie: welches Gericht wann zuletzt auf dem Tisch stand
- [x] F-13 Wochenplan drucken
- [x] F-14 Plan in die Folgewoche kopieren
- [x] F-15 Rezept vom Vorschlagsgenerator ausschließen
