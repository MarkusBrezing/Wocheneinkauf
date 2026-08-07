# Schritt 4 – Einkaufsliste

**Ziel laut Architekturkonzept (Abschnitt 6):** *Funktionsgleichstand zur heutigen App, aber mehrmandantenfähig.*
**Abgedeckte Use Cases:** C-01 … C-17.
**Zusätzlich auf Wunsch:** Einkauf archivieren und die fehlenden Artikel auf die neue Liste schreiben (A);
Verfügbarkeit je Markt (F); Bestandsaufnahme des Grundvorrats (G); Vorlagen für neue Listen (H);
Zusammenführung von Fixartikeln und Grundvorrat (I).

---

## A · Der Abschluss eines Einkaufs

Das ist die Funktion, um die dieser Schritt gebaut ist, und sie beschreibt zugleich den Ablauf im Laden: Abgehakt ist, was im Wagen liegt. Was am Ende offen bleibt, gab es nicht, war zu teuer oder wurde vergessen — und genau das braucht man beim nächsten Mal wieder.

„Einkauf abschließen" macht deshalb drei Dinge in einer Transaktion (`ListArchiver::archive`):

1. Die Liste bekommt `status = 'archived'` und einen Zeitstempel.
2. Eine neue Liste wird angelegt — Markt und Ersteller übernommen.
3. Jeder **offene** Eintrag wird auf die neue Liste **kopiert**: Menge, Notiz, Einheit und Warengruppe unverändert, Haken zurückgesetzt.

Zwei Entscheidungen darin sind nicht selbstverständlich:

**Kopiert, nicht verschoben.** Das Archiv soll zeigen, was auf der Liste stand — einschließlich dessen, was nicht im Wagen landete. Verschöbe man die offenen Einträge, sähe jeder archivierte Einkauf im Nachhinein perfekt abgearbeitet aus, und die Frage „was fehlt hier eigentlich ständig?" wäre nicht mehr zu beantworten.

**Kopiert, nicht neu eingelesen.** Die Übernahme läuft nicht durch `addOrMerge`, sondern dupliziert den Datensatz. Die neue Liste ist leer, es gibt also nichts zusammenzuführen — und eine inzwischen geänderte Stichwortregel soll einen Artikel nicht still in ein anderes Regal schieben, nur weil er eine Woche später noch einmal auf der Liste steht.

Die Übernahme ist abschaltbar (Kontrollkästchen im Formular, `offene_uebernehmen`), damit C-11 auch pur bleibt: nur archivieren, leer weitermachen.

---

## B · Was sonst entstanden ist

### Zwei Tabellen, eine Invariante

| Migration | Tabellen |
|---|---|
| `2026_08_01_002000_create_shopping_lists_table` | `shopping_lists`, `shopping_list_items` |
| `2026_08_01_002100_create_market_product_table` | `market_product` (Verfügbarkeit, siehe Abschnitt F) |
| `2026_08_02_000200_create_list_templates_table` | `list_templates`, `list_template_items` (Vorlagen, Abschnitt H) |
| `2026_08_02_000400_merge_standing_items_into_products` | löst `standing_items` auf (Abschnitt I) |

Je Familie darf es nur **eine** aktive Liste geben — sonst wäre „die Einkaufsliste" nicht mehr eindeutig. Das erzwingt die Datenbank über eine generierte Spalte:

```sql
aktiv_marker TINYINT UNSIGNED GENERATED ALWAYS AS (if(status = 'active', 1, NULL)) STORED,
UNIQUE KEY (family_id, aktiv_marker)
```

NULL wird im UNIQUE-Index nicht verglichen: beliebig viele archivierte Listen, höchstens eine aktive. Dieselbe Ebene 2 wie bei den Verbund-Fremdschlüsseln aus Schritt 3 — die Regel hält auch dann, wenn jemand an Eloquent vorbei schreibt. Geprüft in `ArchivTest::test_die_datenbank_laesst_keine_zweite_aktive_liste_zu`.

