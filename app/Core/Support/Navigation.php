<?php

namespace App\Core\Support;

/**
 * Die Navigation und die Gerätezuordnung der Seiten -- an einer Stelle.
 *
 * Beides gehört zusammen: Ob eine Seite für den PC oder fürs Handy gedacht
 * ist, gehört ins Menü (damit man es vor dem Klick sieht) und auf die Seite
 * selbst (damit man es danach noch weiß). Zwei getrennte Listen wären nach der
 * zweiten Änderung auseinandergelaufen.
 *
 * Die Geräteangabe ist eine Empfehlung, keine Sperre. Der Artikelkatalog lässt
 * sich auch am Handy öffnen -- er ist dort nur mühsam, und genau das soll die
 * Kennzeichnung vorher sagen.
 */
class Navigation
{
    /**
     * Die drei Gerätearten mit ihrem Erscheinungsbild.
     *
     * Die Farbe trägt die Unterscheidung nicht allein: Jede Kennzeichnung hat
     * zusätzlich ein Zeichen und einen Text. Wer Farben schlecht unterscheidet,
     * liest sonst nichts.
     *
     * @var array<string, array{label: string, kurz: string, zeichen: string, hinweis: string}>
     */
    public const GERAETE = [
        'pc' => [
            'label' => 'Für den PC',
            'kurz' => 'PC',
            'zeichen' => '🖥',
            'hinweis' => 'Diese Seite wird am PC gepflegt: viele Spalten, viel Tastatur. Am Telefon ist sie unhandlich.',
        ],
        'tablet' => [
            'label' => 'PC und Tablet',
            'kurz' => 'PC + Tablet',
            'zeichen' => '▤',
            'hinweis' => 'Diese Seite ist für PC und Tablet gedacht. Am Telefon geht sie, macht aber wenig Freude.',
        ],
        'handy' => [
            'label' => 'Fürs Handy',
            'kurz' => 'Handy',
            'zeichen' => '▯',
            'hinweis' => 'Diese Seite ist fürs Telefon gebaut -- große Flächen, wenig darauf.',
        ],
    ];

