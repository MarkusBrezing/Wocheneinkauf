<?php

namespace App\Core\Support;

use Illuminate\Support\Str;

/**
 * Das Anwenderhandbuch.
 *
 * Quelle sind die Markdown-Dateien in docs/handbuch. Sie sind zugleich die
 * Vorlage für die Word-Fassung -- der Text steht also genau einmal. Wäre er
 * in Blade-Dateien gegossen, hätte die gedruckte Fassung spätestens nach der
 * zweiten Änderung etwas anderes behauptet als die Webseite.
 *
 * Kapitelnummer, Überschrift und Kurzbeschreibung liest die Klasse aus der
 * Datei, statt sie in einer zweiten Liste zu pflegen.
 */
class Handbuch
{
    protected const ORDNER = 'docs/handbuch';

    /**
     * Alle Kapitel in ihrer Reihenfolge.
     *
     * @return array<int, array{slug: string, nummer: int, titel: string, geraet: ?string, teaser: string, datei: string}>
     */
    public static function kapitel(): array
    {
        $dateien = glob(base_path(self::ORDNER.'/*.md')) ?: [];
        sort($dateien);

        return array_values(array_filter(array_map(self::lesen(...), $dateien)));
    }

    /**
     * Ein Kapitel als HTML -- oder null, wenn es den Slug nicht gibt.
     *
     * Der Slug wird ausschließlich gegen die vorhandenen Kapitel geprüft und
     * nie in einen Pfad eingesetzt: Ein „../../.env" trifft so ins Leere,
     * statt eine Datei zu öffnen.
     *
     * @return array{kapitel: array<string, mixed>, html: string, vorheriges: ?array<string, mixed>, naechstes: ?array<string, mixed>}|null
     */
    public static function finden(string $slug): ?array
    {
        $alle = self::kapitel();

        foreach ($alle as $i => $kapitel) {
            if ($kapitel['slug'] === $slug) {
                return [
                    'kapitel' => $kapitel,
                    'html' => self::html($kapitel['datei']),
                    'vorheriges' => $alle[$i - 1] ?? null,
                    'naechstes' => $alle[$i + 1] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Alle Kapitel am Stück -- für den Ausdruck.
     *
     * @return array<int, array{kapitel: array<string, mixed>, html: string}>
     */
    public static function komplett(): array
    {
        return array_map(
            fn (array $k) => ['kapitel' => $k, 'html' => self::html($k['datei'])],
            self::kapitel()
        );
    }

    protected static function html(string $datei): string
    {
        // html_input: strip -- die Dateien sind zwar eigene, aber ein
        // Handbuch ist kein Ort, an dem Auszeichnung durchgereicht werden
        // muss.
        return Str::markdown(file_get_contents($datei), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Kopfdaten eines Kapitels aus der Datei selbst.
     *
     * @return array{slug: string, nummer: int, titel: string, geraet: ?string, teaser: string, datei: string}|null
     */
    protected static function lesen(string $datei): ?array
    {
        $name = basename($datei, '.md');

        if (! preg_match('/^(\d+)-(.+)$/', $name, $treffer)) {
            return null;
        }

        $zeilen = array_map('trim', file($datei, FILE_IGNORE_NEW_LINES));
        $titel = '';
        $geraet = null;
        $teaser = '';

        foreach ($zeilen as $zeile) {
            if ($zeile === '') {
                continue;
            }
            if ($titel === '' && str_starts_with($zeile, '# ')) {
                $titel = substr($zeile, 2);

                continue;
            }
            // Die Zeile „*Seitenart: … · Adresse: …*" steht direkt unter der
            // Überschrift und gehört nicht in die Kurzbeschreibung.
            if ($titel !== '' && $geraet === null && str_starts_with($zeile, '*Seitenart:')) {
                $geraet = trim(explode('·', trim($zeile, '*'))[0]);
                $geraet = trim(str_replace('Seitenart:', '', $geraet));

                continue;
            }
            if ($titel !== '' && ! str_starts_with($zeile, '#')) {
                $teaser = strip_tags(Str::markdown($zeile));

                break;
            }
        }

        return [
            'slug' => $treffer[2],
            'nummer' => (int) $treffer[1],
            'titel' => $titel ?: $treffer[2],
            'geraet' => $geraet,
            'teaser' => Str::limit(trim($teaser), 170),
            'datei' => $datei,
        ];
    }
}
