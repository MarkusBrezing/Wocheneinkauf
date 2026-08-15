@verbatim
Du schreibst Rezepte als JSON für eine Haushaltsanwendung. Halte dich genau
an das folgende Format – die Antwort wird maschinell eingelesen.

Antworte AUSSCHLIESSLICH mit dem JSON, ohne Vorwort, ohne Erklärung und ohne
Markdown-Codeblock.

Aufbau:

{
  "version": 1,
  "rezepte": [
    {
      "name": "Linsencurry mit Spinat",
      "portionen": 4,
      "vorbereitung_minuten": 15,
      "koch_minuten": 25,
      "aufwand": "normal",
      "pflanzenpunkte": 6,
      "quelle": null,
      "anmerkungen": "Schmeckt am zweiten Tag besser.",
      "zubereitung": "1. Zwiebeln würfeln und anschwitzen.\n2. Linsen und Kokosmilch zugeben, 20 Minuten köcheln.\n3. Spinat unterheben.",
      "naehrwerte": { "kcal": 520, "eiweiss_g": 22, "fett_g": 18, "kohlenhydrate_g": 58 },
      "schlagworte": [
        { "art": "protein", "name": "vegetarisch" },
        { "art": "cuisine", "name": "indisch" },
        { "art": "course", "name": "Hauptgericht" }
      ],
      "zutaten": [
        { "name": "rote Linsen", "menge": 250, "einheit": "g", "vorrat": true,  "optional": false, "einkaufsname": null, "sammelbegriff": "linsen" },
        { "name": "Zwiebeln", "menge": 2, "einheit": "Stk", "vorrat": false, "optional": false, "einkaufsname": null, "sammelbegriff": "zwiebel" },
        { "name": "Blattspinat", "menge": 200, "einheit": "g", "vorrat": false, "optional": false, "einkaufsname": null, "sammelbegriff": null },
        { "name": "Petersilie", "menge": null, "einheit": null, "vorrat": false, "optional": true, "einkaufsname": null, "sammelbegriff": null }
      ]
    }
  ]
}

Regeln zu den Feldern:

name                  Pflicht. Höchstens 200 Zeichen, eindeutig innerhalb der Datei.
portionen             Ganze Zahl 1–99. Ohne Angabe gilt 4.
vorbereitung_minuten  Ganze Zahl oder null.
koch_minuten          Ganze Zahl oder null.
aufwand               Genau einer von: "quick", "normal", "elaborate".
                      quick = unter 30 Minuten, elaborate = über einer Stunde
                      oder mit längeren Wartezeiten.
pflanzenpunkte        Anzahl VERSCHIEDENER pflanzlicher Zutaten (Gemüse, Obst,
                      Hülsenfrüchte, Nüsse, Samen, Kräuter, Vollkorn). 0–255.
quelle                Vollständige URL oder null. Erfinde keine URL.
anmerkungen           Kurzer Hinweis oder null. Höchstens 5000 Zeichen.
zubereitung           Nummerierte Schritte, getrennt durch \n. Kein Markdown
                      außer einfachen Zeilenumbrüchen.
naehrwerte            Objekt mit frei gewählten Schlüsseln oder null. Nur
                      angeben, wenn die Werte belastbar sind – lieber null als
                      geraten.

schlagworte           Liste, höchstens 20. Je Eintrag "art" und "name".
                      art ist genau einer von:
                        protein   – vegetarisch, vegan, Rind, Huhn, Fisch …
                        cuisine   – italienisch, indisch, deutsch …
                        diet      – glutenfrei, laktosefrei, Low Carb …
                        course    – Hauptgericht, Suppe, Beilage, Frühstück …
                        season    – Frühling, Sommer, Herbst, Winter
                        other     – alles Übrige
                      Vergib nach Möglichkeit je ein protein- und ein
                      cuisine-Schlagwort: Der Wochenplan sorgt damit für
                      Abwechslung.

zutaten               Pflicht, mindestens eine, höchstens 80. Je Zutat:
  name                Pflicht. Der Name, wie man ihn einkauft – also
                      "Zwiebeln", nicht "2 mittelgroße Zwiebeln, gewürfelt".
                      Zubereitungshinweise gehören in die Zubereitung.
  menge               Zahl oder null. Punkt als Dezimaltrennzeichen.
                      null bei "etwas", "nach Geschmack", "eine Handvoll".
  einheit             Eine von: g, kg, ml, l, Stk, Pck, Dose, Glas, Fl,
                      Becher, Beutel, Bund, Kopf, Scheibe, EL, TL, Prise.
                      null, wenn die Menge null ist.
  vorrat              true bei Dingen, die üblicherweise im Schrank stehen:
                      Salz, Pfeffer, Öl, Mehl, Zucker, Essig, Gewürze,
                      Grundnudeln, Reis. Sonst false. Diese Zutaten sind beim
                      Übertragen auf die Einkaufsliste vorab abgewählt.
  optional            true bei "nach Belieben".
  einkaufsname        Nur setzen, wenn auf der Einkaufsliste etwas anderes
                      stehen soll als der Zutatenname – etwa
                      "Olivenöl, gutes fürs Dressing". Sonst null.
  sammelbegriff       Kleingeschriebener Oberbegriff, unter dem gleiche
                      Zutaten aus mehreren Rezepten zu EINEM Eintrag auf der
                      Einkaufsliste zusammenfallen sollen: "zwiebel",
                      "tomaten", "linsen". Sonst null.

Weitere Vorgaben:

- Deutsche Bezeichnungen, deutsche Rechtschreibung.
- Keine urheberrechtlich geschützten Rezepttexte abschreiben. Formuliere die
  Zubereitung in eigenen Worten.
- Mengen für die angegebene Portionszahl, nicht für eine andere.
- Höchstens 200 Rezepte je Antwort.
- Gültiges JSON: doppelte Anführungszeichen, kein Komma hinter dem letzten
  Element, keine Kommentare.

Und jetzt der eigentliche Auftrag – ergänze hier, was du brauchst:
@endverbatim
