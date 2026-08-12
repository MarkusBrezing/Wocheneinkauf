<?php

namespace App\Modules\ShoppingList\Data;

use Illuminate\Support\Str;

/**
 * Die Einheiten, die auf einem Einkaufszettel vorkommen.
 *
 * Zweck ist nicht Vollstaendigkeit, sondern eine gemeinsame Schreibweise:
 * "Liter", "ltr" und "l" muessen dieselbe Einheit ergeben, sonst stehen
 * "2 Liter Milch" und "1 l Milch" als zwei Eintraege auf der Liste (C-17).
 */
class Units
{
    /**
     * Schreibweise -> Normalform. Der Schluessel ist kleingeschrieben und
     * ohne Punkt, so wie normalisiere() den Eingabetext zurichtet.
     *
     * @var array<string, string>
     */
    protected const SCHREIBWEISEN = [
        'g' => 'g', 'gr' => 'g', 'gramm' => 'g',
        'kg' => 'kg', 'kilo' => 'kg', 'kilogramm' => 'kg',
        'l' => 'l', 'ltr' => 'l', 'liter' => 'l',
        'ml' => 'ml', 'milliliter' => 'ml',
        'stk' => 'Stk', 'st' => 'Stk', 'stueck' => 'Stk', 'stück' => 'Stk', 'x' => 'Stk',
        'pck' => 'Pck', 'pkg' => 'Pck', 'packung' => 'Pck', 'packungen' => 'Pck',
        'päckchen' => 'Pck', 'paeckchen' => 'Pck',
        'dose' => 'Dose', 'dosen' => 'Dose',
        'glas' => 'Glas', 'gläser' => 'Glas', 'glaeser' => 'Glas',
        'flasche' => 'Fl', 'flaschen' => 'Fl', 'fl' => 'Fl',
        'becher' => 'Becher',
        'kasten' => 'Kasten', 'kästen' => 'Kasten', 'kaesten' => 'Kasten',
        'beutel' => 'Beutel', 'tüte' => 'Beutel', 'tueten' => 'Beutel', 'tüten' => 'Beutel',
        'bund' => 'Bund',
        'kopf' => 'Kopf', 'köpfe' => 'Kopf',
        'scheibe' => 'Scheibe', 'scheiben' => 'Scheibe',
        'tafel' => 'Tafel', 'tafeln' => 'Tafel',
        'rolle' => 'Rolle', 'rollen' => 'Rolle',
        'el' => 'EL', 'esslöffel' => 'EL', 'essloeffel' => 'EL',
        'tl' => 'TL', 'teelöffel' => 'TL', 'teeloeffel' => 'TL',
        'prise' => 'Prise', 'prisen' => 'Prise',
        'portion' => 'Portion', 'portionen' => 'Portion',
    ];

    /**
     * Bekannte Einheit auf ihre Normalform bringen, sonst null.
     *
     * Bewusst kein "unbekannt heisst Einheit": In "2 Bananen" waere sonst
     * "Bananen" die Einheit und der Artikel haette keinen Namen mehr.
     */
    public static function normalize(?string $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        $schluessel = static::normalisiere($unit);

        if ($schluessel === '') {
            return null;
        }

        return static::SCHREIBWEISEN[$schluessel] ?? null;
    }

    /**
     * Wie normalize(), behaelt aber unbekannte Einheiten bei -- fuer Angaben
     * aus einem Formularfeld, in dem ausdruecklich eine Einheit steht.
     */
    public static function normalizeOrKeep(?string $unit): ?string
    {
        $unit = $unit === null ? null : trim($unit);

        if ($unit === null || $unit === '') {
            return null;
        }

        return static::normalize($unit) ?? Str::limit($unit, 20, '');
    }

    public static function isKnown(string $unit): bool
    {
        return static::normalize($unit) !== null;
    }

    protected static function normalisiere(string $unit): string
    {
        return rtrim(Str::lower(trim($unit)), '.');
    }
}