    /**
     * Zwei Ebenen: oben die fünf Bereiche, darunter deren Seiten.
     *
     * `treffer` sind Präfixe von Routennamen. Sie entscheiden, welcher Eintrag
     * als aktiv gilt -- auch auf Unterseiten, die selbst nicht im Menü stehen
     * (ein einzelnes Rezept gehört zu „Rezepte", ohne dort aufzutauchen).
     *
     * @return array<string, array{label: string, items: array<int, array{label: string, route: string, geraet: string, treffer: array<int, string>}>}>
     */
    public static function gruppen(): array
    {
        return [
            'einkaufen' => [
                'label' => 'Einkaufen',
                'items' => [
                    [
                        'label' => 'Liste',
                        'route' => 'einkaufsliste.index',
                        'geraet' => 'tablet',
                        'treffer' => ['einkaufsliste.index', 'einkaufsliste.eintraege', 'einkaufsliste.markt', 'einkaufsliste.drucken'],
                    ],
                    [
                        'label' => 'Einkaufen',
                        'route' => 'einkaufsliste.einkaufen',
                        'geraet' => 'handy',
                        'treffer' => ['einkaufsliste.einkaufen'],
                    ],
                    [
                        'label' => 'Neue Woche',
                        'route' => 'einkaufsliste.neue-woche',
                        'geraet' => 'tablet',
                        'treffer' => ['einkaufsliste.neue-woche'],
                    ],
                    [
                        'label' => 'Archiv',
                        'route' => 'einkaufsliste.archiv.index',
                        'geraet' => 'tablet',
                        'treffer' => ['einkaufsliste.archiv'],
                    ],
                    [
                        'label' => 'Vorlagen',
                        'route' => 'vorlagen.index',
                        'geraet' => 'pc',
                        'treffer' => ['vorlagen.'],
                    ],
                ],
            ],

            'planen' => [
                'label' => 'Planen',
                'items' => [
                    [
                        'label' => 'Wochenplan',
                        'route' => 'wochenplan.index',
                        'geraet' => 'tablet',
                        'treffer' => ['wochenplan.index', 'wochenplan.slots', 'wochenplan.vorschlag', 'wochenplan.auf-die-liste', 'wochenplan.kopieren', 'wochenplan.drucken'],
                    ],
                    [
                        'label' => 'Historie',
                        'route' => 'wochenplan.historie',
                        'geraet' => 'pc',
                        'treffer' => ['wochenplan.historie', 'wochenplan.rezept.'],
                    ],
                    [
                        'label' => 'Rezepte',
                        'route' => 'rezepte.index',
                        'geraet' => 'tablet',
                        'treffer' => ['rezepte.'],
                    ],
                ],
            ],

            'vorrat' => [
                'label' => 'Vorrat',
                'items' => [
                    [
                        'label' => 'Bestand',
                        'route' => 'vorrat.index',
                        'geraet' => 'tablet',
                        'treffer' => ['vorrat.index', 'vorrat.store', 'vorrat.update', 'vorrat.destroy', 'vorrat.buchen', 'vorrat.verlauf', 'vorrat.auf-die-liste'],
                    ],
                    [
                        // Zaehlen geschieht vor dem Schrank, nicht am
                        // Schreibtisch.
                        'label' => 'Bestandsaufnahme',
                        'route' => 'einkaufsliste.inventur',
                        'geraet' => 'tablet',
                        'treffer' => ['einkaufsliste.inventur'],
                    ],
                ],
            ],

            /*
             * Die Reihenfolge ist die der Pflege, nicht die der Häufigkeit:
             * Warengruppen zuerst, weil alles andere auf ihnen aufbaut; dann
             * die Märkte, deren Regalbereiche Warengruppen zugeordnet
             * bekommen; zuletzt die Artikel, die beides brauchen -- eine
             * Warengruppe zum Einsortieren und Märkte für die Verfügbarkeit.
             *
             * Wer den Bereich anklickt, landet damit am Anfang der Kette und
             * nicht in ihrer Mitte.
             */
            'stammdaten' => [
                'label' => 'Stammdaten',
                'items' => [
                    [
                        'label' => 'Warengruppen',
                        'route' => 'stammdaten.warengruppen.index',
                        'geraet' => 'pc',
                        'treffer' => ['stammdaten.warengruppen'],
                    ],
                    [
                        'label' => 'Märkte',
                        'route' => 'stammdaten.maerkte.index',
                        'geraet' => 'pc',
                        'treffer' => ['stammdaten.maerkte', 'stammdaten.bereiche'],
                    ],
                    [
                        'label' => 'Artikel',
                        'route' => 'stammdaten.produkte.index',
                        'geraet' => 'pc',
                        // Das Zuruecksetzen haengt am Artikelkatalog -- ohne
                        // den Treffer verloere die Seite ihre Menueebene.
                        'treffer' => ['stammdaten.produkte', 'stammdaten.zuruecksetzen'],
                    ],
                ],
            ],

            'verwaltung' => [
                'label' => 'Verwaltung',
                'items' => [
                    [
                        'label' => 'Familie',
                        'route' => 'family.edit',
                        'geraet' => 'pc',
                        'treffer' => ['family.', 'invitations.', 'invitation.'],
                    ],
                    [
                        'label' => 'Konto',
                        'route' => 'account.edit',
                        'geraet' => 'pc',
                        'treffer' => ['account.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Der Menüeintrag zum aktuellen Routennamen.
     *
     * Es gewinnt der längste passende Präfix: „wochenplan.historie" gehört zur
     * Historie und nicht zum Wochenplan, obwohl beide mit „wochenplan."
     * beginnen.
     *
     * @return array{gruppe: string, item: array<string, mixed>}|null
     */
    public static function aktiv(?string $routenname): ?array
    {
        if ($routenname === null) {
            return null;
        }

        $bester = null;
        $laenge = -1;

        foreach (self::gruppen() as $schluessel => $gruppe) {
            foreach ($gruppe['items'] as $item) {
                foreach ($item['treffer'] as $praefix) {
                    if (str_starts_with($routenname, $praefix) && strlen($praefix) > $laenge) {
                        $bester = ['gruppe' => $schluessel, 'item' => $item];
                        $laenge = strlen($praefix);
                    }
                }
            }
        }

        return $bester;
    }

    /**
     * Für welches Gerät die aktuelle Seite gedacht ist -- oder null, wenn sie
     * überall gleich gut funktioniert (Übersicht, Anmeldung, Rechtstexte).
     */
    public static function geraet(?string $routenname): ?string
    {
        return self::aktiv($routenname)['item']['geraet'] ?? null;
    }
}
