<?php

namespace App\Modules\Catalog\Services;

use App\Core\Models\Family;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Data\StarterData;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Models\ProductGroupRule;
use App\Modules\Markets\Models\Market;
use Illuminate\Support\Facades\DB;

/**
 * Legt den Startdatensatz einer neuen Familie an (B-09).
 *
 * Ohne ihn staende eine frisch registrierte Familie vor leeren Listen und
 * muesste erst zwanzig Warengruppen tippen, bevor sie den ersten Artikel
 * erfassen kann.
 *
 * Der Aufruf ist absichtlich wiederholbar: Was schon existiert, bleibt
 * unangetastet. So laesst sich der Befehl auch auf Familien anwenden, die vor
 * Schritt 3 entstanden sind.
 */
class StarterDataInstaller
{
    public function __construct(protected FamilyContext $context) {}

    public function install(Family $family): void
    {
        $this->context->runFor($family->getKey(), function () {
            DB::transaction(function () {
                $gruppen = $this->warengruppen();
                $this->regeln($gruppen);
                $this->markt($gruppen);
            });
        });
    }

    /**
     * @return array<string, ProductGroup> Warengruppen nach code
     */
    protected function warengruppen(): array
    {
        $gruppen = [];

        foreach (StarterData::productGroups() as $sort => $eintrag) {
            $gruppen[$eintrag['code']] = ProductGroup::firstOrCreate(
                ['code' => $eintrag['code']],
                ['label' => $eintrag['label'], 'sort' => ($sort + 1) * 10],
            );
        }

        return $gruppen;
    }

    /**
     * @param  array<string, ProductGroup>  $gruppen
     */
    protected function regeln(array $gruppen): void
    {
        foreach (StarterData::rules() as $regel) {
            $gruppe = $gruppen[$regel['group']] ?? null;

            if (! $gruppe) {
                continue;
            }

            ProductGroupRule::firstOrCreate(
                ['pattern' => $regel['pattern']],
                [
                    'product_group_id' => $gruppe->getKey(),
                    'is_regex' => $regel['regex'] ?? false,
                    'priority' => $regel['priority'] ?? 100,
                ],
            );
        }
    }

    /**
     * @param  array<string, ProductGroup>  $gruppen
     */
    protected function markt(array $gruppen): void
    {
        $vorlage = StarterData::market();

        $markt = Market::firstOrCreate(
            ['name' => $vorlage['name']],
            ['is_active' => true, 'sort' => 0],
        );

        // Nur beim ersten Markt der Familie -- ein spaeterer Nachlauf des
        // Befehls soll eine bewusst getroffene Wahl nicht ueberschreiben.
        if (! Market::where('is_default', true)->exists()) {
            $markt->makeDefault();
        }

        foreach ($vorlage['sections'] as $sort => $bereich) {
            $section = $markt->sections()->firstOrCreate(
                ['name' => $bereich['name']],
                ['sort' => ($sort + 1) * 10, 'family_id' => $markt->family_id],
            );

            $ids = collect($bereich['groups'])
                ->map(fn (string $code) => $gruppen[$code] ?? null)
                ->filter()
                ->mapWithKeys(fn (ProductGroup $g) => [
                    $g->getKey() => ['family_id' => $g->family_id],
                ])
                ->all();

            $section->productGroups()->syncWithoutDetaching($ids);
        }
    }
}
