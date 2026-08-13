# Wocheneinkauf · Technische Dokumentation

**Stand:** 05.08.2026 · **Laravel 13 · PHP 8.4 · MariaDB 10.11** · 94 Klassen · 301 Tests

Die Diagramme sind in UML-Notation als Mermaid-Quelltext eingebettet. GitHub, GitLab und VS Code zeichnen sie unmittelbar; wer sie als Bild braucht, kopiert den Block nach <https://mermaid.live>.

**Verwandte Dokumente:** [Datenbankdokumentation](Wocheneinkauf-Datenbank.md) · [Anwenderhandbuch](handbuch/) · Schrittprotokolle `Schritt-2` bis `Schritt-9`.

---

## 1 · Wozu die Anwendung da ist

Eine Familie führt eine gemeinsame Einkaufsliste, einen Vorrat, Rezepte und einen Wochenplan. Vier Eigenschaften bestimmen jede Entwurfsentscheidung:

| Eigenschaft | Folge für den Entwurf |
|---|---|
| **Mehrere Familien auf einem Server** | Mandantentrennung auf drei Ebenen (Abschnitt 7.1) |
| **Mehrere Geräte gleichzeitig** | Optimistisches Sperren, wiederholbares Senden, weiches Löschen |
| **Schlechtes Netz im Supermarkt** | Eigene Einkaufsansicht, Service Worker, kein Framework im Frontend |
| **Kein Betrieb durch Fachleute** | Regeln, die die Datenbank selbst durchsetzt, statt Verlass auf den Code |

---

## 2 · Systemkontext

```mermaid
graph TB
    subgraph Nutzer
        A["👤 Familienmitglied<br/>PC / Tablet"]
        B["📱 Familienmitglied<br/>Handy im Laden"]
    end

    S["Wocheneinkauf<br/><i>Laravel 13, Blade, MariaDB</i>"]

    subgraph Fremdsysteme
        M["Mailversand<br/><i>SMTP</i>"]
        H["Hoster<br/><i>Apache, MariaDB</i>"]
    end

    A -->|"HTTPS, Sitzung"| S
    B -->|"HTTPS, Service Worker"| S
    S -->|"Einladungen, Passwort zurücksetzen"| M
    S -->|"läuft auf"| H
```

Bewusst **keine** Fremdsysteme darüber hinaus: keine Analyse, kein Tracking, keine Schriften von Dritten, kein Content Delivery Network. Jede zusätzliche Verbindung wäre ein Absatz mehr in der Datenschutzerklärung und ein Ausfallgrund mehr im Betrieb.

---

## 3 · Bausteinsicht

### 3.1 Pakete

```mermaid
graph TB
    subgraph HTTP["app/Http"]
        C["Controllers<br/><i>23 Stück</i>"]
    end

    subgraph Core["app/Core — Mandant und Zugang"]
        CM["Models<br/>Family, User, Invitation"]
        CS["Services<br/>FamilyProvisioner, InvitationService"]
        CC["Concerns<br/>BelongsToFamily, HasPublicId"]
        CX["Support<br/>FamilyContext, Navigation"]
    end

    subgraph Mod["app/Modules — Fachlichkeit"]
        Cat["Catalog<br/>Artikel, Warengruppen, Regeln"]
        Mar["Markets<br/>Märkte, Regalbereiche"]
        SL["ShoppingList<br/>Liste, Vorlagen, Archiv"]
        Rec["Recipes<br/>Rezepte, Zutaten, Tags"]
        Pan["Pantry<br/>Vorrat, Buchungen"]
        MP["MealPlan<br/>Wochenplan, Vorschläge"]
    end

    C --> SL
    C --> Rec
    C --> Pan
    C --> MP
    C --> Cat
    C --> Mar

    SL --> Cat
    SL --> Mar
    Rec --> SL
    Pan --> SL
    MP --> Rec
    MP --> Pan
    MP --> SL

    Mod -.->|"nutzen"| CC
    Mod -.->|"nutzen"| CX
```

Die Pfeilrichtung ist die Aussage: **Die Einkaufsliste kennt niemanden, der sie befüllt.** Rezept, Vorrat und Wochenplan hängen von ihr ab, nicht umgekehrt. Deshalb landet jede Übernahme durch dieselbe Tür (`ShoppingListManager::addOrMerge`) und wird dort zusammengeführt statt gedoppelt — ohne dass die Liste wissen müsste, dass es Rezepte gibt.

