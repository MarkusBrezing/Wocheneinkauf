# Die Einkaufsliste

*Seitenart: 🖥▤ PC und Tablet · Adresse: `/einkaufsliste`*

Die Hauptseite. Sie zeigt die **eine aktive Liste** der Familie. Mehr als eine gibt es nicht — alles andere ist Archiv.

## Artikel aufschreiben

Die Eingabe hat drei Felder, und ihre Reihenfolge hat einen Grund:

| Feld | Beispiel | Wozu |
|---|---|---|
| **Warengruppe** | Milchprodukte | Schränkt die Vorschläge im Artikelfeld ein — steht deshalb vorn |
| **Menge** | `2 l`, `500g`, `1-2` | Alles Zahlenhafte |
| **Artikel** | Milch | Nur der Name |

Die Warengruppe darf auf „automatisch" bleiben; dann sortiert die Anwendung selbst ein (siehe Kapitel *Artikel und Warengruppen*). Wählen Sie eine, schlägt das Artikelfeld nur noch deren Artikel vor — bei einem Katalog mit dreihundert Einträgen ist das der Unterschied zwischen Suchen und Finden.

Die Menge darf leer bleiben. Erkannt werden nur **bekannte Einheiten** (g, kg, ml, l, Stück, Packung, Dose, Bund …).

> **Alles in einem Feld?** Wer aus Gewohnheit „2 l Milch" ins Artikelfeld tippt, bekommt weiterhin das Richtige — gelesen wird beides gleich. Auch `1-2 Zwiebeln, rote` funktioniert: Der Teil hinter dem Komma wird zur Notiz.

## Zusammenführen statt doppeln

Steht ein Artikel bereits **offen** auf der Liste, werden die Mengen addiert statt eine zweite Zeile anzulegen. Aus „1 l Milch" plus „2 l Milch" wird „3 l Milch".

Addiert wird nur bei **gleicher Einheit**. „1 l Milch" und „1 Packung Milch" ergeben sonst eine falsche Zahl; die zweite Angabe landet deshalb in der Notiz. Bereits abgehakte Einträge werden nie mit neuen zusammengeführt — Sie haben sie ja schon im Wagen.

## Die Gliederung

Die Liste ist nach **Regalbereichen des gewählten Marktes** gegliedert, in der Reihenfolge, in der man durch den Laden geht. Ohne gewählten Markt gliedert sie nach Warengruppen. Abgehaktes sinkt innerhalb seines Abschnitts nach unten, bleibt aber sichtbar.

Jeder Abschnitt ist **klappbar** und steht offen, solange etwas fehlt; rechts im Kopf steht, wie viele Artikel darin noch offen sind. Ein Abschnitt, in dem alles abgehakt ist, **klappt von selbst zu** und tritt zurück — er steht ja schon im Wagen.

Am Ende steht gegebenenfalls der Abschnitt **„Gibt es hier nicht"** — Artikel, die im gewählten Markt nicht erhältlich sind (siehe Kapitel *Märkte*).

## Was Sie mit einem Eintrag tun können

Die Liste ist bewusst eng gesetzt — je mehr Artikel ohne Scrollen sichtbar sind, desto weniger muss man suchen. Jede Zeile zeigt Haken, Menge, Artikel; ganz rechts sitzt **bearbeiten**, weit weg vom Haken, der tausendmal häufiger getroffen wird.

| Handlung | Wirkung |
|---|---|
| **Abhaken** | Merkt sich, wer wann abgehakt hat |
| **bearbeiten** | Klappt eine Zeile mit Warengruppe, Menge, Artikel und Notiz auf |
| **Warengruppe ändern** | Verschiebt den Eintrag in einen anderen Abschnitt |
| **Löschen** | Andere Geräte bekommen die Löschung beim Abgleich mit |

## Markt umschalten

Über der Liste wählen Sie den Markt. Das ändert **nur die Sortierung**, nie die Einträge. Dieselbe Liste sieht im Discounter anders aus als im Supermarkt — sie enthält aber dasselbe.

## Drucken und teilen

**Drucken** öffnet dieselbe Liste ohne Bedienelemente. Dort finden Sie auch eine Fassung als reinen Text zum Kopieren in eine Nachricht.

## Einkauf abschließen

**Einkauf beenden** archiviert die Liste und legt eine neue an. Nicht abgehakte Artikel kommen dabei auf die neue Liste — sie haben ja gefehlt. Im Archiv bleibt sichtbar, was gefehlt hat.

Wenn eine Standardvorlage hinterlegt ist, wird sie anschließend auf die neue Liste gesetzt. Die Reihenfolge ist wichtig und von der Anwendung so gewollt: erst die fehlenden Artikel, dann die Vorlage — sonst stünde manches doppelt da.
