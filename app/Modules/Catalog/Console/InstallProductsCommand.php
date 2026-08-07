<?php

namespace App\Modules\Catalog\Console;

use App\Core\Models\Family;
use App\Modules\Catalog\Services\ProductCatalogInstaller;
use Illuminate\Console\Command;

/**
 * Fuellt den Artikelkatalog mit vorbereiteten Saetzen.
 *
 *   php artisan catalog:install-products
 *   php artisan catalog:install-products --satz=vorrat --family=01KYY…
 *
 * Wiederholbar: Was es schon gibt, bleibt unangetastet.
 */
class InstallProductsCommand extends Command
{
    protected $signature = 'catalog:install-products
                            {--family= : public_id einer einzelnen Familie; ohne Angabe alle}
                            {--satz=* : vorrat, alltag; ohne Angabe beide}';

    protected $description = 'Legt gängige Artikel im Katalog an (Grundvorrat und Alltagsartikel)';

    public function handle(ProductCatalogInstaller $installer): int
    {
        $saetze = $this->option('satz') ?: ProductCatalogInstaller::SAETZE;
        $unbekannt = array_diff($saetze, ProductCatalogInstaller::SAETZE);

        if ($unbekannt !== []) {
            $this->error('Unbekannter Satz: '.implode(', ', $unbekannt)
                .'. Möglich sind: '.implode(', ', ProductCatalogInstaller::SAETZE));

            return self::FAILURE;
        }

        $familien = Family::query()
            ->when($this->option('family'), fn ($q, $id) => $q->where('public_id', $id))
            ->get();

        if ($familien->isEmpty()) {
            $this->warn('Keine passende Familie gefunden.');

            return self::FAILURE;
        }

        foreach ($familien as $family) {
            $ergebnis = $installer->install($family, $saetze);

            $this->line(sprintf(
                '  <info>✓</info> %s — %d angelegt, %d waren schon da',
                $family->name,
                $ergebnis['angelegt'],
                $ergebnis['vorhanden'],
            ));
        }

        $this->info('Sätze: '.implode(', ', $saetze));

        return self::SUCCESS;
    }
}
