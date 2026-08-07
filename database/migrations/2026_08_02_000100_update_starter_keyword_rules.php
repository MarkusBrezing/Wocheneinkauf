<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Bringt die Stichwortregeln bestehender Familien auf den korrigierten
 * Startdatensatz (B-07).
 *
 * Warum eine Migration und nicht `catalog:install-starter-data`: Der Befehl
 * ist bewusst additiv -- er legt fehlende Regeln an und laesst alles andere in
 * Ruhe. Hier muessen aber Muster *geaendert* ("apfel" -> Umlautvariante) und
 * *entfernt* werden ("wasser" fing die Wassermelone). Beides kann er nicht,
 * und er soll es auch nicht koennen: Sonst wuerde jeder Nachlauf die eigenen
 * Regeln einer Familie ueberschreiben.
 *
 * Angefasst wird deshalb nur, was noch unveraendert so ausgeliefert wurde --
 * gleiche Warengruppe, gleiche priority, gleiche Ausdrucksart. Wer eine Regel
 * selbst angepasst hat, behaelt sie.
 */
return new class extends Migration
{
    /**
     * Alte Fassung => neue Fassung.
     *
     * @var array<string, array{gruppe: string, alt: array{regex: int, priority: int}, neu: array{pattern: string, regex: int, priority: int}}>
     */
    protected const ERSETZEN = [
        'apfel' => [
            'gruppe' => 'OBST_GEMUESE',
            'alt' => ['regex' => 0, 'priority' => 100],
            'neu' => ['pattern' => '(äpfel|apfel)', 'regex' => 1, 'priority' => 100],
        ],
        'wurst' => [
            'gruppe' => 'FLEISCH',
            'alt' => ['regex' => 0, 'priority' => 100],
            'neu' => ['pattern' => '(würst|wurst)', 'regex' => 1, 'priority' => 100],
        ],
        'eis' => [
            'gruppe' => 'TIEFKUEHL',
            'alt' => ['regex' => 0, 'priority' => 60],
            'neu' => ['pattern' => '\beis(creme|würfel)?\b', 'regex' => 1, 'priority' => 60],
        ],
        // Bleiben inhaltlich, rutschen aber ans Ende der Kette: sonst gewaenne
        // "kaffee" gegen "sahne" und "tee" gegen "wurst".
        'kaffee' => [
            'gruppe' => 'GETRAENKE',
            'alt' => ['regex' => 0, 'priority' => 100],
            'neu' => ['pattern' => 'kaffee', 'regex' => 0, 'priority' => 110],
        ],
        'tee' => [
            'gruppe' => 'GETRAENKE',
            'alt' => ['regex' => 0, 'priority' => 100],
            'neu' => ['pattern' => 'tee', 'regex' => 0, 'priority' => 110],
        ],
    ];

    /**
     * Gehen im neuen Sammelausdruck auf und richten als Teilstring Schaden an.
     *
     * @var array<int, string>
     */
    protected const ENTFERNEN = ['wasser', 'saft', 'bier', 'wein'];

    /**
     * @var array<int, array{gruppe: string, pattern: string, regex: int, priority: int}>
     */
    protected const ERGAENZEN = [
        ['gruppe' => 'GETRAENKE', 'pattern' => '(saft|schorle|limonade|cola|wasser|bier|wein|sekt|tee|kaffee)\b', 'regex' => 1, 'priority' => 30],
        ['gruppe' => 'OBST_GEMUESE', 'pattern' => 'melone', 'regex' => 0, 'priority' => 100],
        ['gruppe' => 'OBST_GEMUESE', 'pattern' => 'traube', 'regex' => 0, 'priority' => 100],
    ];

    public function up(): void
    {
        $this->jeFamilie(function (int $familyId, array $gruppen) {
            foreach (self::ERSETZEN as $alt => $regel) {
                $this->regel($familyId, $gruppen, $regel['gruppe'], $alt, $regel['alt'])
                    ?->update([
                        'pattern' => $regel['neu']['pattern'],
                        'is_regex' => $regel['neu']['regex'],
                        'priority' => $regel['neu']['priority'],
                    ]);
            }

            foreach (self::ENTFERNEN as $pattern) {
                $this->regel($familyId, $gruppen, 'GETRAENKE', $pattern, ['regex' => 0, 'priority' => 100])
                    ?->delete();
            }

            foreach (self::ERGAENZEN as $regel) {
                $this->ergaenze($familyId, $gruppen, $regel);
            }
        });
    }

    public function down(): void
    {
        $this->jeFamilie(function (int $familyId, array $gruppen) {
            foreach (self::ERGAENZEN as $regel) {
                $this->basis($familyId)->where('pattern', $regel['pattern'])->delete();
            }

            foreach (self::ENTFERNEN as $pattern) {
                $this->ergaenze($familyId, $gruppen, [
                    'gruppe' => 'GETRAENKE', 'pattern' => $pattern, 'regex' => 0, 'priority' => 100,
                ]);
            }

            foreach (self::ERSETZEN as $alt => $regel) {
                $this->regel($familyId, $gruppen, $regel['gruppe'], $regel['neu']['pattern'], [
                    'regex' => $regel['neu']['regex'], 'priority' => $regel['neu']['priority'],
                ])?->update([
                    'pattern' => $alt,
                    'is_regex' => $regel['alt']['regex'],
                    'priority' => $regel['alt']['priority'],
                ]);
            }
        });
    }

    /**
     * @param  Closure(int, array<string, int>): void  $callback
     */
    protected function jeFamilie(Closure $callback): void
    {
        $familien = DB::table('product_group_rules')->distinct()->pluck('family_id');

        foreach ($familien as $familyId) {
            $gruppen = DB::table('product_groups')
                ->where('family_id', $familyId)
                ->pluck('id', 'code')
                ->all();

            $callback((int) $familyId, $gruppen);
        }
    }

    /**
     * Die Abfrage auf genau die ausgelieferte Fassung einer Regel -- oder
     * null, wenn es sie so nicht (mehr) gibt.
     *
     * @param  array<string, int>  $gruppen
     * @param  array{regex: int, priority: int}  $stand
     */
    protected function regel(int $familyId, array $gruppen, string $gruppenCode, string $pattern, array $stand): ?Builder
    {
        $gruppeId = $gruppen[$gruppenCode] ?? null;

        if ($gruppeId === null) {
            return null;
        }

        $abfrage = $this->basis($familyId)
            ->where('pattern', $pattern)
            ->where('product_group_id', $gruppeId)
            ->where('is_regex', $stand['regex'])
            ->where('priority', $stand['priority']);

        return $abfrage->exists() ? $abfrage : null;
    }

    /**
     * @param  array<string, int>  $gruppen
     * @param  array{gruppe: string, pattern: string, regex: int, priority: int}  $regel
     */
    protected function ergaenze(int $familyId, array $gruppen, array $regel): void
    {
        $gruppeId = $gruppen[$regel['gruppe']] ?? null;

        if ($gruppeId === null || $this->basis($familyId)->where('pattern', $regel['pattern'])->exists()) {
            return;
        }

        $this->basis($familyId)->insert([
            'family_id' => $familyId,
            'product_group_id' => $gruppeId,
            'pattern' => $regel['pattern'],
            'is_regex' => $regel['regex'],
            'priority' => $regel['priority'],
        ]);
    }

    protected function basis(int $familyId): Builder
    {
        return DB::table('product_group_rules')->where('family_id', $familyId);
    }
};
