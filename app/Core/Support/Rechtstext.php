<?php

namespace App\Core\Support;

use Illuminate\Support\HtmlString;

/**
 * Kleine Hilfe für die Rechtstexte.
 *
 * Impressum und Datenschutzerklärung leben von Angaben, die nur der Betreiber
 * kennt. Fehlt eine, ist die stille leere Zeile die schlechteste aller
 * Möglichkeiten: Die Seite sieht fertig aus, ist es aber nicht -- und ein
 * unvollständiges Impressum ist abmahnfähig.
 */
class Rechtstext
{
    public static function angabe(?string $wert): HtmlString
    {
        if ($wert !== null && trim($wert) !== '') {
            return new HtmlString(e($wert));
        }

        return new HtmlString(
            '<mark class="fehlt" title="In der .env eintragen">[bitte eintragen]</mark>'
        );
    }
}
