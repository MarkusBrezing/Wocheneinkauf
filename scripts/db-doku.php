<?php

/**
 * Erzeugt docs/Wocheneinkauf-Datenbank.md.
 *
 * Struktur, Typen, Schluessel und Indizes kommen aus information_schema -- so
 * kann die Dokumentation nicht von der Wirklichkeit abweichen. Die Prosa
 * kommt aus db-prosa.php.
 *
 * Aufruf aus dem Projektverzeichnis:  php <pfad>/db-doku.php
 */
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$prosa = require __DIR__.'/db-doku-prosa.php';
$schema = config('database.connections.mysql.database');

// ── Schema einlesen ──────────────────────────────────────────────────────
$spalten = [];
foreach (DB::select(
    'SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_DEFAULT d,
            EXTRA e, GENERATION_EXPRESSION g
     FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
    [$schema]
) as $z) {
    $spalten[$z->t][] = $z;
}

$indizes = [];
foreach (DB::select(
    'SELECT TABLE_NAME t, INDEX_NAME i, NON_UNIQUE nu, SEQ_IN_INDEX s, COLUMN_NAME c
     FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
    [$schema]
) as $z) {
    $indizes[$z->t][$z->i]['unique'] = ! $z->nu;
    $indizes[$z->t][$z->i]['spalten'][] = $z->c;
}

$fremd = [];
foreach (DB::select(
    'SELECT k.TABLE_NAME t, k.CONSTRAINT_NAME cn, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt,
            k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE dr, r.UPDATE_RULE ur, k.ORDINAL_POSITION p
     FROM information_schema.KEY_COLUMN_USAGE k
     JOIN information_schema.REFERENTIAL_CONSTRAINTS r
       ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
     WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
     ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
    [$schema]
) as $z) {
    $fremd[$z->t][$z->cn]['spalten'][] = $z->c;
    $fremd[$z->t][$z->cn]['ziel'] = $z->rt;
    $fremd[$z->t][$z->cn]['zielspalten'][] = $z->rc;
    $fremd[$z->t][$z->cn]['loeschen'] = $z->dr;
}

// ── Hilfen ───────────────────────────────────────────────────────────────
$vorgabe = function ($z): string {
    if (str_contains($z->e, 'GENERATED')) {
        return '*berechnet*';
    }
    if ($z->d === null) {
        return $z->n === 'YES' ? 'NULL' : '—';
    }

    return '`'.$z->d.'`';
};

$zeilen = [];
$z = function (string $text = '') use (&$zeilen) {
    $zeilen[] = $text;
};

// ── Kopf ─────────────────────────────────────────────────────────────────
$z('# Wocheneinkauf · Datenbankdokumentation');
$z();
$z('**Stand:** '.date('d.m.Y').' · **Schema:** `'.$schema.'` · **'.count($spalten).' Tabellen**');
$z();
$z('> Struktur, Typen, Schlüssel und Indizes dieses Dokuments sind aus `information_schema` erzeugt und können deshalb nicht von der laufenden Datenbank abweichen. Die Erläuterungen sind von Hand geschrieben.');
$z();
$z('---');
$z();

