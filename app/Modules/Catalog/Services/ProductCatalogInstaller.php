<?php

namespace App\Modules\Catalog\Services;

use App\Core\Models\Family;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Data\StarterData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use Illuminate\Support\Facades\DB;

/**
 * Fuellt den Artikelkatalog einer Familie mit einem vorbereiteten Satz.
 *
 * Zwei Saetze stehen bereit:
 *
 *   vorrat   Grundvorrat -- was im Schrank steht (Mehl, Oel, Konserven).
 *            Wird als is_pantry_staple gefuehrt und von Rezepten als
 *            vorhanden vorausgesetzt (D-05).
 *   alltag   Was Woche fuer Woche nachgekauft wird -- Frisches, Gekuehltes,
 *            Getraenke, Drogerie.
 *
 * Beide laufen nicht automatisch bei der Familienanlage wie der
 * Startdatensatz (B-09): Sie treffen eine Annahme darueber, wie jemand kocht
 * und einkauft. Wer sie will, holt sie sich -- wiederholbar, Vorhandenes
 * bleibt unangetastet.
 */
class ProductCatalogInstaller
{
    public const SAETZE = ['vorrat', 'alltag'];

    public function __construct(
        protected FamilyContext $context,
        protected ProductCategorizer $categorizer,
    ) {}

    /**
     * @param  array<int, string>  $saetze
     * @return array{angelegt: int, vorhanden: int}
     */
    public function install(Family $family, array $saetze = self::SAETZE): array
    {
        return $this->context->runFor($family->getKey(), function () use ($saetze) {
            return DB::transaction(function () use ($saetze) {
                $angelegt = 0;
                $vorhanden = 0;

                foreach ($this->eintraege($saetze) as $eintrag) {
                    // Ueber die Normalform geprueft: "Möhren" und "möhren"
                    // sind derselbe Artikel, und ein bereits gepflegter
                    // Eintrag hat Vorrang vor der Vorlage.
                    if (Product::matchingName($eintrag['name'])->exists()) {
                        $vorhanden++;

                        continue;
                    }

                    $this->anlegen($eintrag);
                    $angelegt++;
                }

                return ['angelegt' => $angelegt, 'vorhanden' => $vorhanden];
            });
        });
    }

    /**
     * @param  array<int, string>  $saetze
     * @return array<int, array{name: string, unit?: ?string, group?: string, vorrat?: bool}>
     */
    protected function eintraege(array $saetze): array
    {
        $eintraege = [];

        if (in_array('vorrat', $saetze, true)) {
            foreach (StarterData::pantryStaples() as $eintrag) {
                $eintraege[] = $eintrag + ['vorrat' => true];
            }
        }

        if (in_array('alltag', $saetze, true)) {
            foreach (StarterData::everydayProducts() as $eintrag) {
                $eintraege[] = $eintrag + ['vorrat' => false];
            }
        }

        return $eintraege;
    }

    /**
     * @param  array{name: string, unit?: ?string, group?: string, vorrat?: bool}  $eintrag
     */
    protected function anlegen(array $eintrag): Product
    {
        $product = new Product([
            'name' => $eintrag['name'],
            'default_unit' => $eintrag['unit'] ?? null,
            'is_pantry_staple' => $eintrag['vorrat'] ?? false,
        ]);

        // Ausdrueckliche Warengruppe nur dort, wo die Stichwortregeln nichts
        // finden oder danebenliegen wuerden -- sonst entscheidet B-07, wie
        // beim Anlegen ueber die Oberflaeche auch.
        $product->product_group_id = $this->gruppe($eintrag['group'] ?? null)
            ?? $this->categorizer->categorizeByRules($eintrag['name'])?->getKey();

        $product->save();

        return $product;
    }

    protected function gruppe(?string $code): ?int
    {
        return $code === null ? null : ProductGroup::where('code', $code)->value('id');
    }
}
