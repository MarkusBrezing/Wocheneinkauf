<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Models\ProductGroupRule;
use Illuminate\Support\Facades\Log;

/**
 * Bestimmt die Warengruppe zu einem Freitext-Artikelnamen (B-07).
 *
 * Ersetzt KEYWORD_RULES aus dem alten index.html. Der Unterschied: die Regeln
 * liegen jetzt je Familie in der Datenbank, jede Familie kann also eigene
 * ergaenzen, ohne dass jemand Code anfasst.
 *
 * Reihenfolge: Erst der Artikelkatalog -- kennt die Familie den Artikel
 * bereits, gilt dessen Zuordnung. Erst danach greifen die Stichwortregeln,
 * die kleinste priority zuerst.
 */
class ProductCategorizer
{
    public function categorize(string $rawName): ?ProductGroup
    {
        $normalisiert = Product::normalize($rawName);

        if ($normalisiert === '') {
            return null;
        }

        return $this->ausKatalog($normalisiert) ?? $this->ausRegeln($normalisiert);
    }

    /**
     * Nur die Stichwortregeln fragen, ohne den Vorrang des Katalogs.
     *
     * Fuer den einen Fall, in dem der Katalog nicht helfen kann: wenn der
     * Artikel selbst darin steht und gerade *neu* einsortiert werden soll --
     * etwa nachdem eine Regel korrigiert wurde. categorize() lieferte dort
     * seine eigene, alte Zuordnung zurueck.
     */
    public function categorizeByRules(string $rawName): ?ProductGroup
    {
        $normalisiert = Product::normalize($rawName);

        return $normalisiert === '' ? null : $this->ausRegeln($normalisiert);
    }

    /**
     * Ein bereits bekannter Artikel schlaegt jede Stichwortregel -- die
     * Familie hat ihn irgendwann bewusst einsortiert.
     */
    protected function ausKatalog(string $normalisiert): ?ProductGroup
    {
        return Product::query()
            ->where('name_normalized', $normalisiert)
            ->whereNotNull('product_group_id')
            ->with('productGroup')
            ->first()?->productGroup;
    }

    protected function ausRegeln(string $normalisiert): ?ProductGroup
    {
        $regeln = ProductGroupRule::query()
            ->orderBy('priority')
            ->orderBy('id')
            ->with('productGroup')
            ->get();

        foreach ($regeln as $regel) {
            if ($this->trifft($regel, $normalisiert)) {
                return $regel->productGroup;
            }
        }

        return null;
    }

    protected function trifft(ProductGroupRule $regel, string $normalisiert): bool
    {
        if (! $regel->is_regex) {
            return str_contains($normalisiert, mb_strtolower($regel->pattern));
        }

        // Die Regel kommt aus der Datenbank und ist damit von Nutzern
        // beeinflussbar. Ein fehlerhafter Ausdruck darf die Kategorisierung
        // nicht sprengen -- er wird protokolliert und uebersprungen.
        $treffer = @preg_match('/'.str_replace('/', '\/', $regel->pattern).'/ui', $normalisiert);

        if ($treffer === false) {
            Log::warning('Ungültige Stichwortregel übersprungen', [
                'rule_id' => $regel->getKey(),
                'pattern' => $regel->pattern,
            ]);

            return false;
        }

        return $treffer === 1;
    }
}
