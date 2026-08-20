@verbatim
Erzeuge Rezepte als JSON für eine Haushaltsanwendung. Antworte NUR mit dem
JSON – kein Vorwort, keine Erklärung, kein Markdown-Codeblock.

{
  "version": 1,
  "rezepte": [{
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
      { "name": "rote Linsen", "menge": 250, "einheit": "g", "vorrat": true, "optional": false, "einkaufsname": null, "sammelbegriff": "linsen" },
      { "name": "Petersilie", "menge": null, "einheit": null, "vorrat": false, "optional": true, "einkaufsname": null, "sammelbegriff": null }
    ]
  }]
}

Regeln

name          Pflicht, max. 200 Zeichen, je Antwort eindeutig.
portionen     1–99, Vorgabe 4. Mengen für genau diese Portionszahl.
vorbereitung_minuten, koch_minuten  Ganze Zahl oder null – keine Spannen
              wie "15–20".
aufwand       "quick" (unter 30 Min.), "normal" oder "elaborate" (über
              1 Std. oder mit langen Wartezeiten).
anmerkungen   Kurzer Hinweis oder null, höchstens 5000 Zeichen.
pflanzenpunkte  Anzahl VERSCHIEDENER pflanzlicher Zutaten (Gemüse, Obst,
              Hülsenfrüchte, Nüsse, Samen, Kräuter, Vollkorn), 0–255.
quelle        Echte URL oder null – keine erfinden.
naehrwerte    Nur wenn belastbar, sonst null. Lieber null als geraten.
zubereitung   Nummerierte Schritte, getrennt durch \n, kein Markdown.
schlagworte   Max. 20. "art" ist eines von: protein, cuisine, diet, course,
              season, other. Möglichst je eines aus protein und cuisine –
              davon lebt die Abwechslung im Wochenplan.

zutaten       1–80 Stück. Je Zutat:
  name        So, wie man es einkauft: "Zwiebeln", nicht "2 gewürfelte
              Zwiebeln". Zubereitungshinweise gehören in "zubereitung".
  menge       Zahl mit Punkt als Dezimaltrennzeichen, oder null bei
              "etwas", "nach Geschmack", "eine Handvoll".
  einheit     g, kg, ml, l, Stk, Pck, Dose, Glas, Fl, Becher, Beutel, Bund,
              Kopf, Scheibe, EL, TL, Prise. null, wenn menge null ist.
  vorrat      true bei Schrankware (Salz, Pfeffer, Öl, Mehl, Zucker, Essig,
              Gewürze, Reis, Nudeln) – die ist auf der Einkaufsliste vorab
              abgewählt. Sonst false.
  optional    true bei "nach Belieben".
  einkaufsname  Nur wenn auf der Einkaufsliste etwas anderes stehen soll als
              der Zutatenname, sonst null.
  sammelbegriff  Kleingeschriebener Oberbegriff, unter dem gleiche Zutaten
              mehrerer Rezepte zu EINEM Listeneintrag zusammenfallen:
              "zwiebel", "tomaten", "linsen". Sonst null.

Deutsche Bezeichnungen. Zubereitung in eigenen Worten – keine geschützten
Rezepttexte abschreiben. Höchstens 200 Rezepte. Gültiges JSON: doppelte
Anführungszeichen, kein Komma hinter dem letzten Element, keine Kommentare.

Auftrag – hier ergänzen:
@endverbatim