// ── Konventionen ─────────────────────────────────────────────────────────
$z('## 1 · Konventionen');
$z();
$z('Wer eine Tabelle dieser Anwendung liest, findet immer dieselben Bauteile wieder. Sie einmal zu verstehen erspart es, sie siebenunddreißigmal nachzuschlagen.');
$z();
$z('### 1.1 Schlüssel');
$z();
$z('| Bauteil | Bedeutung |');
$z('|---|---|');
$z('| `id` | Fortlaufender Primärschlüssel, `bigint unsigned`. Bleibt **intern** -- er steht in keiner URL. |');
$z('| `public_id` | ULID (26 Zeichen, `ascii_bin`) für alles, was nach außen sichtbar wird. Aus einer laufenden Nummer ließe sich sonst ablesen, wie viele Familien oder Rezepte es gibt. |');
$z('| `family_id` | Der Mandant. Steht auf **jeder** fachlichen Tabelle -- ohne Ausnahme. |');
$z('| `client_uuid` | Vom Endgerät vergebene Kennung eines Listeneintrags. Macht wiederholtes Senden ungefährlich. |');
$z();
$z('### 1.2 Mandantentrennung auf drei Ebenen');
$z();
$z('Die Trennung der Familien ist die eine Eigenschaft, die nicht kaputtgehen darf. Deshalb hängt sie nicht an einer einzelnen Vorkehrung:');
$z();
$z('1. **Anwendung.** Ein Global Scope auf jedem Model hängt `where family_id = ?` an jede Abfrage. Wer das Filtern vergisst, bekommt trotzdem nur die eigenen Daten.');
$z('2. **Datenbank.** Verbund-Fremdschlüssel `(id, family_id)` statt einfacher Verweise: Ein Listeneintrag *kann* nicht in der Liste einer anderen Familie hängen, auch nicht durch einen Fehler im Code.');
$z('3. **Nachladen.** IDs aus Formularen werden nie direkt verwendet, sondern über den Global Scope neu geladen. Eine fremde ID trifft dann ins Leere, statt Daten zu verschieben.');
$z();
$z('Sichtbar wird Ebene 2 an den UNIQUE-Schlüsseln über `(id, family_id)`: Sie sehen überflüssig aus -- `id` ist schließlich schon eindeutig -- und sind es nicht. MySQL verlangt sie als Ziel für den Verbund-Fremdschlüssel.');
$z();
$z('### 1.3 Wiederkehrende Spalten');
$z();
$z('| Spalte | Bedeutung |');
$z('|---|---|');
$z('| `created_at`, `updated_at` | Vom Framework gepflegt. Fehlen bei Tabellen, die nur wachsen (Protokolle, Buchungen). |');
$z('| `deleted_at` | **Weiches Löschen.** Gesetzte Zeitmarke = gelöscht. Nötig für den Abgleich zwischen Geräten: Eine verschwundene Zeile wäre für das andere Gerät nicht von „noch nie gesehen" zu unterscheiden. |');
$z('| `sort` | Anzeigereihenfolge, kleinste Zahl zuerst. |');
$z('| `*_qty`, `amount` | `decimal(10,3)`. Kein Fließkomma: 0,1 + 0,2 soll 0,3 ergeben und nicht 0,30000000000000004. |');
$z();
$z('### 1.4 Regeln, die die Datenbank selbst durchsetzt');
$z();
$z('Drei Zusagen der Anwendung stehen nicht im Code, sondern im Schema -- der Code könnte sie unter gleichzeitigen Zugriffen sonst verletzen:');
$z();
$z('| Regel | Umsetzung |');
$z('|---|---|');
$z('| Höchstens **eine aktive Einkaufsliste** je Familie | Generierte Spalte `shopping_lists.aktiv_marker` (1 bei `active`, sonst NULL) plus UNIQUE `(family_id, aktiv_marker)`. NULL zählt im UNIQUE nicht mit, deshalb sind beliebig viele archivierte Listen erlaubt. |');
$z('| Höchstens **eine offene Inventur** je Familie | Dasselbe Muster mit `inventories.offen_marker`. |');
$z('| Abweichung einer Inventurzeile | Generierte Spalte `inventory_lines.difference` = gezählt − erwartet. Von der Datenbank gerechnet, damit sie nicht auseinanderlaufen kann. |');
$z();
$z('---');
$z();

// ── Übersicht ────────────────────────────────────────────────────────────
$z('## 2 · Die Tabellen im Überblick');
$z();
$z('| Gruppe | Tabellen |');
$z('|---|---|');
foreach ($prosa['gruppen'] as $name => $gruppe) {
    $z('| **'.$name.'** | '.implode(', ', array_map(fn ($t) => '`'.$t.'`', $gruppe['tabellen'])).' |');
}
$z();

// Vollständigkeitsprüfung: Was in der Datenbank steht, muss dokumentiert sein.
$dokumentiert = collect($prosa['gruppen'])->flatMap(fn ($g) => $g['tabellen'])->all();
$fehlend = array_diff(array_keys($spalten), $dokumentiert);
if ($fehlend !== []) {
    $z('> ⚠️ Nicht zugeordnet: '.implode(', ', $fehlend));
    $z();
}

// ── ER-Diagramm ──────────────────────────────────────────────────────────
$z('### 2.1 Beziehungen (ER-Diagramm)');
$z();
$z('Ohne die technischen Tabellen des Frameworks und ohne `family_id`, die an jeder fachlichen Tabelle hängt und das Bild sonst unlesbar machen würde.');
$z();
$z('```mermaid');
$z('erDiagram');
$z('    families ||--o{ family_user : "hat Mitglieder"');
$z('    users ||--o{ family_user : "gehört zu"');
$z('    families ||--o{ invitations : "lädt ein"');
$z('    families ||--o{ product_groups : ""');
$z('    product_groups ||--o{ product_group_rules : "Stichwortregeln"');
$z('    product_groups ||--o{ products : "gliedert"');
$z('    families ||--o{ markets : ""');
$z('    markets ||--o{ market_sections : "Regalbereiche"');
$z('    market_sections ||--o{ market_section_groups : ""');
$z('    product_groups ||--o{ market_section_groups : "liegt in"');
$z('    markets ||--o{ market_product : ""');
$z('    products ||--o{ market_product : "erhältlich in"');
$z('    families ||--o{ shopping_lists : ""');
$z('    markets ||--o| shopping_lists : "sortiert nach"');
$z('    shopping_lists ||--o{ shopping_list_items : "enthält"');
$z('    products ||--o{ shopping_list_items : "verweist auf"');
$z('    list_templates ||--o{ list_template_items : ""');
$z('    families ||--o{ recipes : ""');
$z('    recipes ||--o{ recipe_ingredients : "Zutaten"');
$z('    products ||--o{ recipe_ingredients : "verweist auf"');
$z('    recipes ||--o{ recipe_tag : ""');
$z('    tags ||--o{ recipe_tag : ""');
$z('    products ||--o{ pantry_items : "liegt im Vorrat"');
$z('    pantry_items ||--o{ pantry_movements : "Buchungen"');
$z('    inventories ||--o{ inventory_lines : "Zählzeilen"');
$z('    pantry_items ||--o{ inventory_lines : "gezählt wird"');
$z('    families ||--o{ meal_plans : ""');
$z('    meal_plans ||--o{ meal_plan_slots : "Plätze"');
$z('    recipes ||--o| meal_plan_slots : "belegt mit"');
$z('    meal_plans ||--o{ meal_plan_suggestions : "Vorschlagsläufe"');
$z('    shopping_lists ||--o| meal_plans : "übernommen in"');
$z('```');
$z();
$z('---');
$z();