Die Listeneinträge hängen über den Verbund `(list_id, family_id) → shopping_lists (id, family_id)` an ihrer Liste (R-03). Ein Eintrag kann damit nicht in der Liste einer fremden Familie landen.

### Modulschnitt

```
app/Modules/ShoppingList/
  Models/    ShoppingList, ShoppingListItem,
             ListTemplate, ListTemplateItem
  Services/  ItemTextParser, ShoppingListManager, ListSorter,
             ListArchiver, WeekStarter, ListSnapshot,
             PantryInventory, TemplateApplier
  Data/      ParsedItem, ListSection, Units
```

`ShoppingListManager` ist die einzige Stelle, an der Einträge entstehen und sich ändern. Der Grund ist der Zuschnitt der Aufrufer: Formular, Sync-Endpunkt, Assistent „Neue Woche" und — ab Schritt 6 — Rezepte und Wochenplan brauchen dieselben Regeln. Lägen sie im Controller, bekäme jeder Einstieg seine eigene, leicht abweichende Fassung. Genau dieses Muster hat in der alten App zu `addItem`, `addOrMergeItem` und `addRecipeToList` geführt.

### Freitext lesen (C-01)

`ItemTextParser` zerlegt die eine Eingabezeile:

| Eingabe | Name | Menge | Einheit | Notiz |
|---|---|---|---|---|
| `2 l Vollmilch` | Vollmilch | 2 | l | — |
| `500g Mehl` | Mehl | 500 | g | — |
| `1/2 Bund Petersilie` | Petersilie | 0,5 | Bund | — |
| `1-2 Zwiebeln, rote` | Zwiebeln | — (`1-2`) | — | rote |
| `Mehl 500 g` | Mehl | 500 | g | — |
| `Vollmilch 1,5%` | Vollmilch 1,5% | — | — | — |

Der Leitgedanke: **im Zweifel bleibt der Text stehen.** Ein falsch geratener Name ärgert täglich, eine nicht erkannte Menge kostet einen Tastendruck. Daraus folgen drei Regeln:

- Ein Wort gilt nur als Einheit, wenn `Units` es kennt. Sonst wäre in „3 Zwiebeln" die Einheit „Zwiebeln" und der Artikel namenlos.
- Steht die Menge **hinten**, muss eine bekannte Einheit dabeistehen. Eine nackte Zahl am Zeilenende gehört häufiger zum Namen — `Vollmilch 1,5%` ist keine Bestellung über eineinhalb Milch.
- Ein Komma trennt die Notiz ab, außer es steht zwischen Ziffern (`1,5 l Milch`).

Was sich nicht rechnen lässt, wird nicht gerechnet: `1-2` landet in `amount_raw`, `amount` bleibt NULL — und wird damit auch nicht addiert.

### Zusammenführen statt Dubletten (C-17)

Beim Hinzufügen sucht der Manager einen passenden **offenen** Eintrag — über die Katalogverknüpfung, sonst über die Normalform des Namens (dieselbe wie in `products`, „Tomaten (Bio)" = „tomaten").

| Fall | Ergebnis |
|---|---|
| gleiche Einheit, beide Mengen rechenbar | Mengen werden addiert |
| vorhandener Eintrag ohne Menge | die neue Angabe wird die Menge |
| Liter + Gramm, oder `1-2` + `3` | vorhandene Menge bleibt, Zusatz wandert in die Notiz (`auch 500 g`) |

Liter und Gramm zusammenzuzählen wäre geraten. Der stille Weg über die Notiz verliert nichts, und niemand findet auf dem Zettel eine Menge, die er nie aufgeschrieben hat.

**Abgehakte Einträge werden nicht zusammengeführt.** Was im Wagen liegt, ist erledigt; wer denselben Artikel danach noch einmal aufschreibt, meint eine zweite Packung.

### Sortierung (C-06, C-16)

`ListSorter` gliedert die Liste nach den Regalbereichen des gewählten Marktes in Laufreihenfolge (`market_sections.sort`, Zuordnung aus B-04). Ohne Markt bleibt die Warengruppe die Gliederung — besser als Eingabereihenfolge. Artikel, deren Warengruppe in keinem Bereich liegt, sammeln sich am Ende unter *Ohne Regalbereich*.

