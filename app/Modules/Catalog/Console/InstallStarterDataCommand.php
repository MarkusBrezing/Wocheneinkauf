<?php

namespace App\Modules\Catalog\Console;

use App\Core\Models\Family;
use App\Modules\Catalog\Services\StarterDataInstaller;
use Illuminate\Console\Command;

/**
 * Ruestet Familien mit dem Startdatensatz nach (B-09), die vor Schritt 3
 * entstanden sind. Wiederholt aufrufbar -- Vorhandenes bleibt unangetastet.
 */
class InstallStarterDataCommand extends Command
{
    protected $signature = 'catalog:install-starter-data
                            {--family= : public_id einer einzelnen Familie; ohne Angabe alle}';

    protected $description = 'Legt Warengruppen, Stichwortregeln und den Standardmarkt für Familien an';

    public function handle(StarterDataInstaller $installer): int
    {
        $familien = Family::query()
            ->when($this->option('family'), fn ($q, $id) => $q->where('public_id', $id))
            ->get();

        if ($familien->isEmpty()) {
            $this->warn('Keine passende Familie gefunden.');

            return self::FAILURE;
        }

        foreach ($familien as $family) {
            $installer->install($family);
            $this->line("  <info>✓</info> {$family->name}");
        }

        $this->info($familien->count().' Familie(n) versorgt.');

        return self::SUCCESS;
    }
}