### 3.2 Die Module

| Modul | Aufgabe | Kernklassen |
|---|---|---|
| **Core** | Familie, Konten, Einladungen, Mandantenkontext | `FamilyProvisioner`, `FamilyContext`, `FamilyScope`, `BelongsToFamily` |
| **Catalog** | Artikelkatalog, Warengruppen, automatische Einsortierung | `ProductCategorizer`, `ProductMerger`, `StarterDataInstaller` |
| **Markets** | Märkte und Regalbereiche in Laufreihenfolge | `Market`, `MarketSection` |
| **ShoppingList** | Liste, Freitexterkennung, Sortierung, Archiv, Vorlagen, Abgleich | `ShoppingListManager`, `ItemTextParser`, `ListSorter`, `ListArchiver`, `ListSnapshot` |
| **Recipes** | Rezepte, Zutaten, Schlagworte, Übernahme auf die Liste | `RecipeToList` |
| **Pantry** | Bestand, Buchungen, Fehlmengen | `PantryManager`, `ShortfallToList` |
| **MealPlan** | Wochenplan, Vorschlagsgenerator, Übernahme auf die Liste | `MealPlanManager`, `SuggestionEngine` |

### 3.3 Schichten innerhalb eines Moduls

```mermaid
graph LR
    V["Blade-View"] --> Ctl["Controller<br/><i>prüft Eingaben und Rechte</i>"]
    Ctl --> Svc["Service<br/><i>trägt die Fachregeln</i>"]
    Svc --> Mdl["Model<br/><i>Persistenz, Scopes</i>"]
    Mdl --> DB[("MariaDB<br/><i>erzwingt Invarianten</i>")]
```

Der Controller entscheidet nichts Fachliches. Er nimmt entgegen, prüft und gibt weiter — deshalb sind die Fachregeln einzeln testbar, ohne HTTP.

---

## 4 · Klassendiagramme

### 4.1 Mandant und Zugang

```mermaid
classDiagram
    class Family {
        +string public_id
        +string name
        +int week_starts_on
        +string status
        +users() BelongsToMany
    }

    class User {
        +string public_id
        +string email
        +int current_family_id
        +families() BelongsToMany
        +administers(Family) bool
        +currentFamily() BelongsTo
    }

    class Invitation {
        +string token_hash
        +string role
        +int max_uses
        +expires_at
        +istEinloesbar() bool
    }

    class BelongsToFamily {
        <<trait>>
        +bootBelongsToFamily()
        +family() BelongsTo
    }

    class FamilyScope {
        <<GlobalScope>>
        +apply(Builder, Model)
    }

    class FamilyContext {
        <<Singleton>>
        +aktuelleFamilie() int
        +runFor(int, Closure)
    }

    Family "1" o-- "*" Invitation
    Family "*" -- "*" User : family_user (Rolle)
    BelongsToFamily ..> FamilyScope : registriert
    FamilyScope ..> FamilyContext : liest
```

`FamilyContext` ist ein Singleton je Anfrage. Der Global Scope und die Controller müssen dieselbe Familie sehen — zwei Quellen wären ein Sicherheitsloch mit Ansage.

### 4.2 Einkaufsliste

```mermaid
classDiagram
    class ShoppingList {
        +string public_id
        +string status
        +int version
        +archived_at
        +scopeActive()
        +items() HasMany
        +anzeigename() string
    }

    class ShoppingListItem {
        +string client_uuid
        +string raw_name
        +decimal amount
        +string unit
        +bool is_checked
        +string source
        +mengeAnzeige() string
    }

    class ShoppingListManager {
        +current(User, bool) ShoppingList
        +addOrMerge(...) ShoppingListItem
        +addFromClient(...) ShoppingListItem
        +toggle(item, User)
        +update(item, array)
        +switchMarket(list, Market)
    }

    class ItemTextParser {
        +parse(string) ParsedItem
    }

    class ParsedItem {
        <<readonly>>
        +string name
        +float amount
        +string unit
        +string note
    }

    class ListSorter {
        +sections(ShoppingList) Collection
    }

    class ListArchiver {
        +archive(list, bool, User, bool) ShoppingList
        +loadIntoCurrent(list, User)
    }

    class ListSnapshot {
        +fuer(ShoppingList, Carbon) array
    }

    class TemplateApplier {
        +apply(template, list)
        +applyDefault(list)
        +fromList(list, string) ListTemplate
    }

    ShoppingList "1" *-- "*" ShoppingListItem
    ShoppingListManager ..> ItemTextParser : nutzt
    ItemTextParser ..> ParsedItem : erzeugt
    ShoppingListManager ..> ShoppingList
    ListArchiver ..> ShoppingListManager
    TemplateApplier ..> ShoppingListManager
    ListSorter ..> ShoppingList
    ListSnapshot ..> ShoppingList
```