Erledigtes sinkt **innerhalb seines Abschnitts** nach unten, nicht in einen eigenen Block am Listenende: Vor dem Regal will man sehen, was hier schon im Wagen liegt.

### Assistent „Neue Woche" (C-10)

Zwei Handgriffe, die sonst jede Woche anfallen: den alten Einkauf abschließen (mit Übernahme der fehlenden Artikel) und den Grundvorrat aus B-08 wieder aufschreiben. Eine leere Liste wird nicht archiviert — sonst häufen sich leere Archiveinträge.

Die Rezeptauswahl, die C-10 ebenfalls nennt, kommt mit dem Rezeptmodul in Schritt 6. Die Anschlussstelle ist `ShoppingListManager::addOrMerge` mit `source='recipe'`.

### Archiv (C-12, C-13, C-14)

Beim Laden einer archivierten Liste (C-13) wird die aktuelle Liste **ergänzt**, nicht ersetzt — über dieselbe Zusammenführung wie beim Tippen. Wer eine leere Liste will, archiviert vorher. An archivierten Listen selbst lässt sich nichts mehr ändern (403); sie sind die Historie des Einkaufs.

---

## C · Geräteabgleich (C-07, C-08)

Zwei Endpunkte, hinter der normalen Sitzung samt CSRF-Schutz — das Frontend läuft auf derselben Domain, ein zweites Tokenverfahren wäre zusätzliche Angriffsfläche ohne Gewinn. Das CSRF-Token steht im Meta-Element des Layouts.

```
GET  /api/einkaufsliste/stand[?seit=<ISO-8601>]
POST /api/einkaufsliste/stand
```

Der Pfad beginnt mit `api/`, weil `bootstrap/app.php` Fehlerantworten über `shouldRenderJsonWhen` genau dort als JSON rendert und überall sonst als Weiterleitung. Ein Sync-Client kann mit einer Weiterleitung nichts anfangen.

**Drei Zusagen an den Client:**

1. **Delta.** Mit `seit` kommen nur Einträge mit jüngerem `updated_at` — Löschungen eingeschlossen, denn sie sind weich. Eine ersatzlos verschwundene Zeile taucht in keinem Delta auf, und das Gerät, das gerade offline war, würde sie nie wieder los. Die Zeitmarke für die nächste Abfrage liefert der Server (`server_zeit`), nicht die möglicherweise falsch gehende Uhr im Handy.

2. **Kein blindes Überschreiben.** Der POST trägt die dem Client bekannte `version` (und optional `liste_id`). Weicht sie ab, antwortet der Server mit **409** und dem aktuellen Stand:

   ```json
   { "fehler": "version_konflikt", "gesendete_version": 12,
     "liste": { "version": 14, … }, "eintraege": [ … ] }
   ```

   Wurde zwischenzeitlich archiviert, lautet der Fehler `liste_gewechselt` — sonst könnte ein Client mit zufällig passender Versionsnummer auf der falschen Liste weiterarbeiten. Die Liste wird für die Prüfung mit `lockForUpdate` geladen; sonst könnten zwei gleichzeitige Batches beide die Prüfung bestehen.

3. **Wiederholbarkeit.** Jede Mutation trägt eine `client_uuid`. Ein zweiter Versand — der Normalfall nach einem Verbindungsabbruch — legt nichts doppelt an.

Der letzte Punkt hat einen Haken, der Aufmerksamkeit verdient: Wird ein hinzugefügter Artikel mit einem vorhandenen **zusammengeführt** (C-17), gäbe es keinen Datensatz, der die `client_uuid` trägt — der nächste Versand würde die Menge ein zweites Mal addieren. Deshalb legt `addFromClient` in diesem Fall einen sofort weich gelöschten Eintrag an. Dieser Grabstein hat zwei Aufgaben: Er belegt die UUID gegen Wiederholungen, und er sagt den anderen Geräten, dass ihr Eintrag aufgegangen ist und nicht etwa verloren. Geprüft in `SyncTest::test_eine_zusammengefuehrte_mutation_bleibt_wiederholbar`.

