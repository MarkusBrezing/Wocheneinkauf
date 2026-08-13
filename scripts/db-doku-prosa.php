<?php

/**
 * Die Prosa zur Datenbankdokumentation.
 *
 * Was die Datenbank selbst weiß -- Typen, Schluessel, Indizes -- liest der
 * Generator aus information_schema. Hier steht nur das, was sie nicht weiss:
 * wozu eine Tabelle da ist und warum eine Spalte so aussieht, wie sie
 * aussieht.
 */
return [
    'gruppen' => [
        'Mandant, Konten und Zugang' => [
            'einleitung' => 'Die Familie ist der Mandant. Jede fachliche Zeile der Anwendung trägt ihre `family_id` -- ohne Ausnahme. Nutzer hängen nicht an einer Familie, sondern an beliebig vielen; welche gerade gilt, steht in `users.current_family_id`.',
            'tabellen' => ['families', 'users', 'family_user', 'family_settings', 'invitations', 'login_attempts', 'activity_log'],
        ],
        'Stammdaten' => [
            'einleitung' => 'Warengruppen gliedern alles, Märkte bestimmen die Reihenfolge im Laden, und der Artikelkatalog ist die gemeinsame Klammer von Einkaufsliste, Rezept und Vorrat.',
            'tabellen' => ['product_groups', 'product_group_rules', 'products', 'markets', 'market_sections', 'market_section_groups', 'market_product'],
        ],
        'Einkaufsliste' => [
            'einleitung' => 'Je Familie gibt es höchstens **eine** aktive Liste -- das erzwingt die Datenbank und nicht die Anwendung. Alles Weitere ist Archiv.',
            'tabellen' => ['shopping_lists', 'shopping_list_items', 'list_templates', 'list_template_items'],
        ],
        'Rezepte' => [
            'einleitung' => 'Rezepte mit Zutaten und Schlagworten. Die Zutat kennt drei Namen: den Originaltext, den Einkaufsnamen und den Sammelbegriff -- daran hängt die Übernahme auf die Einkaufsliste.',
            'tabellen' => ['recipes', 'recipe_ingredients', 'tags', 'recipe_tag'],
        ],
        'Vorrat' => [
            'einleitung' => 'Der Grundvorrat am Artikel ist die *Absicht* (`products.pantry_target_amount`), `pantry_items` ist die *Wirklichkeit*: was tatsächlich wo liegt. Jede Änderung daran ist eine Buchung. Gezählt wird über die Bestandsaufnahme, die ohne eigene Tabellen auskommt -- ihr Ergebnis ist die Einkaufsliste.',
            'tabellen' => ['pantry_items', 'pantry_movements'],
        ],
        'Wochenplan' => [
            'einleitung' => 'Ein Plan je Familie und Woche, sieben Tage als feste Plätze. Leere Plätze sind Datensätze und keine Lücken -- nur so lassen sie sich sperren und einzeln neu würfeln.',
            'tabellen' => ['meal_plans', 'meal_plan_slots', 'meal_plan_suggestions'],
        ],
        'Technische Tabellen des Frameworks' => [
            'einleitung' => 'Von Laravel mitgebracht und fachlich ohne Bedeutung. Sie tragen bewusst **keine** `family_id`: Sitzungen, Cache und Warteschlangen sind Sache des Servers, nicht der Familie.',
            'tabellen' => ['sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'],
        ],
    ],

    'tabellen' => [
        // ── Mandant, Konten und Zugang ───────────────────────────────────
        'families' => [
            'zweck' => 'Der Mandant. Eine Familie entsteht bei der Registrierung mit; sie wird nie „nebenbei" angelegt.',
            'spalten' => [
                'public_id' => 'ULID für URLs. Die laufende `id` bleibt intern -- aus ihr ließe sich ablesen, wie viele Familien es gibt.',
                'week_starts_on' => 'Erster Tag der Planwoche, 1 = Montag … 7 = Sonntag. Voreingestellt ist **6 (Samstag)**, weil an dem Tag eingekauft wird. Der Wochenplan rechnet damit.',
                'status' => 'Gesperrte Familien bleiben lesbar, aber unveränderlich.',
                'deleted_at' => 'Weiches Löschen: Eine gelöschte Familie ist für die Anwendung weg, für die Wiederherstellung noch da.',
            ],
            'besonderheiten' => [
                '`locale` und `timezone` stehen hier und nicht am Nutzer, weil eine Einkaufsliste nur in *einer* Zeitzone Sinn ergibt.',
            ],
        ],
        'users' => [
            'zweck' => 'Konten. Ein Konto kann mehreren Familien angehören -- Patchwork ist der Normalfall, nicht die Ausnahme.',
            'spalten' => [
                'current_family_id' => 'In welcher Familie der Nutzer gerade arbeitet. Der Global Scope liest genau diesen Wert; ohne ihn sieht die Anwendung nichts.',
                'password' => 'Nur der Hash (bcrypt). Das Klartextpasswort existiert an keiner Stelle des Systems.',
                'email_verified_at' => 'Ohne Bestätigung ist keine Seite erreichbar.',
            ],
            'besonderheiten' => [
                '`email` ist global eindeutig, nicht je Familie -- ein Mensch, ein Konto.',
            ],
        ],
        'family_user' => [
            'zweck' => 'Die Mitgliedschaft samt Rolle. Verbundschlüssel aus `family_id` und `user_id`, keine eigene ID.',
            'spalten' => [
                'role' => '`owner` darf alles einschließlich Löschen der Familie; `admin` pflegt Struktur (Warengruppen, Märkte, Regalbereiche); `member` pflegt Alltagsdaten; `child` liest und hakt ab.',
            ],
            'besonderheiten' => [
                'Der letzte Owner einer Familie kann nicht entfernt werden -- sonst gäbe es Daten ohne Zuständige.',
            ],
        ],
        'family_settings' => [
            'zweck' => 'Freie Einstellungen je Familie als Schlüssel-Wert-Paare. Für alles, was keine eigene Spalte verdient.',
            'spalten' => [
                'setting_key' => 'Heißt nicht `key`, weil das in MySQL ein reserviertes Wort ist.',
            ],
        ],
        'invitations' => [
            'zweck' => 'Einladungen in eine Familie. Der Link funktioniert auch für Gäste ohne Konto -- wer eingeladen wird, hat in der Regel noch keines.',
            'spalten' => [
                'token_hash' => 'Nur der Hash liegt in der Datenbank. Wer sie liest, kann damit keine Einladung einlösen.',
                'max_uses' => 'Erlaubt eine Einladung für mehrere Personen, etwa einen Familien-Link.',
                'revoked_at' => 'Zurückgezogene Einladungen bleiben stehen -- die Frage „wer hat wen eingeladen" muss beantwortbar bleiben.',
            ],
        ],
        'login_attempts' => [
            'zweck' => 'Anmeldeversuche für die Sperre nach zu vielen Fehlschlägen.',
            'spalten' => [
                'ip_address' => '`varbinary(16)`, damit auch IPv6 hineinpasst -- gespeichert wird die gepackte Form.',
            ],
            'besonderheiten' => [
                'Die Tabelle wird regelmäßig aufgeräumt; sie ist eine Sperrliste, kein Protokoll.',
            ],
        ],
        'activity_log' => [
            'zweck' => 'Was in einer Familie geschehen ist. Beantwortet im Alltag vor allem eine Frage: „Wer hat das gelöscht?"',
            'spalten' => [
                'subject_type' => 'Kurzname statt vollem Klassennamen -- ein Protokoll soll eine Umbenennung im Code überleben.',
                'meta' => 'JSON mit dem, was zum Vorgang gehört. Bewusst formlos: Ein Protokoll, das ein Schema erzwingt, wird beim nächsten Feature unvollständig.',
            ],
        ],

        // ── Stammdaten ───────────────────────────────────────────────────
        'product_groups' => [
            'zweck' => 'Warengruppen -- die Gliederung von Liste, Katalog und Regalbereichen. Bei der Registrierung entstehen 15 Stück als Startdatensatz.',
            'spalten' => [
                'code' => 'Sprechender Schlüssel (`OBST_GEMUESE`). Regeln und Startdaten verweisen darauf, nicht auf die ID.',
                'sort' => 'Reihenfolge ohne gewählten Markt.',
            ],
        ],
        'product_group_rules' => [
            'zweck' => 'Stichwortregeln der automatischen Einsortierung (B-07): „milch" → Molkereiprodukte. Rund 90 Regeln je Familie im Startdatensatz.',
            'spalten' => [
                'pattern' => 'Suchbegriff oder regulärer Ausdruck, je nach `is_regex`.',
                'priority' => 'Kleinste Zahl gewinnt. Getränkeregeln stehen vor den Obstregeln, sonst landet Apfelsaft im Obst.',
            ],
            'besonderheiten' => [
                'Die Regeln gehören der Familie: Wer „Bowle" beim Alkohol haben will, ändert das für sich und nicht für alle.',
            ],
        ],
        'products' => [
            'zweck' => 'Der Artikelkatalog. Er ist die gemeinsame Klammer: Ein Listeneintrag, eine Rezeptzutat und eine Vorratsposition zeigen auf denselben Artikel.',
            'spalten' => [
                'name_normalized' => 'Kleingeschrieben, ohne Umlaute und Sonderzeichen. Darüber laufen Suche und Zusammenführung -- „Äpfel" und „aepfel" sind derselbe Artikel.',
                'is_pantry_staple' => 'Gehört zum Grundvorrat: Was immer im Haus sein soll.',
                'pantry_target_amount' => 'Sollmenge des Grundvorrats. Kam bei der Zusammenführung von „Fixartikel" und „Grundvorrat" aus der abgeschafften Tabelle `standing_items` hierher.',
                'deleted_at' => 'Weiches Löschen, weil alte Listen und Rezepte weiter auf den Artikel zeigen.',
            ],
        ],
        'markets' => [
            'zweck' => 'Die Märkte der Familie. Einer ist Standard und wird für neue Listen vorgeschlagen.',
            'spalten' => [
                'is_default' => 'Höchstens einer je Familie -- die Anwendung setzt den bisherigen zurück, wenn ein neuer bestimmt wird.',
                'is_active' => 'Ein stillgelegter Markt verschwindet aus der Auswahl, bleibt aber an den archivierten Listen erhalten.',
            ],
        ],
        'market_sections' => [
            'zweck' => 'Regalbereiche in **Laufreihenfolge**. Sie sind der Grund, warum die Liste im Laden von oben nach unten abgearbeitet werden kann.',
            'spalten' => [
                'sort' => 'Die Reihenfolge, in der man durch den Laden geht -- nicht die alphabetische.',
            ],
        ],
        'market_section_groups' => [
            'zweck' => 'Welche Warengruppe in welchem Regalbereich liegt. Dieselbe Gruppe darf in mehreren Märkten unterschiedlich einsortiert sein.',
            'besonderheiten' => [
                'Reine Zuordnungstabelle mit Verbundschlüssel, keine eigene ID.',
            ],
        ],
        'market_product' => [
            'zweck' => 'Wo es einen Artikel überhaupt gibt (Misopaste nur im Asialaden).',
            'besonderheiten' => [
                '**Keine Zeile heißt: überall erhältlich.** Das ist der häufige Fall, und der soll keine Pflege kosten. Erst wer Zeilen anlegt, schränkt ein.',
                'Artikel, die es im gewählten Markt nicht gibt, sammelt die Liste am Ende unter „Gibt es hier nicht".',
            ],
        ],

        // ── Einkaufsliste ────────────────────────────────────────────────
        'shopping_lists' => [
            'zweck' => 'Eine Liste je Einkauf. Die aktive ist die, auf die geschrieben wird; alles andere ist Archiv.',
            'spalten' => [
                'version' => 'Zähler für den Abgleich zwischen Geräten. Wer mit einer veralteten Version schreibt, bekommt 409 und den aktuellen Stand -- statt fremde Änderungen zu überschreiben.',
                'aktiv_marker' => 'Generierte Spalte: 1 bei `status = active`, sonst NULL. Zusammen mit dem UNIQUE über (`family_id`, `aktiv_marker`) erzwingt sie **höchstens eine aktive Liste je Familie**. NULL-Werte zählen im UNIQUE nicht mit -- deshalb dürfen beliebig viele Listen archiviert sein.',
                'archived_at' => 'Zeitpunkt des Einkaufsabschlusses.',
            ],
            'besonderheiten' => [
                'Die Regel „eine aktive Liste" steht in der Datenbank und nicht nur im Code. Zwei gleichzeitige Anfragen können sie so nicht aushebeln.',
            ],
        ],
        'shopping_list_items' => [
            'zweck' => 'Die Einträge der Liste -- das Herzstück der Anwendung.',
            'spalten' => [
                'client_uuid' => 'Vom Gerät vergeben. Damit ist dieselbe Änderung beliebig oft sendbar, ohne doppelt anzukommen; nach einem Verbindungsabbruch ist genau das der Normalfall.',
                'raw_name' => 'Der Name, wie er auf der Liste steht.',
                'amount' => 'Zahlenwert für das Zusammenrechnen.',
                'amount_raw' => 'Die Eingabe im Original („1-2"). Sie bleibt erhalten, weil sich nicht jede Mengenangabe in eine Zahl pressen lässt.',
                'source' => 'Woher der Eintrag kommt: von Hand, aus einem Rezept, aus dem Vorrat, aus einer Vorlage oder aus dem Wochenplan.',
                'source_ref_id' => 'Der Verweis dazu -- Rezept-ID, Vorratsposition, Inventurlauf.',
                'deleted_at' => 'Weiches Löschen, damit der Abgleich Löschungen überhaupt mitbekommt. Eine verschwundene Zeile wäre für das andere Gerät nicht von „noch nie gesehen" zu unterscheiden.',
            ],
            'besonderheiten' => [
                'Zusammengeführte Einträge bleiben als gelöschte Zeile stehen („Grabstein"), damit ein wiederholtes Senden nicht doch noch eine zweite Zeile erzeugt.',
                'Der Verbundschlüssel (`list_id`, `family_id`) verhindert, dass ein Eintrag in der Liste einer anderen Familie landet -- selbst bei einem Fehler in der Anwendung.',
            ],
        ],
        'list_templates' => [
            'zweck' => 'Vorlagen für neue Listen: „Wocheneinkauf", „Grillabend". Höchstens eine ist Standard.',
        ],
        'list_template_items' => [
            'zweck' => 'Die Positionen einer Vorlage. Aufbau wie ein Listeneintrag, ohne Haken und ohne Abgleich.',
        ],

        // ── Rezepte ──────────────────────────────────────────────────────
        'recipes' => [
            'zweck' => 'Rezepte mit Zubereitung, Nährwerten und Aufwand.',
            'spalten' => [
                'slug' => 'Je Familie eindeutig. Bei Namensgleichheit hängt das Model eine Zahl an, statt den UNIQUE-Fehler bis in die Oberfläche durchschlagen zu lassen.',
                'effort' => 'Steuert den Wochenplan: `quick` werktags, `elaborate` am Wochenende.',
                'plant_points' => 'Wie viele verschiedene Pflanzen im Gericht stecken. Fließt in die Bewertung des Vorschlagsgenerators ein.',
                'exclude_from_suggestions' => '„Nie vorschlagen" (F-15). Von Hand einsetzen bleibt erlaubt -- der Ausschluss gilt nur dem Generator.',
                'nutrition' => 'JSON, weil Nährwertangaben je Quelle anders aussehen.',
            ],
        ],
        'recipe_ingredients' => [
            'zweck' => 'Die Zutaten. Drei Namensfelder, und jedes hat seinen Grund.',
            'spalten' => [
                'raw_name' => 'Der Originaltext aus dem Rezept.',
                'buy_label' => 'Was auf der Einkaufsliste stehen soll, wenn es abweicht: Zutat „Olivenöl", auf der Liste „Olivenöl, gutes fürs Dressing".',
                'merge_key' => 'Klammert Zutaten, die aus mehreren Rezepten zu einem Listeneintrag zusammenfallen sollen.',
                'is_pantry' => 'Vorratszutat -- in der Übernahmemaske vorab **abgewählt**. Was im Schrank steht, muss nicht mit.',
                'is_optional' => '„Nach Belieben". Zählt bei der Vorratsdeckung des Wochenplans nicht mit.',
                'amount' => 'Darf leer sein: „etwas Petersilie" bleibt beim Skalieren „etwas Petersilie" und wird nicht zu „1,5 etwas".',
            ],
        ],
        'tags' => [
            'zweck' => 'Schlagworte mit Art. Der Typ ist nicht Kosmetik: Der Vorschlagsgenerator liest `protein` und `cuisine` für die Abwechslung.',
        ],
        'recipe_tag' => [
            'zweck' => 'Zuordnung Rezept ↔ Schlagwort.',
        ],

        // ── Vorrat und Inventur ──────────────────────────────────────────
        'pantry_items' => [
            'zweck' => 'Was tatsächlich im Haus ist. Derselbe Artikel darf an mehreren Orten liegen -- Nudeln in der Speisekammer *und* im Keller.',
            'spalten' => [
                'location' => 'Der Ort. Teil des Eindeutigkeitsschlüssels, deshalb mit Vorgabe „Vorrat" statt NULL.',
                'target_qty' => 'Sollmenge: so viel soll da sein.',
                'min_qty' => 'Meldebestand: darunter wird es knapp.',
                'current_qty' => 'Der laufende Bestand. Wird ausschließlich über Buchungen verändert und nie unter null geführt.',
                'best_before' => 'Haltbarkeitsdatum. Der Wochenplan zieht Gerichte vor, deren Zutaten bald ablaufen.',
            ],
        ],
        'pantry_movements' => [
            'zweck' => 'Jede Bestandsänderung als eigene Zeile. Kostet Platz und beantwortet dafür die Frage, wo die Vorräte geblieben sind.',
            'spalten' => [
                'qty_delta' => 'Die Änderung, nicht der neue Stand. Gebucht wird, was **tatsächlich** passiert ist: Wer 3 kg verbraucht, aber nur 2 kg im System hatte, erzeugt −2 und nicht −3. Sonst summieren sich die Bewegungen nicht mehr zum Bestand.',
                'reason' => 'Zugang, Verbrauch, Korrektur, Verfall. Der Wert `inventory` stammt aus dem entfernten Inventurlauf und wird nicht mehr vergeben -- er bleibt im Aufzählungstyp, weil alte Buchungen ihn tragen könnten.',
                'ref_type' => 'Auslöser einer Buchung, sofern sie nicht von Hand kam.',
            ],
        ],

        // ── Wochenplan ───────────────────────────────────────────────────
        'meal_plans' => [
            'zweck' => 'Ein Plan je Familie und Kalenderwoche.',
            'spalten' => [
                'week_start' => 'Erster Tag der Planwoche. Welcher das ist, sagt `families.week_starts_on`.',
                'list_id' => 'Die Einkaufsliste, in die der Plan übernommen wurde.',
            ],
        ],
        'meal_plan_slots' => [
            'zweck' => 'Ein Platz: ein Tag, eine Mahlzeit. Alle sieben entstehen auf einmal, auch die leeren.',
            'spalten' => [
                'recipe_id' => 'Einfacher Fremdschlüssel statt Verbund: MySQL lässt `ON DELETE SET NULL` nicht zu, wenn eine Spalte des Verbunds NOT NULL ist. Die Mandantensicherheit kommt hier über die Anwendung -- die ID wird durch den Global Scope nachgeladen, eine fremde trifft ins Leere.',
                'custom_text' => '„Reste", „Essen gehen". Schließt ein Rezept aus und umgekehrt -- beides gleichzeitig wäre widersprüchlich.',
                'is_locked' => 'Gesperrte Plätze rührt der Generator nicht an. Die Sperre gilt dem **Platz**, nicht dem Inhalt: Wer einen gesperrten Platz leert, hat weiterhin einen gesperrten, jetzt leeren Platz.',
                'origin' => 'Von Hand gesetzt oder vom Generator vorgeschlagen.',
            ],
        ],
        'meal_plan_suggestions' => [
            'zweck' => 'Protokoll der Vorschlagsläufe. Der Grund, warum „nochmal würfeln" nicht denselben Vorschlag liefert.',
            'spalten' => [
                'result' => 'JSON: Platz, Rezept und Punktzahl je Vorschlag.',
                'accepted_at' => 'Leer heißt verworfen -- und verworfene Rezepte sind beim nächsten Wurf ausgeschlossen.',
            ],
        ],

        // ── Framework ────────────────────────────────────────────────────
        'sessions' => ['zweck' => 'Angemeldete Sitzungen. Datenbankgestützt, damit ein Abmelden sofort überall gilt.'],
        'password_reset_tokens' => ['zweck' => 'Tokens zum Zurücksetzen des Passworts, mit Ablauf.'],
        'cache' => ['zweck' => 'Zwischenspeicher des Frameworks.'],
        'cache_locks' => ['zweck' => 'Sperren für den Zwischenspeicher.'],
        'jobs' => ['zweck' => 'Warteschlange für Hintergrundarbeiten, derzeit vor allem E-Mails.'],
        'job_batches' => ['zweck' => 'Zusammengefasste Hintergrundarbeiten.'],
        'failed_jobs' => ['zweck' => 'Fehlgeschlagene Hintergrundarbeiten samt Fehlermeldung.'],
        'migrations' => ['zweck' => 'Welche Schemaänderungen gelaufen sind. Der Health-Check liest sie, um ein halb ausgeliefertes Deployment zu erkennen.'],
    ],
];
