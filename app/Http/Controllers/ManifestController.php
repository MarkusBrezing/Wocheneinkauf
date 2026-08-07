<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Das Web-App-Manifest (Schritt 9).
 *
 * Als Route und nicht als Datei im public-Ordner: So steht der App-Name an
 * einer Stelle, und der richtige Medientyp ist unabhaengig davon, was der
 * Webserver ueber die Endung .webmanifest denkt.
 *
 * Die Symbole liegen unter /symbole und nicht unter dem naheliegenden /icons:
 * Apache bringt in mods-enabled/alias.conf ein "Alias /icons/" auf sein
 * eigenes Symbolverzeichnis mit. Der Alias greift vor dem Dateisystem -- die
 * Symbole waeren also unsichtbar, ohne dass irgendetwas im Projekt falsch
 * waere.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $name = config('app.name');

        return response()->json([
            'name' => $name,
            // Unter dem Symbol ist wenig Platz -- der Kurzname wird sonst
            // mitten im Wort abgeschnitten.
            'short_name' => 'Einkauf',
            'description' => 'Einkaufsliste, Vorrat und Wochenplan für die ganze Familie.',
            'lang' => 'de',
            'dir' => 'ltr',
            // Der Einstieg ist die Einkaufsansicht, nicht das Dashboard: Wer
            // das Symbol auf dem Telefon antippt, steht meistens im Laden.
            'start_url' => '/einkaufsliste/einkaufen',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#FDFBF7',
            'theme_color' => '#457A00',
            'icons' => [
                ['src' => '/symbole/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/symbole/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/symbole/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/symbole/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            // Verknuepfungen im Kontextmenue des Symbols.
            'shortcuts' => [
                [
                    'name' => 'Einkaufen',
                    'url' => '/einkaufsliste/einkaufen',
                    'icons' => [['src' => '/symbole/icon-192.png', 'sizes' => '192x192']],
                ],
                [
                    'name' => 'Liste bearbeiten',
                    'url' => '/einkaufsliste',
                    'icons' => [['src' => '/symbole/icon-192.png', 'sizes' => '192x192']],
                ],
                [
                    'name' => 'Wochenplan',
                    'url' => '/wochenplan',
                    'icons' => [['src' => '/symbole/icon-192.png', 'sizes' => '192x192']],
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