// ── Tabellen im Einzelnen ────────────────────────────────────────────────
$z('## 3 · Die Tabellen im Einzelnen');
$z();

$nummer = 0;
foreach ($prosa['gruppen'] as $gruppenname => $gruppe) {
    $nummer++;
    $z('### 3.'.$nummer.' '.$gruppenname);
    $z();
    $z($gruppe['einleitung']);
    $z();

    foreach ($gruppe['tabellen'] as $tabelle) {
        if (! isset($spalten[$tabelle])) {
            $z('> ⚠️ Tabelle `'.$tabelle.'` ist dokumentiert, existiert aber nicht.');
            $z();

            continue;
        }

        $info = $prosa['tabellen'][$tabelle] ?? [];

        $z('#### `'.$tabelle.'`');
        $z();
        $z($info['zweck'] ?? '');
        $z();

        // Spalten
        $z('| Spalte | Typ | Null | Vorgabe | Erläuterung |');
        $z('|---|---|:---:|---|---|');
        foreach ($spalten[$tabelle] as $s) {
            $hinweis = $info['spalten'][$s->c] ?? '';
            if ($hinweis === '' && str_contains($s->e, 'GENERATED')) {
                $hinweis = 'Berechnet: `'.$s->g.'`';
            }
            $z('| `'.$s->c.'` | `'.$s->ty.'` | '.($s->n === 'YES' ? 'ja' : '—').' | '.$vorgabe($s).' | '.$hinweis.' |');
        }
        $z();

        // Schlüssel und Indizes
        $eigene = $indizes[$tabelle] ?? [];
        if ($eigene !== []) {
            $teile = [];
            foreach ($eigene as $name => $idx) {
                $art = $name === 'PRIMARY' ? 'Primärschlüssel' : ($idx['unique'] ? 'UNIQUE' : 'Index');
                $teile[] = '**'.$art.'** ('.implode(', ', array_map(fn ($c) => '`'.$c.'`', $idx['spalten'])).')';
            }
            $z('*Schlüssel und Indizes:* '.implode(' · ', $teile));
            $z();
        }

        // Fremdschlüssel
        $verweise = $fremd[$tabelle] ?? [];
        if ($verweise !== []) {
            $teile = [];
            foreach ($verweise as $fk) {
                $verbund = count($fk['spalten']) > 1 ? ' *(Verbund)*' : '';
                $teile[] = '('.implode(', ', array_map(fn ($c) => '`'.$c.'`', $fk['spalten'])).') → `'
                    .$fk['ziel'].'` '.$verbund.' beim Löschen: '.strtolower($fk['loeschen']);
            }
            $z('*Fremdschlüssel:* '.implode(' · ', $teile));
            $z();
        }

        foreach ($info['besonderheiten'] ?? [] as $punkt) {
            $z('> '.$punkt);
            $z();
        }
    }
}

// ── Anhang ───────────────────────────────────────────────────────────────
$z('---');
$z();
$z('## 4 · Anhang');
$z();
$z('### 4.1 Zahlen');
$z();
$anzahlSpalten = array_sum(array_map('count', $spalten));
$anzahlFks = array_sum(array_map('count', $fremd));
$anzahlIdx = array_sum(array_map('count', $indizes));
$z('| Kennzahl | Wert |');
$z('|---|---:|');
$z('| Tabellen | '.count($spalten).' |');
$z('| Spalten | '.$anzahlSpalten.' |');
$z('| Fremdschlüssel | '.$anzahlFks.' |');
$z('| Schlüssel und Indizes | '.$anzahlIdx.' |');
$z('| Generierte Spalten | 3 |');
$z();
$z('### 4.2 Dokument neu erzeugen');
$z();
$z('Das Dokument entsteht aus dem laufenden Schema. Nach jeder Migration lohnt der erneute Lauf:');
$z();
$z('```bash');
$z('php scripts/db-doku.php');
$z('```');
$z();
$z('Prüft dabei auch, ob eine neue Tabelle noch ohne Beschreibung ist -- die taucht dann oben als Warnung auf.');

file_put_contents(__DIR__.'/../docs/Wocheneinkauf-Datenbank.md', implode("\n", $zeilen)."\n");

echo 'geschrieben: docs/Wocheneinkauf-Datenbank.md ('.count($zeilen)." Zeilen)\n";
