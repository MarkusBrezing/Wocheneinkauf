<?php

namespace App\Modules\Catalog\Data;

/**
 * Der Startdatensatz einer neuen Familie (B-09).
 *
 * Uebernommen aus CATEGORIES und KEYWORD_RULES der alten App, damit sich der
 * Umstieg nicht wie ein Rueckschritt anfuehlt. Alles hier ist nur eine
 * Startaufstellung -- jede Familie darf danach umbenennen, loeschen und
 * ergaenzen.
 */
class StarterData
{
    /**
     * Warengruppen in sinnvoller Laufreihenfolge durch einen Supermarkt.
     *
     * @return array<int, array{code: string, label: string}>
     */
    public static function productGroups(): array
    {
        return [
            ['code' => 'OBST_GEMUESE', 'label' => 'Obst und Gemüse'],
            ['code' => 'BACKWAREN', 'label' => 'Backwaren'],
            ['code' => 'MILCH', 'label' => 'Milchprodukte'],
            ['code' => 'EIER', 'label' => 'Eier'],
            ['code' => 'FLEISCH', 'label' => 'Fleisch und Wurst'],
            ['code' => 'FISCH', 'label' => 'Fisch'],
            ['code' => 'TIEFKUEHL', 'label' => 'Tiefkühl'],
            ['code' => 'KONSERVEN', 'label' => 'Konserven und Gläser'],
            ['code' => 'TROCKEN', 'label' => 'Nudeln, Reis, Mehl'],
            ['code' => 'GEWUERZE', 'label' => 'Gewürze und Öle'],
            ['code' => 'SUESS', 'label' => 'Süßes und Snacks'],
            ['code' => 'GETRAENKE', 'label' => 'Getränke'],
            ['code' => 'HAUSHALT', 'label' => 'Haushalt'],
            ['code' => 'DROGERIE', 'label' => 'Drogerie'],
            ['code' => 'SONSTIGES', 'label' => 'Sonstiges'],
        ];
    }

