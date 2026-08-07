<?php

namespace App\Http\Controllers;

use App\Core\Models\Family;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Die Familie, in der der Nutzer gerade arbeitet.
     *
     * Ohne gesetzten Mandanten koennte der Controller nichts sinnvoll tun --
     * und der Global Scope wuerde ohnehin leere Ergebnisse liefern. Deshalb
     * hier abbrechen statt weiter unten an einer unverstaendlichen Stelle.
     */
    protected function currentFamily(Request $request): Family
    {
        return $request->user()->currentFamily
            ?? abort(409, 'Für dieses Konto ist keine Familie gesetzt.');
    }

    /**
     * Strukturelle Stammdaten -- Warengruppen, Maerkte, Regalbereiche --
     * duerfen nur Owner und Admin aendern. Sie gelten fuer alle Mitglieder
     * gleichzeitig, ein Fehlgriff trifft also die ganze Familie.
     *
     * Alltagsdaten wie Artikel und Grundvorrat darf jedes Mitglied pflegen.
     */
    protected function requireAdmin(Request $request, Family $family): void
    {
        abort_unless($request->user()->administers($family), 403);
    }
}