### 4.3 Rezepte

```mermaid
classDiagram
    class Recipe {
        +string public_id
        +string slug
        +int servings
        +string effort
        +int plant_points
        +bool exclude_from_suggestions
        +gesamtzeit() int
        +scopeSuche(string)
    }

    class RecipeIngredient {
        +string raw_name
        +decimal amount
        +string buy_label
        +string merge_key
        +bool is_pantry
        +alsEingabe(float) ParsedItem
        +zusammenfassungsschluessel() string
    }

    class Tag {
        +string type
        +string name
    }

    class RecipeToList {
        +transfer(recipe, list, int, array) int
        +sammle(recipe, float, array) Collection
    }

    Recipe "1" *-- "*" RecipeIngredient
    Recipe "*" -- "*" Tag : recipe_tag
    RecipeToList ..> Recipe
    RecipeToList ..> ShoppingListManager : addOrMerge
```

### 4.4 Vorrat

```mermaid
classDiagram
    class PantryItem {
        +string location
        +decimal target_qty
        +decimal min_qty
        +decimal current_qty
        +date best_before
        +fehlmenge() float
        +scopeUnterMeldebestand()
    }

    class PantryMovement {
        +decimal qty_delta
        +string reason
        +string ref_type
        +int ref_id
    }

    class PantryManager {
        +ensureItem(Product, string, array) PantryItem
        +book(item, float, string, User) PantryMovement
        +setTo(item, float, string, User) PantryMovement
    }

    class ShortfallToList {
        +fromPantry(User, array) array
    }

    class PantryInventory {
        +uebernehmen(list, array) array
        -fehlmenge(Product, array) ParsedItem
    }

    PantryItem "1" o-- "*" PantryMovement
    PantryManager ..> PantryItem
    ShortfallToList ..> ShoppingListManager
    PantryInventory ..> ShoppingListManager
```

`PantryInventory` gehört zum Modul ShoppingList, nicht zu Pantry: Die Bestandsaufnahme liest die **Sollmenge am Artikel** (`products.pantry_target_amount`) und schreibt auf die Einkaufsliste. Sie rührt den Vorrat nicht an und braucht deshalb auch keine eigenen Tabellen.

### 4.5 Wochenplan

```mermaid
classDiagram
    class MealPlan {
        +string public_id
        +date week_start
        +string status
        +int list_id
        +tage() array
        +kalenderwoche() int
    }

    class MealPlanSlot {
        +date plan_date
        +string meal
        +int recipe_id
        +string custom_text
        +bool is_locked
        +bool is_cooked
        +string origin
        +istBelegt() bool
    }

    class MealPlanSuggestion {
        +string strategy
        +json params
        +json result
        +accepted_at
    }

    class MealPlanManager {
        +forWeek(Carbon, Family, User, array) MealPlan
        +fill(slot, int, string, int) MealPlanSlot
        +suggest(plan, string, User) MealPlanSuggestion
        +accept(suggestion) int
        +reroll(slot) MealPlanSlot
        +toShoppingList(plan, User) int
        +copyToNextWeek(plan, Family, User) MealPlan
    }

    class SuggestionEngine {
        +GEWICHTE array
        +suggest(plan, string, array) array
        +reroll(slot, array) array
        -bewerte(recipe, date, ...) float
        -deckung(recipe, vorrat) float
        -ablaufdruck(recipe) float
    }

    MealPlan "1" *-- "*" MealPlanSlot
    MealPlan "1" o-- "*" MealPlanSuggestion
    MealPlanSlot "*" --> "0..1" Recipe
    MealPlanManager ..> SuggestionEngine
    MealPlanManager ..> RecipeToList : F-09
    SuggestionEngine ..> PantryItem : Vorratsdeckung
```

