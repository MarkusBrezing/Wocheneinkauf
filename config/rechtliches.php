<?php

/*
 * Angaben für Impressum und Datenschutzerklärung.
 *
 * Bewusst in der Konfiguration und nicht in den Views: Die Texte sind für alle
 * gleich, die Angaben nicht. Wer die App selbst betreibt, trägt sie in der
 * .env ein, statt Blade-Dateien zu ändern. Fehlt eine Angabe, zeigt die Seite
 * eine sichtbare Lücke -- besser als eine falsche Angabe, die niemandem
 * auffällt.
 */
return [
    'betreiber' => [
        'name' => env('IMPRESSUM_NAME'),
        'strasse' => env('IMPRESSUM_STRASSE'),
        'ort' => env('IMPRESSUM_ORT'),
        'land' => env('IMPRESSUM_LAND', 'Deutschland'),
        'email' => env('IMPRESSUM_EMAIL'),
        'telefon' => env('IMPRESSUM_TELEFON'),
        'ust_id' => env('IMPRESSUM_UST_ID'),
    ],

    // Wer die Server stellt -- gehört in die Datenschutzerklärung, weil dort
    // die Daten tatsächlich liegen.
    'hoster' => [
        'name' => env('HOSTER_NAME', 'STRATO AG'),
        'anschrift' => env('HOSTER_ANSCHRIFT', 'Otto-Ostrowski-Straße 7, 10249 Berlin'),
    ],

    // Nur relevant, wenn die App über den Familienkreis hinaus angeboten wird.
    'privat' => env('RECHTLICHES_PRIVAT', true),
];