    /**
     * Stichwortregeln der Auto-Kategorisierung.
     *
     * priority 50 fuer Faelle, die vor einer allgemeineren Regel greifen
     * muessen: "kirschtomate" enthaelt "tomate", "sahnejoghurt" enthaelt
     * "sahne". Ohne die Vorrangregel gewaenne die zuerst gefundene Zeile.
     *
     * Die Staffelung insgesamt:
     *
     *   30   Getraenke am Wortende -- "Apfelsaft" ist ein Getraenk
     *   40   Eier
     *   50   Zusammensetzungen, die einer allgemeinen Regel zuvorkommen
     *   60   Eis
     *  100   die allgemeinen Regeln
     *  110   Auffangnetz fuer Getraenke am Wortanfang
     *
     * Deutsche Zusammensetzungen sind der Grund fuer die halbe Handvoll
     * regulaerer Ausdruecke hier: Ein Stichwort, das irgendwo im Wort steckt,
     * trifft zu oft daneben. "Wassermelone" ist kein Getraenk, "Basmatireis"
     * keine Tiefkuehlware, und "Weintrauben" wachsen nicht in der
     * Getraenkeabteilung.
     *
     * @return array<int, array{group: string, pattern: string, priority?: int, regex?: bool}>
     */
    public static function rules(): array
    {
        return [
            // Umlautplural: "Äpfel" ist kein Sonderfall, sondern der Normalfall
            // auf einem Einkaufszettel.
            ['group' => 'OBST_GEMUESE', 'pattern' => '(äpfel|apfel)', 'regex' => true],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'banane'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'tomate'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'gurke'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'salat'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'zwiebel'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'kartoffel'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'karotte'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'möhre'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'paprika'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'zitrone'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'knoblauch'],
            // Beide tragen ein Getraenkewort im Namen -- ohne eigene Regel
            // blieben "Wassermelone" und "Weintrauben" ohne Zuordnung.
            ['group' => 'OBST_GEMUESE', 'pattern' => 'melone'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'traube'],
            // Weiteres Alltagsobst und -gemuese. "beere" deckt Erd-, Heidel-
            // und Himbeeren mit ab, "orange" bleibt hinter der Getraenkeregel
            // (priority 30) zurueck -- Orangensaft ist ein Getraenk.
            ['group' => 'OBST_GEMUESE', 'pattern' => 'birne'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'orange'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'beere'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'pfirsich'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'kirsche'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'pflaume'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'mango'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'ananas'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'kiwi'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'kohl'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'spargel'],
            ['group' => 'OBST_GEMUESE', 'pattern' => 'aubergine'],

            ['group' => 'BACKWAREN', 'pattern' => 'brot'],
            ['group' => 'BACKWAREN', 'pattern' => 'brötchen'],
            ['group' => 'BACKWAREN', 'pattern' => 'toast'],
            ['group' => 'BACKWAREN', 'pattern' => 'baguette'],

            ['group' => 'MILCH', 'pattern' => 'milch'],
            ['group' => 'MILCH', 'pattern' => 'butter'],
            ['group' => 'MILCH', 'pattern' => 'käse'],
            ['group' => 'MILCH', 'pattern' => 'joghurt'],
            ['group' => 'MILCH', 'pattern' => 'quark'],
            ['group' => 'MILCH', 'pattern' => 'sahne'],
            ['group' => 'MILCH', 'pattern' => 'frischkäse'],

            // Als Ausdruck, weil "ei" als Teilstring in Zwiebel, Weizen und
            // Eis steckt. Muss vor den allgemeinen Regeln greifen.
            ['group' => 'EIER', 'pattern' => '^eier?\b', 'regex' => true, 'priority' => 40],

            ['group' => 'FLEISCH', 'pattern' => 'hackfleisch'],
            ['group' => 'FLEISCH', 'pattern' => 'hähnchen'],
            ['group' => 'FLEISCH', 'pattern' => 'schnitzel'],
            // "Würstchen" umlautet -- ohne die Variante faellt es durch.
            ['group' => 'FLEISCH', 'pattern' => '(würst|wurst)', 'regex' => true],
            ['group' => 'FLEISCH', 'pattern' => 'schinken'],
            ['group' => 'FLEISCH', 'pattern' => 'salami'],

            ['group' => 'FISCH', 'pattern' => 'lachs'],
            ['group' => 'FISCH', 'pattern' => 'thunfisch'],
            ['group' => 'FISCH', 'pattern' => 'garnele'],

            ['group' => 'TIEFKUEHL', 'pattern' => 'tiefkühl'],
            ['group' => 'TIEFKUEHL', 'pattern' => 'pizza'],
            // Als Ausdruck mit Wortgrenzen, weil "eis" mitten in Basmatireis,
            // Hackfleisch und Eisbergsalat steckt -- als Teilstring gelesen
            // landete die halbe Liste in der Tiefkuehltruhe.
            ['group' => 'TIEFKUEHL', 'pattern' => '\beis(creme|würfel)?\b', 'regex' => true, 'priority' => 60],

            ['group' => 'KONSERVEN', 'pattern' => 'dose'],
            ['group' => 'KONSERVEN', 'pattern' => 'mais'],
            // priority 50: Alle drei tragen ein allgemeineres Stichwort im
            // Namen -- "tomate" bzw. "milch" -- und muessen ihm zuvorkommen.
            ['group' => 'KONSERVEN', 'pattern' => 'passierte tomaten', 'priority' => 50],
            ['group' => 'KONSERVEN', 'pattern' => 'gehackte tomaten', 'priority' => 50],
            ['group' => 'KONSERVEN', 'pattern' => 'tomatenmark', 'priority' => 50],
            ['group' => 'KONSERVEN', 'pattern' => 'kokosmilch', 'priority' => 50],
            ['group' => 'KONSERVEN', 'pattern' => 'kichererbse'],
            // Bewusst nicht "bohnen": das steckt auch in Kaffeebohnen, und
            // frische oder tiefgekuehlte Bohnen sind ohnehin keine Konserve.
            ['group' => 'KONSERVEN', 'pattern' => 'kidneybohne'],

            ['group' => 'TROCKEN', 'pattern' => 'nudel'],
            ['group' => 'TROCKEN', 'pattern' => 'spaghetti'],
            ['group' => 'TROCKEN', 'pattern' => 'reis'],
            ['group' => 'TROCKEN', 'pattern' => 'mehl'],
            ['group' => 'TROCKEN', 'pattern' => 'zucker'],
            ['group' => 'TROCKEN', 'pattern' => 'haferflocken'],
            ['group' => 'TROCKEN', 'pattern' => 'linsen'],
            ['group' => 'TROCKEN', 'pattern' => 'couscous'],
            ['group' => 'TROCKEN', 'pattern' => 'backpulver'],
            ['group' => 'TROCKEN', 'pattern' => 'hefe'],
            ['group' => 'TROCKEN', 'pattern' => 'stärke'],
            ['group' => 'TROCKEN', 'pattern' => 'semmelbrösel'],

            ['group' => 'GEWUERZE', 'pattern' => 'salz'],
            ['group' => 'GEWUERZE', 'pattern' => 'pfeffer'],
            ['group' => 'GEWUERZE', 'pattern' => 'öl'],
            ['group' => 'GEWUERZE', 'pattern' => 'essig'],
            ['group' => 'GEWUERZE', 'pattern' => 'senf'],
            ['group' => 'GEWUERZE', 'pattern' => 'brühe'],
            ['group' => 'GEWUERZE', 'pattern' => 'curry'],
            ['group' => 'GEWUERZE', 'pattern' => 'oregano'],
            ['group' => 'GEWUERZE', 'pattern' => 'zimt'],
            ['group' => 'GEWUERZE', 'pattern' => 'lorbeer'],
            // Vor "paprika", sonst landet das Pulver im Gemuesefach.
            ['group' => 'GEWUERZE', 'pattern' => 'paprikapulver', 'priority' => 50],
            // Vor "essig", damit Balsamico nicht am allgemeinen Stichwort
            // haengenbleibt -- inhaltlich dieselbe Gruppe, aber so bleibt die
            // Absicht sichtbar.
            ['group' => 'GEWUERZE', 'pattern' => 'balsamico', 'priority' => 50],

            ['group' => 'SUESS', 'pattern' => 'schokolade'],
            ['group' => 'SUESS', 'pattern' => 'keks'],
            ['group' => 'SUESS', 'pattern' => 'chips'],
            ['group' => 'SUESS', 'pattern' => 'honig'],
            ['group' => 'SUESS', 'pattern' => 'marmelade'],
            ['group' => 'SUESS', 'pattern' => 'nussmus'],

            // Im Deutschen steht das Getraenk am Wortende: Apfel*saft*,
            // Mineral*wasser*, Eis*tee*, Rot*wein*. Genau deshalb die
            // Wortgrenze -- "Wassermelone", "Weintrauben", "Bierschinken" und
            // "Kaffeesahne" enden eben nicht darauf und bleiben, wo sie
            // hingehoeren. priority 30 zieht die Regel vor alle anderen: sonst
            // gewaenne bei "Apfelsaft" das Obst und bei "Eistee" die
            // Tiefkuehltruhe.
            ['group' => 'GETRAENKE', 'pattern' => '(saft|schorle|limonade|cola|wasser|bier|wein|sekt|tee|kaffee)\b',
                'regex' => true, 'priority' => 30],

            // Auffangnetz fuer die Faelle, in denen das Getraenk vorn steht:
            // "Kaffeebohnen", "Teebeutel". Ganz am Ende der Kette, damit
            // "Kaffeesahne" bei den Milchprodukten bleibt.
            ['group' => 'GETRAENKE', 'pattern' => 'kaffee', 'priority' => 110],
            ['group' => 'GETRAENKE', 'pattern' => 'tee', 'priority' => 110],

            ['group' => 'HAUSHALT', 'pattern' => 'spülmittel'],
            ['group' => 'HAUSHALT', 'pattern' => 'müllbeutel'],
            ['group' => 'HAUSHALT', 'pattern' => 'küchenrolle'],
            ['group' => 'HAUSHALT', 'pattern' => 'waschmittel'],
            // Das gebraeuchlichere Wort fehlte -- die Regel hiess nur
            // "toilettenpapier".
            ['group' => 'HAUSHALT', 'pattern' => 'klopapier'],
            ['group' => 'HAUSHALT', 'pattern' => 'backpapier'],
            ['group' => 'HAUSHALT', 'pattern' => 'frischhaltefolie'],
            ['group' => 'HAUSHALT', 'pattern' => 'alufolie'],
            ['group' => 'HAUSHALT', 'pattern' => 'spülmaschinentabs'],

            ['group' => 'DROGERIE', 'pattern' => 'zahnpasta'],
            ['group' => 'DROGERIE', 'pattern' => 'shampoo'],
            ['group' => 'DROGERIE', 'pattern' => 'duschgel'],
            ['group' => 'DROGERIE', 'pattern' => 'toilettenpapier'],
        ];
    }

    /**
     * Gaengige Vorratsprodukte fuer den Artikelkatalog.
     *
     * Das ist der Bestand, der in fast jedem Haushalt im Schrank steht und
     * beim Kochen vorausgesetzt wird. Als eigener Satz und nicht im
     * Startdatensatz (B-09), weil er eine Annahme trifft: Nicht jede Familie
     * kocht mit Sojasauce, und ein Katalog voller nie gekaufter Artikel
     * erschwert die Suche mehr, als er nuetzt. Wer ihn will, holt ihn sich:
     *
     *   php artisan catalog:install-pantry-staples --family=<public_id>
     *
     * is_pantry_staple markiert sie als Grundvorrat -- Rezepte setzen solche
     * Zutaten als vorhanden voraus und schreiben sie nicht auf die Liste
     * (D-05).
     *
     * @return array<int, array{name: string, unit?: ?string, group?: string}>
     */
    public static function pantryStaples(): array
    {
        return [
            // Nudeln, Reis, Mehl
            ['name' => 'Mehl', 'unit' => 'kg'],
            ['name' => 'Vollkornmehl', 'unit' => 'kg'],
            ['name' => 'Zucker', 'unit' => 'kg'],
            ['name' => 'Puderzucker', 'unit' => 'Pck'],
            ['name' => 'Speisestärke', 'unit' => 'Pck'],
            ['name' => 'Backpulver', 'unit' => 'Pck'],
            ['name' => 'Hefe', 'unit' => 'Pck'],
            ['name' => 'Haferflocken', 'unit' => 'Pck'],
            ['name' => 'Spaghetti', 'unit' => 'Pck'],
            ['name' => 'Nudeln', 'unit' => 'Pck'],
            ['name' => 'Reis', 'unit' => 'Pck'],
            ['name' => 'Basmatireis', 'unit' => 'Pck'],
            ['name' => 'Couscous', 'unit' => 'Pck'],
            ['name' => 'Linsen', 'unit' => 'Pck'],
            ['name' => 'Semmelbrösel', 'unit' => 'Pck'],

            // Konserven und Glaeser
            ['name' => 'Passierte Tomaten', 'unit' => 'Glas'],
            ['name' => 'Gehackte Tomaten', 'unit' => 'Dose'],
            ['name' => 'Tomatenmark', 'unit' => 'Glas'],
            ['name' => 'Kichererbsen', 'unit' => 'Dose'],
            ['name' => 'Kidneybohnen', 'unit' => 'Dose'],
            ['name' => 'Mais', 'unit' => 'Dose'],
            ['name' => 'Kokosmilch', 'unit' => 'Dose'],
            ['name' => 'Thunfisch', 'unit' => 'Dose'],
            ['name' => 'Gemüsebrühe', 'unit' => 'Glas', 'group' => 'GEWUERZE'],

            // Gewuerze und Oele
            ['name' => 'Salz', 'unit' => 'Pck'],
            ['name' => 'Pfeffer', 'unit' => 'Mühle'],
            ['name' => 'Olivenöl', 'unit' => 'Fl'],
            ['name' => 'Rapsöl', 'unit' => 'Fl'],
            ['name' => 'Essig', 'unit' => 'Fl'],
            ['name' => 'Balsamico', 'unit' => 'Fl'],
            ['name' => 'Senf', 'unit' => 'Glas'],
            ['name' => 'Paprikapulver', 'unit' => 'Pck'],
            ['name' => 'Curry', 'unit' => 'Pck', 'group' => 'GEWUERZE'],
            ['name' => 'Oregano', 'unit' => 'Pck', 'group' => 'GEWUERZE'],
            ['name' => 'Zimt', 'unit' => 'Pck', 'group' => 'GEWUERZE'],
            ['name' => 'Lorbeerblätter', 'unit' => 'Pck', 'group' => 'GEWUERZE'],
            ['name' => 'Honig', 'unit' => 'Glas', 'group' => 'SUESS'],

            // Getraenke und Fruehstueck
            ['name' => 'Kaffee', 'unit' => 'Pck'],
            ['name' => 'Tee', 'unit' => 'Pck'],
            ['name' => 'Marmelade', 'unit' => 'Glas', 'group' => 'SUESS'],
            ['name' => 'Nussmus', 'unit' => 'Glas', 'group' => 'SUESS'],

            // Haushalt und Drogerie
            ['name' => 'Klopapier', 'unit' => 'Pck'],
            ['name' => 'Küchenrolle', 'unit' => 'Pck'],
            ['name' => 'Spülmittel', 'unit' => 'Fl'],
            ['name' => 'Waschmittel', 'unit' => 'Pck'],
            ['name' => 'Müllbeutel', 'unit' => 'Pck'],
            ['name' => 'Frischhaltefolie', 'unit' => 'Pck', 'group' => 'HAUSHALT'],
            ['name' => 'Backpapier', 'unit' => 'Pck', 'group' => 'HAUSHALT'],
            ['name' => 'Zahnpasta', 'unit' => 'Stk'],
            ['name' => 'Duschgel', 'unit' => 'Fl'],
            ['name' => 'Shampoo', 'unit' => 'Fl'],
        ];
    }

    /**
     * Was in einem deutschen Familienhaushalt Woche fuer Woche im Wagen
     * landet -- Frisches, Gekuehltes, Getraenke, Suesses, Drogerie.
     *
     * Ergaenzt pantryStaples() um alles, was nicht im Schrank steht, sondern
     * regelmaessig nachgekauft wird. Kein Grundvorrat: Diese Artikel gehoeren
     * auf die Liste, wenn sie fehlen, und werden von Rezepten nicht als
     * vorhanden vorausgesetzt.
     *
     * @return array<int, array{name: string, unit?: ?string, group?: string}>
     */
    public static function everydayProducts(): array
    {
        return [
            // Obst und Gemuese
            ['name' => 'Äpfel', 'unit' => 'kg'],
            ['name' => 'Bananen', 'unit' => 'kg'],
            ['name' => 'Birnen', 'unit' => 'kg'],
            ['name' => 'Orangen', 'unit' => 'kg'],
            ['name' => 'Zitronen', 'unit' => 'Stk'],
            ['name' => 'Weintrauben', 'unit' => 'g'],
            ['name' => 'Erdbeeren', 'unit' => 'g'],
            ['name' => 'Heidelbeeren', 'unit' => 'g', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Tomaten', 'unit' => 'g'],
            ['name' => 'Kirschtomaten', 'unit' => 'g'],
            ['name' => 'Gurken', 'unit' => 'Stk'],
            ['name' => 'Paprika', 'unit' => 'Stk'],
            ['name' => 'Zucchini', 'unit' => 'Stk', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Möhren', 'unit' => 'kg'],
            ['name' => 'Kartoffeln', 'unit' => 'kg'],
            ['name' => 'Zwiebeln', 'unit' => 'kg'],
            ['name' => 'Knoblauch', 'unit' => 'Stk'],
            ['name' => 'Lauch', 'unit' => 'Stk', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Brokkoli', 'unit' => 'Stk', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Blumenkohl', 'unit' => 'Stk', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Champignons', 'unit' => 'g', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Kopfsalat', 'unit' => 'Stk'],
            ['name' => 'Feldsalat', 'unit' => 'g'],
            ['name' => 'Babyspinat', 'unit' => 'g', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Avocado', 'unit' => 'Stk', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Ingwer', 'unit' => 'Stk', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Petersilie', 'unit' => 'Bund', 'group' => 'OBST_GEMUESE'],
            ['name' => 'Schnittlauch', 'unit' => 'Bund', 'group' => 'OBST_GEMUESE'],

            // Backwaren
            ['name' => 'Vollkornbrot', 'unit' => 'Stk'],
            ['name' => 'Toastbrot', 'unit' => 'Pck'],
            ['name' => 'Brötchen', 'unit' => 'Stk'],
            ['name' => 'Baguette', 'unit' => 'Stk'],
            ['name' => 'Croissants', 'unit' => 'Stk', 'group' => 'BACKWAREN'],
            ['name' => 'Knäckebrot', 'unit' => 'Pck'],

            // Kuehlregal
            ['name' => 'Vollmilch', 'unit' => 'l'],
            ['name' => 'H-Milch', 'unit' => 'l'],
            ['name' => 'Butter', 'unit' => 'Pck'],
            ['name' => 'Margarine', 'unit' => 'Becher', 'group' => 'MILCH'],
            ['name' => 'Schlagsahne', 'unit' => 'Becher'],
            ['name' => 'Schmand', 'unit' => 'Becher', 'group' => 'MILCH'],
            ['name' => 'Crème fraîche', 'unit' => 'Becher', 'group' => 'MILCH'],
            ['name' => 'Naturjoghurt', 'unit' => 'g'],
            ['name' => 'Fruchtjoghurt', 'unit' => 'Becher'],
            ['name' => 'Speisequark', 'unit' => 'g'],
            ['name' => 'Frischkäse', 'unit' => 'Becher'],
            ['name' => 'Gouda', 'unit' => 'g', 'group' => 'MILCH'],
            ['name' => 'Emmentaler', 'unit' => 'g', 'group' => 'MILCH'],
            ['name' => 'Mozzarella', 'unit' => 'Pck', 'group' => 'MILCH'],
            ['name' => 'Feta', 'unit' => 'Pck', 'group' => 'MILCH'],
            ['name' => 'Parmesan', 'unit' => 'g', 'group' => 'MILCH'],
            ['name' => 'Eier', 'unit' => 'Stk'],

            // Fleisch, Wurst, Fisch
            ['name' => 'Hackfleisch', 'unit' => 'g'],
            ['name' => 'Hähnchenbrust', 'unit' => 'g'],
            ['name' => 'Schnitzel', 'unit' => 'g'],
            ['name' => 'Bratwurst', 'unit' => 'Stk'],
            ['name' => 'Aufschnitt', 'unit' => 'g', 'group' => 'FLEISCH'],
            ['name' => 'Salami', 'unit' => 'g'],
            ['name' => 'Kochschinken', 'unit' => 'g'],
            ['name' => 'Speck', 'unit' => 'g', 'group' => 'FLEISCH'],
            ['name' => 'Lachs', 'unit' => 'g'],
            ['name' => 'Fischstäbchen', 'unit' => 'Pck', 'group' => 'TIEFKUEHL'],

            // Tiefkuehl
            ['name' => 'Tiefkühlgemüse', 'unit' => 'Pck'],
            ['name' => 'Tiefkühlerbsen', 'unit' => 'Pck'],
            ['name' => 'Pommes', 'unit' => 'Pck', 'group' => 'TIEFKUEHL'],
            ['name' => 'Tiefkühlpizza', 'unit' => 'Stk'],
            ['name' => 'Speiseeis', 'unit' => 'Pck', 'group' => 'TIEFKUEHL'],
            ['name' => 'Tiefkühlbeeren', 'unit' => 'Pck'],

            // Getraenke
            ['name' => 'Mineralwasser', 'unit' => 'Kasten'],
            ['name' => 'Apfelsaft', 'unit' => 'l'],
            ['name' => 'Orangensaft', 'unit' => 'l'],
            ['name' => 'Apfelschorle', 'unit' => 'Kasten'],
            ['name' => 'Cola', 'unit' => 'l'],
            ['name' => 'Bier', 'unit' => 'Kasten'],
            ['name' => 'Weißwein', 'unit' => 'Fl'],
            ['name' => 'Rotwein', 'unit' => 'Fl'],

            // Suesses und Snacks
            ['name' => 'Schokolade', 'unit' => 'Tafel'],
            ['name' => 'Kekse', 'unit' => 'Pck'],
            ['name' => 'Chips', 'unit' => 'Pck'],
            ['name' => 'Gummibärchen', 'unit' => 'Pck', 'group' => 'SUESS'],
            ['name' => 'Salzstangen', 'unit' => 'Pck', 'group' => 'SUESS'],
            ['name' => 'Nüsse', 'unit' => 'Pck', 'group' => 'SUESS'],
            ['name' => 'Müsliriegel', 'unit' => 'Pck', 'group' => 'SUESS'],
            ['name' => 'Müsli', 'unit' => 'Pck', 'group' => 'TROCKEN'],

            // Drogerie und Haushalt
            ['name' => 'Zahnbürste', 'unit' => 'Stk', 'group' => 'DROGERIE'],
            ['name' => 'Deo', 'unit' => 'Stk', 'group' => 'DROGERIE'],
            ['name' => 'Handseife', 'unit' => 'Fl', 'group' => 'DROGERIE'],
            ['name' => 'Taschentücher', 'unit' => 'Pck', 'group' => 'DROGERIE'],
            ['name' => 'Spülmaschinentabs', 'unit' => 'Pck'],
            ['name' => 'Allzweckreiniger', 'unit' => 'Fl', 'group' => 'HAUSHALT'],
            ['name' => 'Schwämme', 'unit' => 'Pck', 'group' => 'HAUSHALT'],
        ];
    }

    /**
     * Standardmarkt mit Regalbereichen in Laufreihenfolge. Jeder Bereich
     * buendelt die genannten Warengruppen (B-04).
     *
     * @return array{name: string, sections: array<int, array{name: string, groups: array<int, string>}>}
     */
    public static function market(): array
    {
        return [
            'name' => 'Supermarkt',
            'sections' => [
                ['name' => 'Obst und Gemüse', 'groups' => ['OBST_GEMUESE']],
                ['name' => 'Backwaren', 'groups' => ['BACKWAREN']],
                ['name' => 'Kühlregal', 'groups' => ['MILCH', 'EIER']],
                ['name' => 'Fleisch und Fisch', 'groups' => ['FLEISCH', 'FISCH']],
                ['name' => 'Tiefkühl', 'groups' => ['TIEFKUEHL']],
                ['name' => 'Vorratsregal', 'groups' => ['KONSERVEN', 'TROCKEN', 'GEWUERZE']],
                ['name' => 'Süßes und Snacks', 'groups' => ['SUESS']],
                ['name' => 'Getränke', 'groups' => ['GETRAENKE']],
                ['name' => 'Haushalt und Drogerie', 'groups' => ['HAUSHALT', 'DROGERIE']],
                ['name' => 'Sonstiges', 'groups' => ['SONSTIGES']],
            ],
        ];
    }
}