---

## 5 · Ablaufsicht

### 5.1 Artikel aufschreiben (C-01, C-17)

```mermaid
sequenceDiagram
    actor N as Nutzer
    participant C as ShoppingListController
    participant M as ShoppingListManager
    participant P as ItemTextParser
    participant K as ProductCategorizer
    participant DB as MariaDB

    N->>C: POST /einkaufsliste/eintraege<br/>"2 l Milch"
    C->>M: addOrMerge(liste, eingabe)
    M->>P: parse("2 l Milch")
    P-->>M: ParsedItem(name: Milch, amount: 2, unit: l)
    M->>DB: passender offener Eintrag?
    alt Artikel steht schon offen auf der Liste
        DB-->>M: Eintrag (1 l Milch)
        M->>DB: UPDATE amount = 3
        Note over M,DB: Nur bei gleicher Einheit --<br/>sonst wandert die Angabe in die Notiz
    else neu
        M->>K: warengruppe("Milch")
        K-->>M: MOLKEREI
        M->>DB: INSERT shopping_list_items
    end
    M-->>C: ShoppingListItem
    C-->>N: Redirect mit Bestätigung
```

### 5.2 Abgleich zwischen Geräten mit Versionskonflikt (C-07, C-08)

```mermaid
sequenceDiagram
    participant G1 as Gerät A
    participant G2 as Gerät B
    participant S as ShoppingListSyncController
    participant M as ShoppingListManager

    G1->>S: GET /api/einkaufsliste/stand
    S-->>G1: {version: 7, items: [...]}
    G2->>S: POST /api/einkaufsliste/stand<br/>{version: 7, mutations: [...]}
    S->>M: addFromClient(...)
    M-->>S: ok
    S-->>G2: {version: 8}

    Note over G1: Gerät A war im Funkloch<br/>und kennt nur Version 7

    G1->>S: POST {version: 7, mutations: [...]}
    S-->>G1: 409 Conflict + vollständiger Stand (Version 8)
    Note over G1,S: Der Server überschreibt nichts.<br/>Das Gerät führt zusammen und sendet erneut.
    G1->>S: POST {version: 8, mutations: [...]}
    S-->>G1: {version: 9}
```