**Was hier bewusst fehlt:** der Client. Service Worker, Offline-Cache und die lokale Warteschlange sind Schritt 9 der Roadmap. Dieser Schritt liefert die Serverseite, ohne die kein Offline-Betrieb möglich wäre.

---

## D · Rechte — die Festlegung aus Schritt 3, fortgeschrieben

Punkt 4 aus Abschnitt 7 des Konzepts (Rolle `child`) wird hier praktisch relevant. Die Festlegung:

| Handlung | Wer darf |
|---|---|
| Hinzufügen, abhaken, bearbeiten, löschen, Markt umschalten | **jedes Mitglied**, auch `child` |
| Einkauf archivieren, „Neue Woche", Archiv laden | **jedes Mitglied** |
| Archiveintrag endgültig löschen (C-14) | **Owner und Admin** |

Die Begründung ist dieselbe wie bei den Artikeln in Schritt 3: Einkaufen ist Alltag, und ein versehentlich gelöschter Listeneintrag ist in fünf Sekunden neu getippt. Ein Archiveintrag dagegen ist Historie der ganzen Familie und lässt sich nicht wieder aufschreiben — das ist die einzige Stelle des Moduls mit einer Rollenprüfung.

Wenn `child` weiter eingeschränkt werden soll (etwa: darf abhaken, aber nicht löschen), ist `ShoppingListController` die Stelle dafür — nicht der Manager.

---

## E · Testdaten

```bash
php artisan db:seed --class=DemoDataSeeder
```

