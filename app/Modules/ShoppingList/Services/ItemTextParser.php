<?php

namespace App\Modules\ShoppingList\Services;

use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Data\Units;
use Illuminate\Support\Str;

/**
 * Zerlegt die eine Eingabezeile, mit der ein Artikel auf die Liste kommt
 * (C-01): "2 l Milch", "500g Mehl", "1-2 Zwiebeln", "Brot, aufgebacken".
 *
 * Leitgedanke: Im Zweifel bleibt der Text stehen. Ein falsch geratener Name
 * aergert taeglich, eine nicht erkannte Menge kostet einen Tastendruck.
 * Deshalb gilt ein Wort nur dann als Einheit, wenn es in Units bekannt ist --
 * sonst waere in "3 Zwiebeln" die Einheit "Zwiebeln" und der Artikel namenlos.
 */
class ItemTextParser
{
    /**
     * Eine Menge ist entweder ein Bereich ("1-2"), ein Bruch ("1/2") oder
     * eine Dezimalzahl ("1,5"). Die Reihenfolge ist wichtig: der Bereich muss
     * vor der einfachen Zahl stehen, sonst gewinnt "1" gegen "1-2".
     */
    protected const MENGE = '\d+(?:[.,]\d+)?\s*[-–]\s*\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+|\d+(?:[.,]\d+)?';

    public function parse(string $eingabe): ParsedItem
    {
        $text = Str::squish($eingabe);

        [$text, $note] = $this->notizAbtrennen($text);

        $treffer = $this->mengeVorn($text) ?? $this->mengeHinten($text);

        if ($treffer === null) {
            return new ParsedItem(name: $this->kuerze($text, 160), note: $note);
        }

        [$mengeRoh, $einheit, $name] = $treffer;

        // Bleibt nach dem Abziehen der Menge kein Name uebrig ("2 l"), ist die
        // Zerlegung offenbar danebengegangen -- dann gilt die Eingabe.
        if (trim($name) === '') {
            return new ParsedItem(name: $this->kuerze($text, 160), note: $note);
        }

        return new ParsedItem(
            name: $this->kuerze($name, 160),
            amount: $this->alsZahl($mengeRoh),
            amountRaw: $this->rohform($mengeRoh),
            unit: $einheit,
            note: $note,
        );
    }

    /**
     * Alles hinter dem ersten Komma ist Notiz -- ausser das Komma steht
     * zwischen Ziffern, dann ist es ein Dezimaltrennzeichen ("1,5 l Milch").
     *
     * @return array{0: string, 1: ?string}
     */
    protected function notizAbtrennen(string $text): array
    {
        $teile = preg_split('/(?<!\d),\s*|,\s*(?!\d)/u', $text, 2);

        if ($teile === false || count($teile) < 2 || trim($teile[1]) === '') {
            return [$text, null];
        }

        return [trim($teile[0]), $this->kuerze(trim($teile[1]), 160)];
    }

    /**
     * "2 l Milch", "500g Mehl", "3 Zwiebeln".
     *
     * @return array{0: string, 1: ?string, 2: string}|null
     */
    protected function mengeVorn(string $text): ?array
    {
        $muster = '/^(?P<menge>'.self::MENGE.')\s*(?P<einheit>[\p{L}]+\.?)?\s*(?P<rest>.*)$/u';

        if (! preg_match($muster, $text, $m)) {
            return null;
        }

        $einheit = Units::normalize($m['einheit'] ?? '');

        // Unbekanntes Wort direkt hinter der Zahl gehoert zum Namen.
        $rest = $einheit === null
            ? trim(($m['einheit'] ?? '').' '.$m['rest'])
            : trim($m['rest']);

        return [$m['menge'], $einheit, $rest];
    }

    /**
     * "Milch 2l", "Mehl 500 g".
     *
     * Hier ist eine bekannte Einheit Pflicht. Eine nackte Zahl am Ende gehoert
     * sonst haeufiger zum Namen als zur Menge -- "Vollmilch 1,5" ist keine
     * Bestellung ueber eineinhalb Milch.
     *
     * @return array{0: string, 1: ?string, 2: string}|null
     */
    protected function mengeHinten(string $text): ?array
    {
        $muster = '/^(?P<rest>.+?)\s+(?P<menge>'.self::MENGE.')\s*(?P<einheit>[\p{L}]+\.?)$/u';

        if (! preg_match($muster, $text, $m)) {
            return null;
        }

        $einheit = Units::normalize($m['einheit']);

        if ($einheit === null) {
            return null;
        }

        return [$m['menge'], $einheit, trim($m['rest'])];
    }

    /**
     * Der rechenbare Wert einer Menge, oder null wenn es keinen gibt.
     * Ein Bereich ("1-2") hat keinen -- deshalb wird er auch nicht addiert.
     */
    protected function alsZahl(string $mengeRoh): ?float
    {
        $menge = str_replace(' ', '', $mengeRoh);

        if (preg_match('/^(\d+)\/(\d+)$/', $menge, $m) && (int) $m[2] !== 0) {
            return (int) $m[1] / (int) $m[2];
        }

        if (preg_match('/^\d+(?:[.,]\d+)?$/', $menge)) {
            return (float) str_replace(',', '.', $menge);
        }

        return null;
    }

    /**
     * Die Schreibweise, die der Nutzer gewaehlt hat -- aber nur, wenn sie sich
     * nicht aus der Zahl selbst ergibt. "1/2" und "1-2" bleiben erhalten,
     * "2" nicht.
     */
    protected function rohform(string $mengeRoh): ?string
    {
        $menge = preg_replace('/\s+/', '', $mengeRoh) ?? $mengeRoh;

        if (preg_match('/^\d+(?:[.,]\d+)?$/', $menge)) {
            return null;
        }

        return $this->kuerze(str_replace('–', '-', $menge), 20);
    }

    protected function kuerze(string $text, int $laenge): string
    {
        return Str::limit(trim($text), $laenge, '');
    }
}