Jede Änderung trägt eine `client_uuid`. Kommt sie zweimal an — der Normalfall nach einem Verbindungsabbruch —, erkennt der Server das und tut nichts. Zusammengeführte Einträge bleiben als weich gelöschte Zeile stehen („Grabstein"), damit ein später eintreffender Wiederholversuch nicht doch noch eine zweite Zeile erzeugt.

### 5.3 Einkauf abschließen (C-11 und Zusatzfunktion)

```mermaid
sequenceDiagram
    actor N as Nutzer
    participant C as ShoppingListController
    participant A as ListArchiver
    participant T as TemplateApplier
    participant DB as MariaDB

    N->>C: POST /einkaufsliste/archivieren
    C->>A: archive(liste, offeneUebernehmen: true)
    A->>DB: UPDATE status = archived, archived_at = jetzt
    A->>DB: INSERT neue aktive Liste
    A->>DB: kopiere nicht abgehakte Einträge
    Note over A,DB: Kopieren, nicht verschieben --<br/>das Archiv soll zeigen, was gefehlt hat
    A->>T: applyDefault(neueListe)
    T->>DB: Standardvorlage zusammenführen
    Note over T: Reihenfolge ist entscheidend:<br/>erst die offenen Artikel, dann die Vorlage --<br/>sonst gäbe es doppelte Zeilen
    A-->>C: neue Liste
    C-->>N: Redirect auf /einkaufsliste
```

### 5.4 Wochenplan auf die Einkaufsliste (F-09)

```mermaid
sequenceDiagram
    actor N as Nutzer
    participant C as MealPlanController
    participant P as MealPlanManager
    participant R as RecipeToList
    participant M as ShoppingListManager

    N->>C: POST /wochenplan/{plan}/auf-die-liste
    C->>P: toShoppingList(plan, user)
    P->>M: current(user)
    M-->>P: aktive Liste
    loop je belegtem, nicht gekochtem Platz
        P->>R: transfer(rezept, liste, portionen)
        R->>R: skalieren, Vorratszutaten abwählen
        R->>M: addOrMerge(zutat)
        Note over M: Zwiebeln aus zwei Rezepten<br/>ergeben einen Eintrag
    end
    P->>P: plan.list_id merken
    C-->>N: Redirect auf /einkaufsliste
```

### 5.5 Bestandsaufnahme des Grundvorrats (E-09)

```mermaid
sequenceDiagram
    actor N as Nutzer
    participant C as PantryInventoryController
    participant P as PantryInventory
    participant M as ShoppingListManager

    N->>C: GET /einkaufsliste/inventur
    C-->>N: je Grundvorratsartikel Sollmenge und ein leeres Feld
    N->>C: POST mit den gezählten Mengen
    C->>P: uebernehmen(liste, zaehlung)
    loop je Artikel mit Eintrag
        P->>P: Soll − Ist
        alt fehlt etwas
            P->>M: addOrMerge(Fehlmenge)
        else nichts oder nicht gezählt
            Note over P: Ein leeres Feld heißt "nicht gezählt"<br/>und nicht "nichts da".
        end
    end
    C-->>N: Redirect auf die Einkaufsliste
```

---

## 6 · Zustandsdiagramme

### 6.1 Einkaufsliste

```mermaid
stateDiagram-v2
    [*] --> active : erste Anfrage der Familie
    active --> archived : Einkauf abschließen
    archived --> [*]

    note right of active
        Höchstens eine je Familie.
        Erzwungen von der Datenbank über
        aktiv_marker + UNIQUE.
    end note

    note right of archived
        Bleibt lesbar und lässt sich in die
        laufende Liste nachladen (C-14).
    end note
```

### 6.2 Platz im Wochenplan

```mermaid
stateDiagram-v2
    [*] --> leer : Plan anlegen — alle 7 Tage auf einmal
    leer --> belegt : Rezept oder Freitext setzen
    leer --> belegt : Vorschlag annehmen
    belegt --> belegt : neu würfeln
    belegt --> gekocht : als gekocht markieren
    gekocht --> belegt : Haken zurücknehmen
    belegt --> leer : leeren

    state gesperrt <<choice>>
    leer --> gesperrt : sperren
    note right of gesperrt
        Die Sperre gilt dem Platz, nicht dem Inhalt:
        Der Generator geht an gesperrten Plätzen
        vorbei, egal ob sie belegt sind.
    end note
```

### 6.3 Einladung

```mermaid
stateDiagram-v2
    [*] --> offen : erstellt, mit Ablaufdatum
    offen --> eingelöst : angenommen
    offen --> zurückgezogen : widerrufen
    offen --> abgelaufen : expires_at überschritten
    eingelöst --> offen : wenn max_uses noch nicht erreicht
    zurückgezogen --> [*]
    abgelaufen --> [*]
```

---

## 7 · Querschnittsthemen

### 7.1 Mandantentrennung — drei Ebenen

Die eine Eigenschaft, die nicht kaputtgehen darf. Deshalb hängt sie nicht an einer einzelnen Vorkehrung:

```mermaid
graph TB
    R["Anfrage eines Nutzers"] --> E1

    subgraph E1["Ebene 1 — Anwendung"]
        G["FamilyScope hängt<br/>where family_id = ?<br/>an jede Abfrage"]
    end

    subgraph E15["Ebene 1½ — Nachladen"]
        N["IDs aus Formularen werden<br/>durch den Scope neu geladen.<br/>Eine fremde ID trifft ins Leere."]
    end

    subgraph E2["Ebene 2 — Datenbank"]
        F["Verbund-Fremdschlüssel<br/>(id, family_id).<br/>Ein Eintrag kann nicht in der<br/>Liste einer fremden Familie hängen."]
    end

    E1 --> E15 --> E2 --> DB[("Daten")]
```

Ebene 1 fängt Vergesslichkeit ab, Ebene 1½ fängt manipulierte Formulare ab, Ebene 2 fängt Fehler in Ebene 1 und 1½ ab. Jede Ebene für sich wäre eine vertretbare Lösung; erst zusammen sind sie eine, bei der ein einzelner Fehler keine fremden Daten sichtbar macht. Sechs Tests prüfen ausdrücklich, dass fremde Zugriffe in 404 enden.

### 7.2 Was die Datenbank selbst durchsetzt

| Zusage | Umsetzung |
|---|---|
| Eine aktive Einkaufsliste je Familie | `aktiv_marker` (generiert) + UNIQUE |
| Kein Eintrag in fremder Liste | Verbund-Fremdschlüssel `(list_id, family_id)` |

Der Grund ist immer derselbe: Zwei gleichzeitige Anfragen können eine Prüfung im Code unterlaufen — „gibt es schon eine aktive Liste?" ist zwischen Frage und Antwort nicht mehr wahr. Ein UNIQUE ist es.

### 7.3 Offlinebetrieb

```mermaid
graph LR
    B["Browser"] --> SW["Service Worker<br/>public/sw.js"]
    SW -->|"Seitenaufruf"| Netz{"Netz da?"}
    Netz -->|ja| Server["Server"]
    Netz -->|nein| Cache["zuletzt geladene<br/>Einkaufsansicht"]
    Cache -->|"nichts da"| Off["offline.html"]
    Server -->|"200, keine Weiterleitung"| Speichern["in den Cache"]
```

Zwei Entscheidungen stecken darin:

- **Erst Netz, dann Speicher.** Andersherum wäre schneller, aber eine Einkaufsliste von gestern ist schlimmer als eine, die eine Sekunde braucht.
- **Weiterleitungen kommen nicht in den Cache.** Läge die Anmeldeseite einmal unter der Adresse der Einkaufsliste, führte jeder weitere Aufruf offline dorthin — eine Falle, aus der man ohne Netz nicht mehr herauskommt.

Abhaken braucht weiterhin Empfang. Das Warteschlangen-Verfahren für Offline-Änderungen gehört zum Sync-Frontend (Abschnitt 5.2), dessen Endpunkte seit Schritt 4 bereitstehen.

### 7.4 Oberfläche und Geräte

Die Anwendung unterscheidet drei Seitenarten, und sie sagt es dem Nutzer:

| Art | Beispiele | Kennzeichnung |
|---|---|---|
| **PC** | Artikelkatalog, Warengruppen, Märkte, Vorlagen | grauer Streifen, Marke „🖥 Für den PC" |
| **PC + Tablet** | Einkaufsliste, Wochenplan, Rezepte, Vorrat | grüner Streifen, Marke „▤ PC und Tablet" |
| **Handy** | Einkaufsansicht `/einkaufsliste/einkaufen` | eigenes Layout, Marke „▯ Fürs Handy" |

Quelle ist `App\Core\Support\Navigation` — dieselbe Klasse baut das zweistufige Menü und die Kennzeichnung. Zwei getrennte Listen wären nach der zweiten Änderung auseinandergelaufen.

Der erklärende Satz („Diese Seite wird am PC gepflegt …") erscheint per Media Query **nur dort, wo er gebraucht wird**: auf einem Bildschirm, für den die Seite nicht gebaut ist. Am PC wäre der Hinweis „für den PC" nur Lärm.

### 7.5 Kein JavaScript-Framework

Die Anwendung kommt ohne Vue, React oder Livewire aus. Formulare sind Formulare, Antworten sind Weiterleitungen. Das ist keine Nostalgie, sondern folgt aus dem Einsatzort: Im Supermarkt zählt die erste Anzeige, und die ist ohne 200 kB Framework schneller da. Der einzige nennenswerte Skriptcode ist der Service Worker.

### 7.6 Sicherheit

| Thema | Umsetzung |
|---|---|
| Passwörter | bcrypt, Klartext existiert nirgends |
| Sitzungen | in der Datenbank, damit Abmelden sofort überall gilt |
| CSRF | Laravel-Token auf jedem Formular |
| Brute Force | `login_attempts` + Sperre nach mehreren Fehlversuchen |
| Einladungen | nur der Token-Hash liegt in der Datenbank |
| Rechte | Strukturdaten nur Owner und Admin (`requireAdmin`), Alltagsdaten jedes Mitglied |
| IDs nach außen | ULIDs statt laufender Nummern |

---

## 8 · Verteilung

```mermaid
graph TB
    subgraph Geraete["Endgeräte"]
        PC["Browser am PC"]
        Tab["Tablet"]
        Handy["Handy — installiert als PWA"]
    end

    subgraph Server["Server beim Hoster"]
        AP["Apache 2.4<br/>DocumentRoot: public/"]
        PHP["PHP 8.4-FPM<br/>Laravel 13"]
        DB[("MariaDB 10.11<br/>Schema wocheneinkauf")]
        FS["storage/<br/>Logs, Cache, Sitzungen"]
    end

    PC -->|HTTPS| AP
    Tab -->|HTTPS| AP
    Handy -->|HTTPS| AP
    AP --> PHP
    PHP --> DB
    PHP --> FS
```

**Betriebsüberwachung:** `GET /health` antwortet mit `200 ok` oder `503 degraded` und meldet Datenbankverbindung sowie Migrationsstand. Offene Migrationen bedeuten: Der Code ist neuer als das Schema — ein Deployment, das nur halb durchgelaufen ist.

**Verzeichnisfalle beim Betrieb mit Apache:** Die App-Symbole liegen unter `public/symbole/` und nicht unter `public/icons/`. Apache bringt in `mods-enabled/alias.conf` ein `Alias /icons/` auf sein eigenes Symbolverzeichnis mit; der Alias greift vor dem Dateisystem, die Symbole wären also unsichtbar, ohne dass am Projekt etwas falsch wäre.

---

## 9 · Getroffene Entscheidungen

| # | Entscheidung | Warum so | Preis |
|---|---|---|---|
| 1 | Laravel statt eigenem PHP | Mandanten, Rechte, Migrationen, Tests sind gelöste Probleme | Rahmenwerk muss gepflegt werden |
| 2 | Mandantentrennung dreifach | Ein einzelner Fehler darf keine fremden Daten zeigen | Mehr Fremdschlüssel, längere Migrationen |
| 3 | Generierte Spalten für Invarianten | Nebenläufigkeit lässt sich im Code nicht sicher prüfen | An MariaDB/MySQL gebunden |
| 4 | Weiches Löschen bei Listeneinträgen | Der Abgleich muss Löschungen mitbekommen | Abfragen müssen `deleted_at` beachten |
| 5 | Regelbasierter Vorschlagsgenerator statt KI | Millisekunden, testbar, erklärbar | Weniger überraschend |
| 6 | Kein JS-Framework | Erste Anzeige im Funkloch | Kein optimistisches Aktualisieren im Frontend |
| 7 | Eigene Ansicht fürs Handy statt responsiver Liste | Im Laden zählt nur Abhaken | Zwei Ansichten zu pflegen |
| 8 | Artikelkatalog als gemeinsame Klammer | Liste, Rezept und Vorrat müssen dasselbe meinen | Mehr Verweise beim Anlegen |
| 9 | Deutsche Fachbegriffe im Code | Die Fachsprache der Nutzer ist deutsch | Gemischtsprachiger Code |

---

## 10 · Qualität

| Kennzahl | Wert |
|---|---|
| Tests | 301 (Feature und Unit) |
| Zusicherungen | rund 1.240 |
| Testdatenbank | echtes MariaDB, kein SQLite — generierte Spalten und Verbundschlüssel gäbe es dort nicht |
| Codeformat | Laravel Pint, Standard `laravel` |
| Laufzeit der Testreihe | rund 140 Sekunden |

Getestet wird ausdrücklich auch das, was **nicht** passieren darf: fremde Datensätze enden in 404, die zweite aktive Liste scheitert an der Datenbank, die Bestandsaufnahme rührt den Vorrat nicht an, ein ausgeschlossenes Rezept taucht in keinem Vorschlag auf.

```bash
php artisan test          # ganze Reihe
./vendor/bin/pint         # Format
php scripts/db-doku.php   # Datenbankdokumentation neu erzeugen
```

---

## 11 · Was noch offen ist

| Thema | Stand |
|---|---|
| Import aus `data.js` (Schritt 5) | wartet auf die Datei |
| Offline-Warteschlange fürs Abhaken | Endpunkte stehen, Frontend fehlt |
| Barcode-Erfassung im Vorrat (E-12) | KANN, nicht gebaut |
| Rezept-Import aus URL (D-09) | KANN, nicht gebaut |
| AV-Vertrag, Löschkonzept, juristische Prüfung | außerhalb des Codes |