Legt die Familie *Familie Demo* an: drei Mitglieder (Owner, Admin, Kind), 30 Katalogartikel, drei Märkte mit eigener Laufreihenfolge (Supermarkt, Hofladen, Asialaden), vier Artikel mit eingeschränkter Verfügbarkeit, sechs Artikel als Grundvorrat, zwei Vorlagen (davon „Wochenbedarf" als Standard), eine laufende Liste und **drei abgeschlossene Einkäufe** aus den letzten drei Wochen.

Anmeldung: `demo@wocheneinkauf.test` / `passwort` (ebenso `partner@…` und `kind@…`).

Die Historie entsteht über dieselben Dienste wie im Betrieb — die archivierten Einkäufe wurden also wirklich abgeschlossen, samt Übernahme der fehlenden Artikel in die jeweils nächste Woche. Nur so zeigen die Daten auch das Verhalten und nicht bloß gefüllte Tabellen. Der Seeder ist additiv: Sind die Demodaten schon da, tut er nichts.

---

## F · Verfügbarkeit je Markt

Nachtrag über den Use-Case-Katalog hinaus, aus demselben Alltag wie der Abschluss: **Tomaten gibt es überall, Misopaste nur im Asialaden.**

### Leer heißt überall

Die Zuordnung liegt in der Pivot-Tabelle `market_product` — und die bleibt für den Normalfall leer. Eingetragen wird nur die Ausnahme.

Das ist der Grund gegen ein zusätzliches Kennzeichen am Artikel (`ist_eingeschraenkt` o. ä.): Es gäbe den widersprüchlichen Zustand „eingeschränkt, aber auf keinen Markt" — einen Artikel, den es nirgends gibt. Ohne Kennzeichen ist jeder Zustand der Tabelle sinnvoll lesbar, und niemand muss für 200 Katalogartikel bestätigen, dass es sie tatsächlich überall gibt.

Zwei Nebenwirkungen, die zur Entscheidung gehören:

- Ein Formular, das das Feld `verfuegbarkeit` **nicht** sendet, lässt die vorhandene Pflege unangetastet (`ProductController::verfuegbarkeitSpeichern`). Sonst würde ein älteres Frontend die Einschränkung stillschweigend löschen.
- Beim Zusammenführen von Dubletten (B-06) wandert die gepflegte Einschränkung mit — bei beidseitiger Pflege als Vereinigung. Wer sie einmal eingetragen hat, soll sie nicht durch ein Aufräumen verlieren.

Die Tabelle hängt über Verbund-Fremdschlüssel an Markt **und** Artikel; dafür haben `markets` und `products` jetzt ebenfalls ein `UNIQUE (id, family_id)`. Eine familienübergreifende Zuordnung lässt die Datenbank damit nicht zu (R-03), und untergeschobene IDs aus einem manipulierten Formular laufen schon vorher über den Global Scope ins Leere.

### Was die Liste damit macht

Bei gewähltem Markt sammelt `ListSorter` am **Ende** der Liste einen Abschnitt **„Gibt es hier nicht"**. Ohne diesen Abschnitt lägen die Artikel unter *Ohne Regalbereich* und sähen aus wie ein Pflegefehler statt wie eine Information. Im passenden Markt steht derselbe Artikel ganz normal in seinem Regal.

Der Hinweis „nur bei Asialaden" erscheint außerdem an der Zeile, in der Druckansicht und — als `nur_bei_maerkten` — im Sync. Und schon beim Aufschreiben: *„Misopaste steht auf der Liste. Gibt es nur bei Asialaden."* Das ist der Moment, in dem die Information nützt; im Laden ist es zu spät.

**Freitext ohne Katalogverknüpfung gilt als überall erhältlich.** Über einen Eintrag, den der Katalog nicht kennt, ist schlicht nichts bekannt — eine Warnung ins Blaue hinein wäre schlimmer als keine.

---

## G · Bestandsaufnahme des Grundvorrats

Eine Erfassungsmaske unter **Liste → Bestand des Grundvorrats**: Sie zeigt je Grundvorratsartikel die Sollmenge und ein Feld für den Bestand. Was zur Sollmenge fehlt, geht gesammelt auf die Einkaufsliste — das ist E-09 („Fehlmengen gesammelt übernehmen"), auf den Grundvorrat beschränkt und ohne eigene Tabellen. Die vollständige Inventur mit Zählhistorie und Bewegungskonto bleibt Modul E in Schritt 7.

Die eine Entscheidung, auf die es dabei ankommt:

> **Ein leeres Feld heißt „nicht gezählt", eine 0 heißt „nichts mehr da".**

Ohne diesen Unterschied müsste man die Zählung in einem Zug durchhalten — wer beim Vorratsschrank unterbrochen wird, fände sonst seine Liste voller Artikel, die er nie geprüft hat. Für Grundvorrat ohne Sollmenge (mit denen sich nichts rechnen lässt) gibt es stattdessen ein Kästchen „fehlt".

Die Seite ist so gebaut, dass sie sich auch **ausdrucken** lässt: Im Druck verschwindet die Bedienoberfläche, und die Bestandsspalte wird zur Schreiblinie.

---

## H · Vorlagen für neue Listen

Die zweite Ergänzung über den Use-Case-Katalog hinaus: benannte Sammlungen von Artikeln für einen Anlass — „Wochenbedarf", „Grillabend", „Urlaubsvorrat". Verwaltet unter **Vorlagen**.

Das Verhältnis zum Grundvorrat (B-08) ist eine Verallgemeinerung: Der Grundvorrat ist *die eine* unbenannte Liste dessen, was ohnehin im Haus sein soll. Vorlagen dürfen mehrere sein und tragen einen Namen. Beides bleibt nebeneinander bestehen, weil beides einen eigenen Platz im Ablauf hat — der Grundvorrat im Assistenten und in der Bestandsaufnahme, die Vorlagen beim Anlegen einer Liste.

### Angewendet wird über die Zusammenführung

`TemplateApplier` schreibt keine Kopien, sondern geht durch `addOrMerge`. Eine Vorlage trifft fast immer auf eine Liste, auf der schon etwas steht: Wer „2 l Milch" notiert und danach die Wochenvorlage mit „2 l Milch" übernimmt, will vier Liter — aber einen Eintrag (C-17).

### Die Standardvorlage und die Reihenfolge beim Abschluss

Eine Vorlage lässt sich zur **Standardvorlage** machen (★). Sie kommt dann auf jede neu angelegte Liste — auch auf die, die nach dem Abschluss eines Einkaufs beginnt. Damit steht die nächste Liste nicht leer da.

Das erzwingt eine Reihenfolge, die im `ListArchiver` festgeschrieben ist:

1. alte Liste archivieren
2. neue Liste anlegen — **ohne** Standardvorlage
3. die fehlenden Artikel kopieren
4. **erst jetzt** die Standardvorlage anwenden

Andernfalls stünde die Vorlagenposition schon da, bevor der fehlende Artikel kopiert wird — und die Kopie legt bewusst einen Datensatz an, ohne zusammenzuführen. Ergebnis wären zwei Zeilen Milch. In dieser Reihenfolge trifft die Vorlage auf den bereits übernommenen Artikel und verschmilzt mit ihm.

Der Assistent „Neue Woche" geht denselben Weg mit umgekehrtem Vorzeichen: Er archiviert **ohne** Standardvorlage und wendet nur die im Formular gewählten Vorlagen an (die Standardvorlage ist dort vorausgewählt). Sonst käme sie zweimal — einmal automatisch, einmal bewusst — und die Mengen verdoppelten sich.

**Was sich dabei summiert, summiert sich absichtlich:** Wer die Milch nicht bekommen hat, findet sie mit der neuen Wochenmenge zusammengezählt wieder. Wer sie gekauft und abgehakt hat, bekommt nur die Vorlagenmenge — Abgehaktes wird nicht übernommen.

### Mandantenfähigkeit

Beide Models tragen `BelongsToFamily`; das Route-Model-Binding läuft durch den Global Scope, eine fremde Vorlage endet also in 404. Die Positionen hängen über den Verbund `(template_id, family_id)` an ihrer Vorlage (R-03), und die IDs aus dem Assistenten werden über den Scope nachgeladen statt übernommen (Ebene 1½). Für alle drei Ebenen gibt es einen Test in `VorlagenTest`.

`is_default` steht wie bei `Market` nicht in `#[Fillable]` — es darf je Familie nur eine Standardvorlage geben, und das lässt sich nur sicherstellen, wenn das Setzen durch `makeDefault()` läuft.

**Rechte:** wie beim Artikelkatalog darf jedes Mitglied Vorlagen pflegen. Sie sind Alltag, keine Strukturentscheidung — anders als Warengruppen oder Regalbereiche, die die Sortierung für alle festlegen.

### Der bequemste Weg zu einer Vorlage

„Aktuelle Liste als Vorlage sichern" auf der Vorlagenseite: einmal von Hand zusammenstellen, dann behalten. Übernommen wird alles, was auf der Liste steht — auch das Abgehakte, denn wer den Einkauf gerade erledigt hat, will genau ihn beim nächsten Mal wieder.

---

## I · Nachtrag: Fixartikel sind im Grundvorrat aufgegangen

Bis hierher führte die Anwendung zwei Begriffe für dieselbe Sache:

- **Fixartikel** (`standing_items`) — was jede Woche auf die Liste soll, mit Sollmenge und eigener Pflegeseite.
- **Grundvorrat** (`products.is_pantry_staple`) — was im Schrank steht und von Rezepten als vorhanden vorausgesetzt wird (D-05).

In der Praxis ist das derselbe Bestand: Milch, Butter, Mehl, Klopapier. Zwei Pflegeorte für eine Sache bedeuten doppelte Arbeit und die stete Frage, welcher gerade gilt. Seit der Migration `2026_08_02_000400_merge_standing_items_into_products` gibt es nur noch den **Grundvorrat**:

| vorher | jetzt |
|---|---|
| `standing_items.amount` | `products.pantry_target_amount` |
| `standing_items.unit` | `products.default_unit` |
| `standing_items.is_active` | `products.is_pantry_staple` |
| eigene Seite *Fixartikel* | Spalten *Grundvorrat* und *Sollmenge* im Artikelkatalog |

Die Migration überführt jeden **aktiven** Fixartikel in den Katalog — verknüpfte aktualisieren ihren Artikel, unverknüpfte legen einen an, damit keine Vorlage verlorengeht. Deaktivierte werden übersprungen: Sie waren abgeschaltet, und ein abgeschalteter Grundvorrat ist keiner. Vorhandene Angaben am Artikel werden nicht überschrieben — was dort gepflegt ist, war die bewusstere Entscheidung.

Der Assistent „Neue Woche" und die Bestandsaufnahme (Abschnitt G) arbeiten seitdem mit `Product::grundvorrat()`. Die Bestandsaufnahme heißt jetzt **Bestand des Grundvorrats**.

---

## J · Tests

226 Tests insgesamt, 881 Assertions.

```bash
php artisan test
```

| Testdatei | Deckt ab |
|---|---|
| `Einkaufsliste/EinkaufslisteTest` | C-01 … C-06, C-09, C-15, C-16, C-17, Versionszählung |
| `Einkaufsliste/ArchivTest` | C-11 … C-14, Übernahme der fehlenden Artikel, Ein-Listen-Invariante |
| `Einkaufsliste/NeueWocheTest` | C-10 |
| `Einkaufsliste/SyncTest` | C-07, C-08, Konflikt, Idempotenz, Grabstein |
| `Einkaufsliste/TrennungUndRechteTest` | Mandantentrennung auf beiden Ebenen, Rollen |
| `Einkaufsliste/VerfuegbarkeitTest` | Pflege, Listenabschnitt, Hinweise, beide Trennungsebenen |
| `Einkaufsliste/GrundvorratInventurTest` | Fehlmengen, „nicht gezählt" gegen „nichts da", Mandantentrennung |
| `Einkaufsliste/VorlagenTest` | Verwaltung, Anwenden, Standardvorlage, Reihenfolge beim Abschluss |

---

## Abschluss-Check für Schritt 4

- [ ] `php artisan test` → 226 Tests grün
- [ ] `/health` zeigt `"ausgefuehrt": 21`, `"offen": 0`
- [ ] `2 l Milch` eintippen, dann `1 l Milch` → **ein** Eintrag mit 3 l
- [ ] Markt umschalten → die Abschnitte ordnen sich neu
- [ ] Einen Artikel abhaken, Einkauf archivieren → der offene Rest steht auf der neuen Liste
- [ ] Archiveintrag öffnen → die abgehakten Artikel stehen durchgestrichen darin
- [ ] Als Mitglied ohne Admin-Rolle einen Archiveintrag löschen wollen → 403
- [ ] `GET /api/einkaufsliste/stand` liefert JSON mit `version`
- [ ] Einen Artikel auf „nur in diesen Märkten" stellen, im anderen Markt sortieren → er steht unter *Gibt es hier nicht*
- [ ] Bestand eines Grundvorratsartikels unter der Sollmenge eintragen → die Differenz steht auf der Liste
- [ ] Eine Vorlage zur Standardvorlage machen, Einkauf abschließen → die neue Liste beginnt damit

---

## Beobachtungen am Rand — inzwischen erledigt

Beim Befüllen der Demodaten fielen zwei Fehlgriffe der Auto-Kategorisierung auf („Apfelsaft" bei Obst und Gemüse, „Äpfel" gar nicht erkannt). Die Nachbesserung des Startdatensatzes ist in [Schritt-3, Abschnitt E](Schritt-3_Stammdaten-und-Maerkte.md) beschrieben — sie förderte zwei schwerere Fälle zutage, als der Anlass vermuten ließ.

---

## Danach: Schritt 5

Migration der eigenen Daten aus `data.json`, `history.json` und `settings.json` in die Datenbank. Vorzumerken:

- `ProductMerger::verweiseUmhaengen()` deckt jetzt `standing_items` und `shopping_list_items` ab; Rezeptzutaten und Vorratspositionen kommen dort dazu.
- Die Historie der alten App wird zu archivierten Listen — `ListArchiver` erzeugt sie im Betrieb, für den Import genügt `status='archived'` samt `archived_at`.
- `client_uuid` ist beim Import frei zu vergeben — aber **je Familie** eindeutig, nicht nur je Liste: Das Route-Model-Binding löst einen Eintrag ohne Listenbezug auf. Eine echte UUID je Zeile erledigt das von selbst.
